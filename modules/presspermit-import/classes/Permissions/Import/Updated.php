<?php
namespace PublishPress\Permissions\Import;

class Updated
{
    function __construct($prev_version)
    {
        // single-pass do loop to easily skip unnecessary version checks
        do {
            if (version_compare($prev_version, '2.1.1', '<')) {
                global $wpdb;

                if ($wpdb->get_results("SHOW TABLES LIKE '$wpdb->pp_groups'")) {
                    if ($misnamed_anon_group_id = $wpdb->get_var("SELECT ID FROM $wpdb->pp_groups WHERE group_name = '[Anonymous]'")) {
                        if ($wpdb->get_results("SHOW TABLES LIKE '$wpdb->ppc_roles'")) {
                            if ($proper_anon_group_id = $wpdb->get_var("SELECT ID FROM $wpdb->pp_groups WHERE group_name = '" . '{Anonymous}' . "'")) {
                                // properly named anonymous group was already created by PP
                                $wpdb->update($wpdb->ppc_roles, ['agent_id' => $proper_anon_group_id], ['agent_type' => 'pp_group', 'agent_id' => $misnamed_anon_group_id]);
                                $wpdb->update($wpdb->ppc_exceptions, ['agent_id' => $proper_anon_group_id], ['agent_type' => 'pp_group', 'agent_id' => $misnamed_anon_group_id]);
                                $wpdb->delete($wpdb->pp_groups, ['ID' => $misnamed_anon_group_id]);
                            } else {
                                // properly named anonymous group does not exist, so just rename
                                $wpdb->update($wpdb->pp_groups, ['agent_id' => $proper_anon_group_id], ['agent_type' => 'pp_group', 'agent_id' => $misnamed_anon_group_id]);
                                $wpdb->update($wpdb->pp_groups, ['group_name' => '{Anonymous}'], ['ID' => $misnamed_anon_group_id]);
                            }
                        }
                    }
                }
            } else break;

            if (version_compare($prev_version, '2.0.1-beta', '<')) {
                require_once(PRESSPERMIT_IMPORT_CLASSPATH . '/Importer.php');
                $importer = new Importer();
                $importer->updateTablecodes();

            } else break;

        } while (0); // end single-pass version check loop
    }
}
