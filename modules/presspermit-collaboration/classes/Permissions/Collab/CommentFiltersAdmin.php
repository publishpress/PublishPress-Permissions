<?php
namespace PublishPress\Permissions\Collab;

class CommentFiltersAdmin
{
    function __construct() {
        add_filter('the_comments', [$this, 'fltLogCommentPostIds']);

        add_filter('wp_count_comments', [$this, 'fltWpCountCommentsOverride'], 99, 2);
        add_filter('map_meta_cap', [$this, 'fltAdjustReqdCaps'], 1, 4);        
    }

    public function fltLogCommentPostIds($comments)
    {
        // buffer the listed IDs for more efficient user_has_cap calls
        $pp = presspermit();
        if (empty($pp->listed_ids)) {
            $pp->listed_ids = [];

            foreach ($comments as $row) {
                if (!empty($row->comment_post_ID)) {
                    $post_type = get_post_field('post_type', $row->comment_post_ID);
                    $pp->listed_ids[$post_type][$row->comment_post_ID] = true;
                }
            }
        }

        return $comments;
    }

    public function fltAdjustReqdCaps($reqd_caps, $orig_cap, $user_id, $args)
    {
        global $pagenow;

        // users lacking edit_posts cap may have moderate_comments capability via a supplemental role
        if (('edit-comments.php' == $pagenow) && ($key = array_search('edit_posts', $reqd_caps))) {
            global $current_user;
            if (did_action('load-edit-comments.php') && ($user_id == $current_user->ID) && empty($current_user->allcaps['edit_posts'])) {
                $reqd_caps[$key] = 'moderate_comments';
            }
        }

        return $reqd_caps;
    }

    // force wp_count_comments() through WP_Comment_Query filtering
    public function fltWpCountCommentsOverride($comments, $post_id = 0)
    {
        global $pagenow, $wpdb;

        if (!current_user_can('moderate_comments') || defined('PRESSPERMIT_FORCE_COMMENT_COUNT_FILTER')) {
            return $comments;
        }

        if (!empty($pagenow) && ('post-new.php' == $pagenow)) {
            return $comments;
        }

        if (!$post_id) {
            $post_id = ('edit.php' == $pagenow) ? 0 : PWP::getPostID();
        }

        $qry_obj = new \WP_Comment_Query(['post_id' => $post_id]);

        $clauses = array_fill_keys( ['fields', 'join', 'orderby', 'limits', 'groupby'], '' );
        $clauses['where'] = "comment_approved = '1'";

        /**
		 * Filters the comment query clauses.
		 *
		 * @since 3.1.0
		 *
		 * @param string[]         $pieces An associative array of comment query clauses.
		 * @param WP_Comment_Query $this   Current instance of WP_Comment_Query (passed by reference).
		 */

		$clauses = apply_filters_ref_array( 'comments_clauses', array( $clauses, $qry_obj) );

		$join    = isset( $clauses['join'] ) ? $clauses['join'] : '';
		$where   = isset( $clauses['where'] ) ? $clauses['where'] : '';

		if ( $where ) {
			$where = 'WHERE ' . $where;
		}

		$request = "SELECT comment_approved, COUNT( * ) AS num_comments FROM $wpdb->comments $join $where GROUP BY comment_approved";

        $count = $wpdb->get_results($request);

        // remainder of this function ported from WP function wp_count_comments()

        $total = 0;
        $approved = ['0' => 'moderated', '1' => 'approved', 'spam' => 'spam', 'trash' => 'trash', 'post-trashed' => 'post-trashed'];
        foreach ((array)$count as $row) {
            $row = (array)$row;  // PP modification

            // Don't count post-trashed toward totals
            if (!empty($row['num_comments'])) {
                if ('post-trashed' != $row['comment_approved'] && 'trash' != $row['comment_approved'])
                    $total += $row['num_comments'];

                if (isset($approved[$row['comment_approved']]))
                    $stats[$approved[$row['comment_approved']]] = $row['num_comments'];
            }
        }

        $stats['total_comments'] = $total;
        $stats['all'] = $total;
        foreach ($approved as $key) {
            if (empty($stats[$key]))
                $stats[$key] = 0;
        }

        $stats = (object)$stats;

        return $stats;
    }
}
