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
    }

    function actAdminInit()
    {
        if (!empty($_POST['pp_rs_import']) || !empty($_POST['pp_pp_import']) || !empty($_POST['pp_undo_imports'])) {
            require_once(PRESSPERMIT_IMPORT_CLASSPATH . '/Importer.php');
            Import\Importer::handleSubmission();
        }
    }

    function fltDefaultTab($default_tab)
    {
        if (isset($_POST['pp_rs_import']) || isset($_POST['pp_pp_import']) || isset($_POST['pp_undo_imports'])) {
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

        $ver = get_option('ppi_version');

        if (empty($ver['db_version']) || version_compare(PRESSPERMIT_IMPORT_DB_VERSION, $ver['db_version'], '!=')) {
            $ver_change = true;

            // set_current_user may have triggered DB setup already
            require_once(PRESSPERMIT_IMPORT_CLASSPATH . '/DB/DatabaseSetup.php');
            new Import\DB\DatabaseSetup($ver['db_version']);
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
