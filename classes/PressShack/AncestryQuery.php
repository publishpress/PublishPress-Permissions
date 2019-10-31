<?php

namespace PressShack;

class AncestryQuery
{
    public static function queryDescendantIDs($source_name, $parent_id, $args = [])
    {
        global $wpdb;

        $defaults = ['include_revisions' => false, 'include_attachments' => true, 'post_status' => false, 'append_clause' => '', 'post_types' => []];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $descendant_ids = [];
        $clauses = $append_clause;

        if (!$parent_id) {
            return [];
        }

        switch ($source_name) {
            case 'post':
                if ($post_types) {
                    $clauses .= " AND post_type IN ('" . implode("','", (array)$post_types) . "')";
                }

                $skip_types = [];

                if (!$include_revisions)
                    $skip_types[] = 'revision';

                if (!$include_attachments)
                    $skip_types[] = 'attachment';

                $clauses .= ($skip_types) ? " AND post_type NOT IN ('" . implode("','", $skip_types) . "')" : '';

                $clauses .= (is_array($post_status)) ? " AND post_status IN ('" . implode("','", $post_status) . "')" : '';

                $table = $wpdb->posts;
                $col_id = 'ID';
                $col_parent = 'post_parent';
                break;

            case 'term':
                $table = $wpdb->term_taxonomy;
                $col_id = 'term_id';
                $col_parent = 'parent';
                break;

            default:
                return [];
        }

        return self::doQueryDescendantIDs($table, $col_id, $col_parent, $parent_id, $clauses, $descendant_ids);
    }

    // recursive function
    private static function doQueryDescendantIDs($table_name, $col_id, $col_parent, $parent_id, $type_clause, $descendant_ids = [])
    {
        global $wpdb;

        $descendant_ids = [];

        $parent_id = (int)$parent_id;
        if ($results = $wpdb->get_col("SELECT $col_id FROM $table_name WHERE $col_parent = '$parent_id' $type_clause")) {
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
