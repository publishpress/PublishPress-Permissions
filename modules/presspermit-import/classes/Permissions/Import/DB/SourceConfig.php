<?php
namespace PublishPress\Permissions\Import\DB;

class SourceConfig
{
    function __construct() {
        global $wpdb;

        if (!defined('SCOPER_VERSION') || empty($wpdb->groups_rs)) {
            self::setRoleScoperTables();
        }
    }

    public static function setRoleScoperTables()
    {
        global $wpdb;
        $wpdb->user2role2object_rs = $wpdb->prefix . 'user2role2object_rs';
        $wpdb->role_scope_rs = $wpdb->prefix . 'role_scope_rs';

        $prefix = $wpdb->prefix; // (! empty($wpdb->base_prefix) && $sitewide_groups) ? $wpdb->base_prefix : $wpdb->prefix;

        $wpdb->groups_basename = 'groups_rs';
        $wpdb->groups_rs = $prefix . $wpdb->groups_basename;

        $wpdb->user2group_rs = $prefix . 'user2group_rs';

        $wpdb->groups_id_col = 'ID';
        $wpdb->groups_name_col = 'group_name';
        $wpdb->groups_descript_col = 'group_description';
        $wpdb->groups_homepage_col = 'group_homepage';
        $wpdb->groups_meta_id_col = 'group_meta_id';

        $wpdb->user2group_gid_col = 'group_id';
        $wpdb->user2group_uid_col = 'user_id';
        $wpdb->user2group_assigner_id_col = 'assigner_id';
        $wpdb->user2group_status_col = 'status';
    }

    private function hasTable($table_name)
    {
        global $wpdb;

        $results = (array)$wpdb->get_results("SHOW TABLES LIKE '$table_name'");
        return (bool)reset($results);
    }

    function hasInstallation($install_code)
    {
        global $wpdb;

        switch ($install_code) {
            case 'rs' :
                return $this->hasTable($wpdb->user2role2object_rs);
                break;
        }

        return false;
    }

    function hasUnimported($install_code) {
        global $wpdb;

        if (!$this->hasInstallation($install_code)) {
            if (!is_multisite() || !is_main_site()) {
                return false;
            }
        }

        switch ($install_code) {
            case 'rs' :
                if (!$this->hasTable($wpdb->role_scope_rs)) {
                    if (!is_multisite() || !is_main_site()) {
                        return false;
                    }
                }

                require_once(PRESSPERMIT_IMPORT_CLASSPATH . '/DB/RoleScoper.php');
                $importer = RoleScoper::instance();

                if (is_multisite()) {
                    if (is_main_site()) {
                        return true;
					}

                    $groups = [];  // will deal with netwide groups in import function
                } else {
                    $groups = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->groups_rs WHERE ID NOT IN ( SELECT source_id FROM $wpdb->ppi_imported WHERE run_id > 0 AND source_tbl = %d )", $importer->getTableCode($wpdb->groups_rs)));
                }

                $restrictions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->role_scope_rs WHERE role_type = 'rs' AND ( topic = 'term' OR ( topic = 'object' AND src_or_tx_name = 'post' ) ) AND max_scope = topic AND requirement_id NOT IN ( SELECT source_id FROM $wpdb->ppi_imported WHERE run_id > 0 AND source_tbl = %d )", $importer->getTableCode($wpdb->role_scope_rs)));
                $item_roles = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->user2role2object_rs WHERE role_type = 'rs' AND scope IN ( 'term', 'object' ) AND date_limited = '0' AND content_date_limited = '0' AND assignment_id NOT IN ( SELECT source_id FROM $wpdb->ppi_imported WHERE run_id > 0 AND source_tbl = %d )", $importer->getTableCode($wpdb->user2role2object_rs)));
                $site_roles = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->user2role2object_rs WHERE role_type = 'rs' AND scope = 'blog' AND date_limited = '0' AND content_date_limited = '0' AND assignment_id NOT IN ( SELECT source_id FROM $wpdb->ppi_imported WHERE run_id > 0 AND source_tbl = %d )", $importer->getTableCode($wpdb->user2role2object_rs)));

                if (!empty($_REQUEST['show_unimported'])) {
                    pp_debug_echo('groups:');
                    pp_dump($groups);
                    pp_debug_echo('<br />site roles:');
                    pp_dump($site_roles);
                    pp_debug_echo('<br />restrictions:');
                    pp_dump($restrictions);
                    pp_debug_echo('<br />item roles:');
                    pp_dump($item_roles);
                }

                return $groups || $restrictions || $item_roles || $site_roles;
                break;
        }
    }
}
