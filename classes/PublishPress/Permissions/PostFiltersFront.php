<?php

namespace PublishPress\Permissions;

class PostFiltersFront
{
    var $archives_where = '';

    public function __construct()
    {
        if (!presspermit()->isContentAdministrator()) {
            require_once(__DIR__ . '/PostFiltersFrontNonAdministrator.php');
            new PostFiltersFrontNonAdministrator();
        }

        add_filter('get_previous_post_where', [$this, 'fltAdjacentPostWhere']);
        add_filter('get_next_post_where', [$this, 'fltAdjacentPostWhere']);

        add_filter('getarchives_where', [$this, 'fltGetarchivesWhere']);

        add_filter('option_sticky_posts', [$this, 'fltStickyPosts']);

        add_filter('shortcode_atts_gallery', [$this, 'fltAttsGallery'], 10, 3);

        if (!empty($_REQUEST['preview'])) {
            add_filter('wp_link_pages_link', [$this, 'fltPagesLink']);
        }

        do_action('presspermit_post_filters_front');
    }

    public function fltAttsGallery($out, $pairs, $atts)
    {
        if (!empty($atts['include'])) // force subsequent get_posts() query to be filtered for PP exceptions
            add_action('pre_get_posts', [$this, 'actGetGalleryPosts']);

        return $out;
    }

    public function actGetGalleryPosts(&$query_obj)
    {
        $query_obj->query_vars['suppress_filters'] = false;
        remove_action('pre_get_posts', [$this, 'actGetGalleryPosts']);
    }

    // custom wrapper to clean up after get_previous_post_where, get_next_post_where nonstandard arg syntax 
    // (uses alias p for post table, passes "WHERE post_type=...)
    public function fltAdjacentPostWhere($where)
    {
        global $wpdb, $current_user;

        $post_type = PWP::findPostType();

        $limit_statuses = array_merge(
            PWP::getPostStatuses(['public' => true, 'post_type' => $post_type]),
            PWP::getPostStatuses(['private' => true, 'post_type' => $post_type])
        );

        if (!empty($current_user->ID)) {
            $where = str_replace(" AND p.post_status = 'publish'", " AND p.post_status IN ('" . implode("','", $limit_statuses) . "')", $where);
        }

        // get_adjacent_post() function includes 'WHERE ' at beginning of $where
        $where = str_replace('WHERE ', 'AND ', $where);

        if ($limit_statuses) {
            $limit_statuses = array_fill_keys($limit_statuses, true);
        }

        $args = ['post_types' => $post_type, 'source_alias' => 'p', 'skip_teaser' => true, 'limit_statuses' => $limit_statuses];

        $where = 'WHERE 1=1 ' . apply_filters('presspermit_posts_where', $where, $args);

        return $where;
    }

    public function fltGetarchivesWhere($where)
    {
        global $current_user, $wpdb;

        // possible @todo: implement in any other PP filters?
        require_once(PRESSPERMIT_CLASSPATH_COMMON . '/SqlTokenizer.php');
        $parser = new \PressShack\SqlTokenizer();
        $post_type = $parser->ParseArg($where, 'post_type');

        $where = str_replace("WHERE ", "WHERE $wpdb->posts.post_date > 0 AND ", $where);

        $stati = array_merge(
            PWP::getPostStatuses(['public' => true, 'post_type' => $post_type]),
            PWP::getPostStatuses(['private' => true, 'post_type' => $post_type])
        );

        if (!empty($current_user->ID)) {
            $where = str_replace("AND post_status = 'publish'", "AND post_status IN ('" . implode("','", $stati) . "')", $where);
        }

        $where = str_replace("WHERE ", "AND ", $where);

        $where = apply_filters('presspermit_posts_where', $where, ['skip_teaser' => true, 'post_type' => $post_type]);

        $where = 'WHERE 1=1 ' . $where;

        $this->archives_where = $where;

        return $where;
    }

    public function fltGetArchivesRequest($request)
    {
        if (0 === strpos($request, "SELECT YEAR(post_date) AS")) {
            $request = str_replace("SELECT YEAR(post_date) AS", "SELECT DISTINCT YEAR(post_date) AS", $request);
        } else {
            global $wpdb;
            $request = str_replace("SELECT * FROM $wpdb->posts", "SELECT DISTINCT * FROM $wpdb->posts", $request);
        }

        remove_filter('query', ['PostFiltersFront', 'fltGetArchivesRequest'], 50);
        return $request;
    }

    public function fltStickyPosts($post_ids)
    {
        if ($post_ids && !presspermit()->isContentAdministrator()) {
            global $wpdb;
            $clauses = array_fill_keys(['distinct', 'join', 'groupby', 'orderby', 'limits'], '');
            $clauses['fields'] = 'ID';
            $clauses['where'] = "AND ID IN ('" . implode("','", $post_ids) . "')";
            $clauses = apply_filters('presspermit_posts_clauses', $clauses);

            $post_ids = $wpdb->get_col(
                "SELECT {$clauses['distinct']} ID FROM $wpdb->posts {$clauses['join']} WHERE 1=1 {$clauses['where']}"
            );
        }

        return $post_ids;
    }

    public function fltPagesLink($pagenum_link)
    {
        $matches = [];

        if (preg_match_all('/href="([^"]*)"/', $pagenum_link, $matches)) {
            $append_arg = (strpos($matches[1][0], '?p=') || strpos($matches[1][0], '&p=')) ? '&preview=true' : '?preview=true';

            // only do magic if the link does not already contain preview directive
            if (!strpos($matches[1][0], 'preview=1') && !strpos($matches[1][0], 'preview=true')) {
                $pagenum_link = str_replace($matches[1][0], $matches[1][0] . $append_arg, $pagenum_link);
            }
        }

        return $pagenum_link;
    }
}
