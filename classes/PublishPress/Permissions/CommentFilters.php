<?php

namespace PublishPress\Permissions;

class CommentFilters
{
    public function __construct() {
        add_filter('comments_clauses', [$this, 'fltCommentsClauses'], 10, 2);

        add_filter('wp_count_comments', [$this, 'fltCountComments'], 10, 2);
    }

    public function fltCommentsClauses($clauses, $qry_obj = false, $args = [])
    {
        global $wpdb;

        $defaults = ['query_contexts' => []];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $query_contexts[] = 'comments';

        if (did_action('comment_post'))  // don't filter comment retrieval for email notification
            return $clauses;

        if (is_admin() && defined('PP_NO_COMMENT_FILTERING')) {
            global $current_user;

            //if ( empty( $current_user->allcaps['moderate_comments'] ) )
            return $clauses;
        }

        if (empty($clauses['join']) || !strpos($clauses['join'], $wpdb->posts))
            $clauses['join'] .= " INNER JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->comments.comment_post_ID";

        // (subsequent filter will expand to additional statuses as appropriate)
        $clauses['where'] = preg_replace("/ post_status\s*=\s*[']?publish[']?/", " $wpdb->posts.post_status = 'publish'", $clauses['where']);

        $post_type = '';
        $post_id = ($qry_obj && !empty($qry_obj->query_vars['post_id'])) ? $qry_obj->query_vars['post_id'] : 0;

        if ($post_id) {
            if ($_post = get_post($post_id))
                $post_type = $_post->post_type;
        } else {
            $post_type = ($qry_obj && isset($qry_obj->query_vars['post_type'])) ? $qry_obj->query_vars['post_type'] : '';
        }

        if ($post_type && !in_array($post_type, presspermit()->getEnabledPostTypes(), true))
            return $clauses;

        $clauses['where'] = "1=1 " . apply_filters( 'presspermit_posts_where', 
                'AND ' . $clauses['where'],
                array_merge($args, ['post_types' => $post_type, 'skip_teaser' => true, 'query_contexts' => $query_contexts])
            );

        if (!empty($clauses['groupby']) && !empty($clauses['select']) && (false !== stripos($clauses['select'], 'COUNT(')) && !defined('PRESSPERMIT_LEGACY_COMMENT_FILTERING')) {
            $clauses['orderby'] = '';
        }

        return $clauses;
    }

    public function fltCountComments($comment_count, $post_id = 0)
    {
        global $wpdb;

        $post_id = (int)$post_id;

        $where = ($post_id > 0) ? $wpdb->prepare("comment_post_ID = %d", $post_id) : '1=1';

        $clauses = $this->fltCommentsClauses(['join' => '', 'where' => ''], false, ['required_operation' => 'edit']);

        $totals = (array)$wpdb->get_results("
            SELECT comment_approved, COUNT( * ) AS total
            FROM {$wpdb->comments} 
            {$clauses['join']} 
            WHERE {$clauses['where']} $where
            GROUP BY comment_approved
        ", ARRAY_A);

        $comment_count = [
            'approved' => 0,
            'awaiting_moderation' => 0,
            'spam' => 0,
            'trash' => 0,
            'post-trashed' => 0,
            'total_comments' => 0,
            'all' => 0,
        ];

        foreach ($totals as $row) {
            switch ($row['comment_approved']) {
                case 'trash':
                    $comment_count['trash'] = $row['total'];
                    break;
                case 'post-trashed':
                    $comment_count['post-trashed'] = $row['total'];
                    break;
                case 'spam':
                    $comment_count['spam'] = $row['total'];
                    $comment_count['total_comments'] += $row['total'];
                    break;
                case '1':
                    $comment_count['approved'] = $row['total'];
                    $comment_count['total_comments'] += $row['total'];
                    $comment_count['all'] += $row['total'];
                    break;
                case '0':
                    $comment_count['awaiting_moderation'] = $row['total'];
                    $comment_count['total_comments'] += $row['total'];
                    $comment_count['all'] += $row['total'];
                    break;
                default:
                    break;
            }
        }

        $comment_count['moderated'] = $comment_count['awaiting_moderation'];
        unset($comment_count['awaiting_moderation']);
        $comment_count = (object)$comment_count;

        return $comment_count;
    }
}
