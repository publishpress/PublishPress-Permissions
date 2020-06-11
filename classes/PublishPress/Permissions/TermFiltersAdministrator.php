<?php

namespace PublishPress\Permissions;

class TermFiltersAdministrator
{
    public function __construct() {
        add_filter('get_terms', [$this, 'fltPadTermCounts'], 10, 3);
    }

    public function fltPadTermCounts($terms, $taxonomies, $args)
    {
        if (!defined('XMLRPC_REQUEST') && ('all' == $args['fields']) && empty($args['pp_no_filter'])) {
            global $pagenow;
            if (!is_admin() || !in_array($pagenow, ['post.php', 'post-new.php'])) {
                require_once(PRESSPERMIT_CLASSPATH . '/TermQuery.php');

                // pp_tallyTermCounts() is PP equivalent to WP _pad_term_counts()
                $args['required_operation'] = (PWP::isFront() && !presspermit_is_preview()) ? 'read' : 'edit';
                $taxonomies = (array)$taxonomies;  // avoid PHP warning if taxonomies argument is corrupted upstream
                TermQuery::tallyTermCounts($terms, reset($taxonomies), $args);
            }
        }
        return $terms;
    }
}