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

        require_once(PRESSPERMIT_IMPORT_CLASSPATH . '/UI/SettingsTabImportNotes.php');
    }

    function fltOptionTabs($tabs)
    {
        $tabs['import'] = esc_html__('Import', 'press-permit-core');
        return $tabs;
    }

    function actOptionsSections($options)
    {
        $options['import'] = ['rs_import' => ['import_placeholder']];
        return $options;
    }

    function actOptionsPreUI()
    {
        if (!PWP::empty_POST('pp_rs_import') && did_action('presspermit_importing')) {
            $rs_import = Import\DB\RoleScoper::instance();

            ?>
            <table class="form-table pp-form-table pp-options-table">
                <tr>
                    <td>
                        <div class='rsu-issue'>
                        <h4><?php esc_html_e('Role Scoper Import Results:', 'press-permit-core'); ?></h4>
                        <ul>
                        <?php

                        if ($rs_import->timed_out && array_diff($rs_import->num_imported, ['0'])) :
                            ?>
                            <li class="pp-warning"><?php esc_html_e('Import completed partially, but reached time limit. Please run again.'); ?></li>
                        <?php
                        endif;

                        if (!empty($rs_import->return_error) && (defined('PRESSPERMIT_DEBUG') || defined('WP_DEBUG'))) :
                            ?>
                            <li class="pp-warning"><?php echo esc_html($rs_import->return_error); ?></li>
                        <?php
                        endif;

                        if ($rs_import->sites_examined) :
                            ?>
                            <li><?php printf(esc_html(_n('1 site examined:', '%1$s sites examined:', (int) $rs_import->sites_examined, 'press-permit-core')), (int) $rs_import->sites_examined); ?></li>
                        <?php
                        endif;

                        if (!array_diff($rs_import->num_imported, ['0'])) :
                            ?>
                            <li class="pp-warning"><?php esc_html_e('Nothing to import!', 'press-permit-core'); ?></li>
                        <?php else :
                            foreach ($rs_import->num_imported as $import_type => $num) :
                                if (!$num) continue;
                                ?>
                                <li class="pp-success"><?php printf(esc_html__('%1$s imported: %2$s', 'press-permit-core'), esc_html($rs_import->import_types[$import_type]), (int) $num); ?></li>
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

        if (!PWP::empty_POST('pp_undo_imports')) {
            ?>
            <table class="form-table pp-form-table pp-options-table">
                <tr>
                    <td>
                        <h4 class="pp-success"><?php esc_html_e('Previous import values have been deleted', 'press-permit-core'); ?></h4>
                    </td>
                </tr>
            </table>
            <?php
        }
    }

    function actOptionsUI()
    {
        global $wpdb;

        $ui = SettingsAdmin::instance(); 
        $tab = 'import';

        echo '<tr><td>';

        if ($offer_rs = $this->hasUnimported('rs')) :
            wp_nonce_field('pp-rs-import', '_pp_import_nonce');
            ?>
            <h3>
                <?php esc_html_e('Role Scoper Import', 'press-permit-core'); ?>
            </h3>

            <p>
                <?php esc_html_e('Migrates Role Scoper Options, Role Groups, Roles and Restrictions to PublishPress Permissions.', 'press-permit-core'); ?>
            </p>

            <br />

            <input name="pp_rs_import" type="submit" value="Do Import"/>

            <?php
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($count = $wpdb->get_var("SELECT COUNT(i.ID) FROM $wpdb->ppi_imported AS i INNER JOIN $wpdb->ppi_runs AS r ON i.run_id = r.ID AND r.import_type = 'rs'")) :
                ?>
                <span class='prev-imports'>
                <?php printf(esc_html(_n(' (%s configuration item previously imported)', ' (%s configuration items previously imported)', (int) $count)), (int) $count); ?>
            </span>
            <?php
            endif;
            ?>
            
            <br /><br />
            <div class='rsu-issue rsu-notes'>
            <?php esc_html_e('Notes:', 'press-permit-core'); ?>

            <?php 
            SettingsTabImportNotes::displayNotes();
            ?>
            </div>
        <?php
        endif;

        if (empty($offer_rs) && empty($offer_pp) && PWP::empty_POST('pp_rs_import') && PWP::empty_POST('pp_pp_import')) : ?>
            <p>
                <?php esc_html_e('Nothing to import!', 'press-permit-core'); ?>
            </p>
        <?php
        endif;

        if (presspermit()->getOption('display_hints')) :
            ?>
            <div class="pp-hint pp-optionhint">
                <?php
                SettingsAdmin::echoStr('pp-import-disable');
                ?>
            </div>
        <?php
        endif;
        ?>

        </td>
        </tr>

        <?php
        if (is_multisite()) {
            $site_clause = (is_main_site()) ? "AND site > 0" : $wpdb->prepare("AND site = %d", get_current_blog_id());  // if on main site, will undo import for all sites
        } else {
            $site_clause = '';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_col(
                "SELECT run_id FROM $wpdb->ppi_imported WHERE run_id > 0 $site_clause"  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ) 
        ) : ?>
            <tr>
                <td>

                    <?php
                    $msg = esc_html__("All imported groups, roles, permissions and options will be deleted. Are you sure?", 'press-permit-core');
                    ?>
                    <div style="float:right">
                        <input name="pp_undo_imports" type="submit" value="<?php esc_attr_e('Undo All Imports', 'press-permit-core'); ?>"
                               onclick="<?php echo "javascript:if (confirm('" . esc_attr($msg) . "')) {return true;} else {return false;}"; ?>"/>
                    </div>
                </td>
            </tr>
        <?php
        endif;
    }

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
