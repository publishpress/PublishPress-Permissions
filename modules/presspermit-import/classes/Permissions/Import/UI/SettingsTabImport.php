<?php
namespace PublishPress\Permissions\Import\UI;

use \PublishPress\Permissions\Import as Import;
use \PublishPress\Permissions\UI\SettingsAdmin as SettingsAdmin;

class SettingsTabImport
{
    var $enabled;

    function __construct()
    {
        add_filter('presspermit_option_tabs', [$this, 'fltOptionTabs'], 1);

        add_action('presspermit_option_sections', [$this, 'actOptionsSections']);

        add_action('presspermit_import_options_pre_ui', [$this, 'actOptionsPreUI']);
        add_action('presspermit_import_options_ui', [$this, 'actOptionsUI']);
    }

    function fltOptionTabs($tabs)
    {
        $tabs['import'] = __('Import', 'press-permit-core');
        return $tabs;
    }

    function actOptionsSections($options)
    {
        $options['import'] = ['rs_import' => ['import_placeholder']];
        return $options;
    }

    function actOptionsPreUI()
    {
        if (!empty($_POST['pp_rs_import']) && did_action('presspermit_importing')) {
            $rs_import = Import\DB\RoleScoper::instance();

            ?>
            <table class="form-table pp-form-table pp-options-table">
                <tr>
                    <td>
                        <div class='rsu-issue'>
                        <h4><?php _e('Role Scoper Import Results:', 'press-permit-core'); ?></h4>
                        <ul>
                        <?php

                        if ($rs_import->timed_out && array_diff($rs_import->num_imported, ['0'])) :
                            ?>
                            <li class="pp-warning"><?php _e('Import completed partially, but reached time limit. Please run again.'); ?></li>
                        <?php
                        endif;

                        if (!empty($rs_import->return_error) && (defined('PRESSPERMIT_DEBUG') || defined('WP_DEBUG'))) :
                            ?>
                            <li class="pp-warning"><?php echo $rs_import->return_error; ?></li>
                        <?php
                        endif;

                        if ($rs_import->sites_examined) :
                            ?>
                            <li><?php printf(_n('1 site examined:', '%1$s sites examined:', $rs_import->sites_examined, 'press-permit-core'), $rs_import->sites_examined); ?></li>
                        <?php
                        endif;

                        if (!array_diff($rs_import->num_imported, ['0'])) :
                            ?>
                            <li class="pp-warning"><?php _e('Nothing to import!', 'press-permit-core'); ?></li>
                        <?php else :
                            foreach ($rs_import->num_imported as $import_type => $num) :
                                if (!$num) continue;
                                ?>
                                <li class="pp-success"><?php printf(__('%1$s imported: %2$s', 'press-permit-core'), $rs_import->import_types[$import_type], $num); ?></li>
                            <?php
                            endforeach;
                        endif;
                        ?>
                        </ul>
                        </div>
                    </td>
                </tr>
            </table>
            <?php
        }

        if (!empty($_POST['pp_undo_imports'])) {
            ?>
            <table class="form-table pp-form-table pp-options-table">
                <tr>
                    <td>
                        <h4 class="pp-success"><?php _e('Previous import values have been deleted', 'press-permit-core'); ?></h4>
                    </td>
                </tr>
            </table>
            <?php
        }
    }

    function actOptionsUI()
    {
        $ui = SettingsAdmin::instance(); 
        $tab = 'import';

        echo '<tr><td>';

        if ($offer_rs = $this->hasUnimported('rs')) :
            ?>
            <h3>
                <?php _e('Role Scoper Import', 'press-permit-core'); ?>
            </h3>

            <p>
                <?php _e('Migrates Role Scoper Options, Role Groups, Roles and Restrictions to PublishPress Permissions.', 'press-permit-core'); ?>
            </p>

            <br />

            <input name="pp_rs_import" type="submit" value="Do Import"/>

            <?php
            global $wpdb;
            if ($count = $wpdb->get_var("SELECT COUNT(i.ID) FROM $wpdb->ppi_imported AS i INNER JOIN $wpdb->ppi_runs AS r ON i.run_id = r.ID AND r.import_type = 'rs'")) :
                ?>
                <span class='prev-imports'>
                <?php printf(_n(' (%s configuration item previously imported)', ' (%s configuration items previously imported)', $count), $count); ?>
            </span>
            <?php
            endif;
            ?>
            
            <br /><br />
            <div class='rsu-issue rsu-notes'>
            <?php _e('<strong>Notes:</strong>', 'press-permit-core'); ?>

            <ul class="pp-notes">
                <li><?php _e('The import can be run multiple times if source values change.', 'press-permit-core'); ?></li>
                <li><?php _e('Configuration items will be imported even if the request exceeds PHP execution time limit. Repeat as necessary until all items are imported.', 'press-permit-core'); ?></li>
                <li><?php _e('Current Role Scoper configuration is not modified or deleted. You will still be able to restore previous behavior by reactivating Role Scoper if necessary.', 'press-permit-core'); ?></li>
                <li><?php _e('Following import, you should manually review the results and confirm that permissions are correct. Some manual followup may be required.', 'press-permit-core'); ?></li>
                <li><?php _e('If your Role Scoper configuration has Category Restrictions on the Author or Editor role, specific Publish Permissions will be enabled to control publishing permissions separate from editing permissions. Existing specific Edit Permissions will be mirrored as specific Publish Permissions to maintain previous access.', 'press-permit-core'); ?></li>
                <li><?php _e('Category Restrictions on the Editor role are converted to specific Edit, Publish and Term Assignment Permissions. If a Post Editor should be blocked from editing other&apos;s posts within a specified category but still be able to submit / edit / publish their own posts in that category, they will need to be switched to a WordPress role that does not have the "edit_others_pages" capability. Then specific editing permissions can be granted per-category.', 'press-permit-core'); ?></li>
            </ul>
            </div>
        <?php
        endif;

        if (!$offer_rs && !$offer_pp && empty($_POST['pp_rs_import']) && empty($_POST['pp_pp_import'])) : ?>
            <p>
                <?php _e('Nothing to import!', 'press-permit-core'); ?>
            </p>
        <?php
        endif;

        if (presspermit()->getOption('display_hints')) :
            ?>
            <div class="pp-hint pp-optionhint">
                <?php
                echo SettingsAdmin::getStr('pp-import-disable');
                ?>
            </div>
        <?php
        endif;
        ?>

        </td>
        </tr>

        <?php
        global $wpdb;

        if (is_multisite()) {
            $site_clause = (is_main_site()) ? "AND site > 0" : "AND site = %d";  // if on main site, will undo import for all sites
        } else {
            $site_clause = '';
        }

        if ($wpdb->get_col(
                $wpdb->prepare(
                    "SELECT run_id FROM $wpdb->ppi_imported WHERE run_id > 0 $site_clause",
                    get_current_blog_id()
                )
            ) 
        ) : ?>
            <tr>
                <td>

                    <?php
                    $msg = __("All imported groups, roles, exceptions and options will be deleted. Are you sure?", 'press-permit-core');
                    $js_call = "javascript:if (confirm('$msg')) {return true;} else {return false;}";
                    ?>
                    <div style="float:right">
                        <input name="pp_undo_imports" type="submit" value="<?php _e('Undo All Imports', 'press-permit-core'); ?>"
                               onclick="<?php echo $js_call; ?>"/>
                    </div>
                </td>
            </tr>
        <?php
        endif;
    } // end function optionsUI()

    private function hasInstallation($install_code)
    {
        require_once(PRESSPERMIT_IMPORT_CLASSPATH . '/DB/SourceConfig.php');
        $config = new Import\DB\SourceConfig();
        return $config->hasInstallation($install_code);
    }

    private function hasUnimported($install_code)
    {
        require_once(PRESSPERMIT_IMPORT_CLASSPATH . '/DB/SourceConfig.php');
        $config = new Import\DB\SourceConfig();
        return $config->hasUnimported($install_code);
    }
}
