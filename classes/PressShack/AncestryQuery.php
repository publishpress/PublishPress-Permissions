<?php

namespace PressShack;

class AncestryQuery
{
    public static function queryDescendantIDs($source_name, $parent_id, $args = [])
    {
        $clauses = '';

        if (!$parent_id) {
            return [];
        }

        $args['source_name'] = $source_name;

        return self::doQueryDescendantIDs($table, $col_id, $col_parent, $parent_id, $clauses);
    }

    // recursive function
    private static function doQueryDescendantIDs($table_name, $col_id, $col_parent, $parent_id, $args)
    {
        $defaults = ['source_name' => '', 'include_revisions' => false, 'include_attachments' => true, 'post_status' => false, 'post_types' => []];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        global $wpdb;

        $descendant_ids = [];

        switch ($source_name) {
            case 'post':
                $type_clause = '';

                if ($post_types) {
                    $type_clause .= " AND post_type IN ('" . implode("','", array_map('sanitize_key', (array)$post_types)) . "')";
                }

                if (!$include_revisions) {
                    $type_clause .= $wpdb->prepare(" AND post_type != %s", 'revision');
                }

                if (!$include_attachments) {
                    $type_clause .= $wpdb->prepare(" AND post_type != %s", 'attachment');
                }

                if (is_array($post_status)) {
                    $type_clause .= " AND post_status IN ('" . implode("','", array_map('sanitize_key', $post_status)) . "')";
                }

                $results = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT ID FROM $wpdb->posts WHERE post_parent = %d $type_clause",
                        (int) $parent_id
                    )
                );

                break;

            case 'term':
                $results = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT term_id FROM $wpdb->term_taxonomy WHERE parent = %d",
                        (int) $parent_id
                    )
                );

                break;

            default:
                return [];
        }

        if ($results) {
            foreach ($results as $id) {
                if (!in_array($id, $descendant_ids)) {
                    $descendant_ids[] = $id;
                    $next_generation = self::doQueryDescendantIDs($table_name, $col_id, $col_parent, $id, $type_clause, $descendant_ids);
                    $descendant_ids = array_unique(array_merge($descendant_ids, $next_generation));
                }
            }
        }

        return $descendant_ids;
    }
}
