<?php
namespace PublishPress\Permissions;

class ImportHooksAdmin
{
    function __construct()
    {
        require_once(PRESSPERMIT_IMPORT_ABSPATH . '/db-config.php');

        add_action('presspermit_pre_init', [$this, 'actInit']);

        add_action('init', [$this, 'actAdminInit'], 90);
        add_filter('presspermit_options_default_tab', [$this, 'fltDefaultTab']);
        add_action('presspermit_options_ui', [$this, 'actOptionsUI']);

        add_filter('presspermit_import_count', [$this, 'fltImportCount'], 10, 2);
    }

    function fltImportCount($count, $type = 'rs') {
        global $wpdb;

        if ('rs' == $type) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $count = $wpdb->get_var(
                "SELECT COUNT(i.ID) FROM $wpdb->ppi_imported AS i INNER JOIN $wpdb->ppi_runs AS r ON i.run_id = r.ID AND r.import_type = 'rs'"
            );
        }

        return $count;
    }

    function actAdminInit()
    {
        if (!PWP::empty_POST('pp_rs_import') || !PWP::empty_POST('pp_pp_import') || !PWP::empty_POST('pp_undo_imports')) {
            check_admin_referer('pp-rs-import', '_pp_import_nonce');

            require_once(PRESSPERMIT_IMPORT_CLASSPATH . '/Importer.php');
            Import\Importer::handleSubmission(); // action-specific nonce checks
        }
    }

    function fltDefaultTab($default_tab)
    {
        if (PWP::is_POST('pp_rs_import') || PWP::is_POST('pp_pp_import') || PWP::is_POST('pp_undo_imports')) {
            $default_tab = 'import';
        }

        return $default_tab;
    }

    function actOptionsUI() {
        require_once(PRESSPERMIT_IMPORT_CLASSPATH . '/UI/SettingsTabImport.php');
        new Import\UI\SettingsTabImport();
    }

    function actInit()
    {
        $this->versionCheck();
    }

    private function versionCheck()
    {
        $ver_change = false;

        $pp_ver = get_option('presspermit_version');
        $ver = get_option('ppi_version');

        if (!is_array($ver) || empty($ver['db_version']) || version_compare(PRESSPERMIT_IMPORT_DB_VERSION, $ver['db_version'], '!=')
        || ($pp_ver && version_compare($pp_ver['version'], '3.2.3', '<'))
        ) {
            $ver_change = true;

            // set_current_user may have triggered DB setup already
            require_once(PRESSPERMIT_IMPORT_CLASSPATH . '/DB/DatabaseSetup.php');

            $db_ver = (is_array($ver) && isset($ver['db_version'])) ? $ver['db_version'] : ''; 
            new Import\DB\DatabaseSetup($db_ver);
        }

        // These maintenance operations only apply when a previous version of PP was installed 
        if (!empty($ver['version'])) {
            if (version_compare(PRESSPERMIT_IMPORT_VERSION, $ver['version'], '!=')) {
                $ver_change = true;

                require_once(PRESSPERMIT_IMPORT_CLASSPATH . '/Updated.php');
                new Import\Updated($ver['version']);
            }
        } //else {
        // first-time install (or previous install was totally wiped)
        //}

        if ($ver_change) {
            $ver = [
                'version' => PRESSPERMIT_IMPORT_VERSION,
                'db_version' => PRESSPERMIT_IMPORT_DB_VERSION
            ];

            update_option('ppi_version', $ver);
        }
    }
}
