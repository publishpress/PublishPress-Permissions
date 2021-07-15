<?php

namespace PublishPress\Permissions;

class TermQuery
{
    // derived from WP _pad_term_counts(), but includes private posts in counts based on current user's access 
    public static function tallyTermCounts(&$terms, $taxonomy, $args = [])
    {
        global $wpdb;

        if (!$terms) {
            return;
        }

        $defaults = ['pad_counts' => true, 'post_type' => '', 'required_operation' => ''];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $term_items = [];

        if ($terms) {
            if (!is_object(reset($terms))) {
                return $terms;
            }

            foreach ((array)$terms as $key => $term) {
                $terms_by_id[$term->term_id] = &$terms[$key];
                $term_ids[$term->term_taxonomy_id] = $term->term_id;
            }
        }

        // Get the object and term ids and stick them in a lookup table
        $tax_obj = get_taxonomy($taxonomy);

        $object_types = ($post_type) ? (array)$post_type : (array)esc_sql($tax_obj->object_type);

        if ( class_exists('\PublishPress\Permissions\PostFilters') && ! presspermit()->isUserUnfiltered() ) {
            // will default to src_table $wpdb->posts
            $join = \PublishPress\Permissions\PostFilters::instance()->fltPostsJoin('', []);

            // need to apply term restrictions in case post is restricted by another taxonomy
            $type_status_clause = \PublishPress\Permissions\PostFilters::instance()->getPostsWhere(
                ['post_types' => $object_types, 'required_operation' => $required_operation, 'join' => $join]
            );
        } else {
            $join = '';
            $stati = get_post_stati(['public' => true, 'private' => true], 'names', 'or');
            $status_clause = ($stati) ? "AND post_status IN ('" . implode("', '", $stati) . "')" : '';
            $type_status_clause = "AND post_type IN ('" . implode("', '", $object_types) . "') $status_clause";
        }

        if (!$required_operation) {
            $required_operation = (PWP::isFront() && !presspermit_is_preview()) ? 'read' : 'edit';
        }

        $qry = "SELECT tr.object_id, tr.term_taxonomy_id FROM $wpdb->term_relationships AS tr"
        . " INNER JOIN $wpdb->posts ON object_id = $wpdb->posts.ID $join"
        . " WHERE tr.term_taxonomy_id IN ('" . implode("','", array_keys($term_ids)) . "') $type_status_clause";

        static $cache_results;

        if (!isset($cache_results)) {
            $cache_results = [];
        }

        if (isset($cache_results[$qry])) {
            $results = $cache_results[$qry];
        } else {
            $results = $wpdb->get_results(
                "SELECT tr.object_id, tr.term_taxonomy_id FROM $wpdb->term_relationships AS tr"
                . " INNER JOIN $wpdb->posts ON object_id = $wpdb->posts.ID $join"
                . " WHERE tr.term_taxonomy_id IN ('" . implode("','", array_keys($term_ids)) . "') $type_status_clause"
            );

            $cache_results[$qry] = $results;
        }

        foreach ($results as $row) {
            $id = $term_ids[$row->term_taxonomy_id];
            $term_items[$id][$row->object_id] = isset($term_items[$id][$row->object_id]) ? ++$term_items[$id][$row->object_id] : 1;
        }

        // Touch every ancestor's lookup row for each post in each term
        foreach ($term_ids as $term_id) {
            $child = $term_id;
            while (!empty($terms_by_id[$child]) && $parent = $terms_by_id[$child]->parent) {
                if (!empty($term_items[$term_id])) {
                    foreach ($term_items[$term_id] as $item_id => $touches) {
                        $term_items[$parent][$item_id] = isset($term_items[$parent][$item_id]) ? ++$term_items[$parent][$item_id] : 1;
                    }
                }

                $child = $parent;
            }
        }

        foreach (array_keys($terms_by_id) as $key) {
            $terms_by_id[$key]->count = 0;
        }

        // Transfer the touched cells
        foreach ((array)$term_items as $id => $items) {
            if (isset($terms_by_id[$id])) {
                $terms_by_id[$id]->count = count($items);
            }
        }
    }
}
