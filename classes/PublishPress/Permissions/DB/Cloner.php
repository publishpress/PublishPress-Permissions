<?php

namespace PublishPress\Permissions\DB;

class Cloner
{
    public static function clonePermissions($agent_type, $agent_id, $source_agent)
    {
        global $wpdb, $current_user;

        if ('pp_group' != $agent_type)
            return false;

        $current_user_id = $current_user->ID;

        $agent_id = (int)$agent_id;
        $agent = presspermit()->groups()->getGroup($agent_id);

        if (is_string($source_agent)) {
            $source_agent = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $wpdb->pp_groups WHERE metagroup_type = 'wp_role' AND metagroup_id = %s",
                $source_agent
            ));
        } else {
            $source_agent = presspermit()->groups()->getGroup($source_agent);
        }

        if (!$source_agent || ($source_agent->metagroup_type != 'wp_role'))
            return false;

        $source_agent_id = $source_agent->ID;

        // =========== Roles Import =============
        $exc_query = "SELECT role_name FROM $wpdb->ppc_roles WHERE agent_type = %s AND agent_id = %d";
        $target_roles = $wpdb->get_col($wpdb->prepare($exc_query, $agent_type, $agent_id));

        $source_roles = $wpdb->get_col($wpdb->prepare($exc_query, $agent_type, $source_agent_id));
        foreach ($source_roles as $role_name) {
            $wpdb->insert_id = 0;
            $sql = "INSERT INTO $wpdb->ppc_roles (agent_id, agent_type, role_name, assigner_id) SELECT * FROM"
                . " ( SELECT '$agent_id' AS a, '$agent_type' AS b, '$role_name' AS c, '$current_user_id' AS d ) AS tmp WHERE NOT EXISTS"
                . " (SELECT 1 FROM $wpdb->ppc_roles WHERE agent_type = '$agent_type' AND agent_id = '$agent_id' AND role_name = '$role_name')"
                . " LIMIT 1";

            $wpdb->query($sql);
        }

        // =========== Exceptions Import ===========
        $old_inherited_from = [];
        $log_eitem_ids = [];
        $target_agent_data = compact('agent_type', 'agent_id');

        $exc_query = "SELECT exception_id, for_item_source, for_item_type, for_item_status, operation, mod_type,"
            . " via_item_source, via_item_type FROM $wpdb->ppc_exceptions WHERE agent_type = %s AND agent_id = %d";

        $target_exceptions = $wpdb->get_results($wpdb->prepare($exc_query, $agent_type, $agent_id), OBJECT_K);

        $source_exceptions = $wpdb->get_results($wpdb->prepare($exc_query, $agent_type, $source_agent_id), OBJECT_K);

        $source_eitems = $wpdb->get_results(
            "SELECT eitem_id, exception_id, item_id, assign_for, inherited_from FROM $wpdb->ppc_exception_items"
            . " WHERE exception_id IN ('" . implode("','", array_keys($source_exceptions)) . "')"
        );

        foreach ($source_eitems as $row) {
            $target_exception_data = (array)$source_exceptions[$row->exception_id];
            unset($target_exception_data['exception_id']);
            $target_exception_id = self::get_exception_id($target_exceptions, $target_exception_data, $target_agent_data);

            $wpdb->insert_id = 0;
            $sql = "INSERT INTO $wpdb->ppc_exception_items (assign_for, exception_id, assigner_id, item_id) SELECT * FROM"
                . " ( SELECT '" . trim($row->assign_for) . "' AS a, '$target_exception_id' AS b, '$current_user_id' AS c, '$row->item_id' AS d ) AS tmp"
                . " WHERE NOT EXISTS (SELECT 1 FROM $wpdb->ppc_exception_items WHERE assign_for = '" . trim($row->assign_for) . "'"
                . " AND exception_id = '$target_exception_id' AND item_id = '$row->item_id') LIMIT 1";

            $wpdb->query($sql);
            if ($wpdb->insert_id) {
                $target_eitem_id = (int)$wpdb->insert_id;

                $log_eitem_ids[$row->eitem_id] = $target_eitem_id;

                if ($row->inherited_from) {
                    $old_inherited_from[$target_eitem_id] = $row->inherited_from;
                }
            }
        }

        // convert inherited_from values from source to target exception items
        foreach ($old_inherited_from as $target_eitem_id => $source_inherited_from) {
            if (isset($log_eitem_ids[$source_inherited_from])) {
                $data = ['inherited_from' => $log_eitem_ids[$source_inherited_from]];
                $where = ['eitem_id' => $target_eitem_id];
                $wpdb->update($wpdb->ppc_exception_items, $data, $where);
            }
        }

        return true;
    }

    private static function get_exception_id(&$stored_exceptions, $data, $merge_data)
    {
        $exception_id = 0;

        foreach ($stored_exceptions as $exc) {
            foreach ($data as $key => $val) {
                if ($val != $exc->$key) {
                    continue 2;
                }
            }

            $exception_id = $exc->exception_id;
            break;
        }

        if (!$exception_id) {
            global $wpdb;
            $wpdb->insert($wpdb->ppc_exceptions, array_merge($data, $merge_data));  // agent data must be inserted but did not apply to comparison
            $exception_id = (int)$wpdb->insert_id;
        }

        return $exception_id;
    }
}
