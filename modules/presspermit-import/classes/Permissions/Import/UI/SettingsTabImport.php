<?php
namespace PublishPress\Permissions\Import\UI;

use \PublishPress\Permissions\Import as Import;

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
        $tabs['import'] = __('Import', 'presspermit');
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
                        <h3><?php _e('Role Scoper Import Results:', 'ppi'); ?></h3>
                        <?php

                        if ($rs_import->timed_out && array_diff($rs_import->num_imported, ['0'])) :
                            ?>
                            <h4 class="pp-warning"><?php _e('Import completed partially, but reached time limit. Please run again.'); ?></h4>
                        <?php
                        endif;

                        if (!empty($rs_import->return_error) && (defined('PRESSPERMIT_DEBUG') || defined('WP_DEBUG'))) :
                            ?>
                            <h4 class="pp-warning"><?php echo $rs_import->return_error; ?></h4>
                        <?php
                        endif;

                        if ($rs_import->sites_examined) :
                            ?>
                            <h4><?php printf(_n('1 site examined:', '%1$s sites examined:', $rs_import->sites_examined, 'ppi'), $rs_import->sites_examined); ?></h4>
                        <?php
                        endif;

                        if (!array_diff($rs_import->num_imported, ['0'])) :
                            ?>
                            <h4 class="pp-warning"><?php _e('Nothing to import!', 'ppi'); ?></h4>
                        <?php else :
                            foreach ($rs_import->num_imported as $import_type => $num) :
                                if (!$num) continue;
                                ?>
                                <h4 class="pp-success"><?php printf(__('%1$s imported: %2$s', 'ppi'), $rs_import->import_types[$import_type], $num); ?></h4>
                            <?php
                            endforeach;
                        endif;
                        ?>

                    </td>
                </tr>
            </table>
            <?php
        }

        if (!empty($_POST['pp_pp_import']) && did_action('presspermit_importing') && presspermit()->isPro()) {
            $pp_import = Import\DB\PressPermitBeta::instance();
            ?>
            <table class="form-table pp-form-table pp-options-table">
                <tr>
                    <td>
                        <h3><?php _e('Permissions Import Results:', 'ppi'); ?></h3>
                        <?php

                        if ($pp_import->timed_out && array_diff($pp_import->num_imported, ['0'])) :
                            ?>
                            <h4 class="pp-warning"><?php _e('Import completed partially, but reached time limit. Please run again.'); ?></h4>
                        <?php
                        endif;

                        if (!empty($pp_import->return_error) && (defined('PRESSPERMIT_DEBUG') || defined('WP_DEBUG'))) :
                            ?>
                            <h4 class="pp-warning"><?php echo $rs_import->return_error; ?></h4>
                        <?php
                        endif;

                        if ($pp_import->sites_examined) :
                            ?>
                            <h4><?php printf(_n('1 site examined:', '%1$s sites examined:', $pp_import->sites_examined, 'ppi'), $pp_import->sites_examined); ?></h4>
                        <?php
                        endif;

                        if (!array_diff($pp_import->num_imported, ['0'])) :
                            ?>
                            <h4 class="pp-warning"><?php _e('Nothing to import!', 'ppi'); ?></h4>
                        <?php else :
                            foreach ($pp_import->num_imported as $import_type => $num) :
                                if (!$num) continue;
                                ?>
                                <h4 class="pp-success"><?php printf(__('%1$s imported: %2$s', 'ppi'), $pp_import->import_types[$import_type], $num); ?></h4>
                            <?php
                            endforeach;
                        endif;
                        ?>
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
                        <h4 class="pp-success"><?php _e('Previous import values have been deleted', 'ppi'); ?></h4>
                    </td>
                </tr>
            </table>
            <?php
        }
    }

    function actOptionsUI()
    {
        $ui = \PublishPress\Permissions\UI\SettingsAdmin::instance(); 
        $tab = 'import';

        echo '<tr><td>';

        if ($offer_rs = $this->hasUnimported('rs')) :
            ?>
            <h3>
                <?php _e('Role Scoper Import', 'presspermit'); ?>
            </h3>

            <p>
                <?php _e('Migrates Role Scoper Options, Role Groups, Roles and Restrictions to PressPermit.', 'ppi'); ?>
            </p>

            <br />

            <input name="pp_rs_import" type="submit" value="Do Import"/>

            <?php
            global $wpdb;
            if ($count = $wpdb->get_var("SELECT COUNT(i.ID) FROM $wpdb->ppi_imported AS i INNER JOIN $wpdb->ppi_runs AS r ON i.run_id = r.ID AND r.import_type = 'rs'")) :
                ?>
                <span>
                <?php printf(_n(' (%s configuration item previously imported)', ' (%s configuration items previously imported)', $count), $count); ?>
            </span>
            <?php
            endif;
            ?>
            
            <br /><br />
            <p>
            <?php _e('<strong>Notes:</strong>', 'ppi'); ?>

            <ul class="pp-notes" style="max-width:500px;margin-left:20px">
                <li><?php _e('The import can be run multiple times if source values change.', 'ppi'); ?></li>
                <li><?php _e('Configuration items will be imported even if the request exceeds PHP execution time limit. Repeat as necessary until all items are imported.', 'ppi'); ?></li>
                <li><?php _e('Current Role Scoper configuration is not modified or deleted. You will still be able to restore previous behavior by reactivating Role Scoper if necessary.', 'ppi'); ?></li>
                <li><?php _e('Following import, you should manually review the results and confirm that permissions are correct. Some manual followup may be required.', 'ppi'); ?></li>
                <li><?php _e('Category Restrictions on Contributor and Author are converted to Term Assignment Exceptions. If you want a user to be blocked from authoring but able to contribute to a category, manually assign "Post - Assign Term - Also these" exceptions for desired terms, with status "(unpublished)".', 'ppi'); ?></li>
                <li><?php _e('Category Restrictions on Editor are converted to Post Edit Exceptions. If you want a sitewide Editor to be blocked from editing in some categories but still able to author/contribute to them, you will need to change their sitewide role to Contributor/Author and assign "Post - Edit - Also these" exceptions for the categories which they should be a full Editor in.', 'ppi'); ?></li>
                <li><?php _e('Role date limits are not currently supported as such. You can assign roles to groups and (with the Membership module activated) apply membership date ranges. But RS role date limits are not imported. If this is an issue, submit a feature development request.', 'ppi'); ?></li>
                <li><?php _e('Content date ranges for supplemental roles are not currently supported and not imported.', 'ppi'); ?></li>
                <li><?php _e('NextGen Gallery roles are not currently supported and not imported.', 'ppi'); ?></li>
            </ul>
            </p>
        <?php
        endif;

        if ($offer_pp = presspermit()->isPro() && $this->hasUnimported('pp')) : ?>
            <?php if ($this->hasUnimported('rs')) : ?>
                <br/>
                <hr/>
            <?php endif; ?>

            <h3>
                <?php _e('Press Permit Beta (1.x) Import', 'presspermit'); ?>
            </h3>

            <p>
                <?php _e('Migrates Press Permit 1.x Options, Role Groups, Roles and Conditions.', 'ppi'); ?>
            </p>

            <br />

            <input name="pp_pp_import" type="submit" value="Do Import"/>

            <?php
            global $wpdb;
            if ($count = $wpdb->get_var("SELECT COUNT(i.ID) FROM $wpdb->ppi_imported AS i INNER JOIN $wpdb->ppi_runs AS r ON i.run_id = r.ID AND r.import_type = 'pp'")) :
                ?>
                <p>
                    <?php printf(_n('(%s configuration item previously imported)', '(%s configuration items previously imported)', $count), $count); ?>
                </p>
            <?php
            endif;
            ?>

            <br /><br />

            <p>
            <?php _e('<strong>Notes:</strong>', 'ppi'); ?>

            <ul class="pp-notes" style="max-width:500px;margin-left:20px">
                <li><?php _e('The import can be run multiple times if source values change.', 'ppi'); ?></li>
                <li><?php _e('Configuration items will be imported even if the request exceeds PHP execution time limit. Repeat as necessary until all items are imported.', 'ppi'); ?></li>
                <li><?php _e('Current Press Permit 0.9/1.x configuration is not modified or deleted. You will still be able to restore previous behavior by reactivating the old version (and associated extensions) if necessary.', 'ppi'); ?></li>
                <li><?php _e('Following import, you should manually review the results and confirm that permissions are correct. Some manual followup may be required.', 'ppi'); ?></li>
                <li><?php _e('Editorial conditions are converted to Post Edit Exceptions for specific posts, assigned to applicable WP Role metagroups.', 'ppi'); ?></li>
                <li><?php _e('Supplemental Editor roles for Editability conditions are converted to Exceptions (Post - Edit - Also these) for specific posts.', 'ppi'); ?></li>
                <li><?php _e('Contributor Restrict and Author Restrict conditions are converted to Exceptions (all types - Assign Term - Not these), assigned to applicable WP Role metagroups.', 'ppi'); ?></li>
                <li><?php _e('Supplemental roles for Bypass Contributor Restrict and Bypass Author Restrict are converted to Exceptions (all types - Assign Term - Also these)', 'ppi'); ?></li>
                <li><?php _e('For WPML, post-specific roles and conditions mirrored to translations are imported. But language-specific sitewide role assignments are no longer supported and not imported.', 'ppi'); ?></li>
                <li><?php _e('For bbPress, forum-specific Spectator roles are imported as an Exception on the "Read" operation.', 'ppi'); ?></li>
                <li><?php _e('For bbPress, forum-specific Participant roles are imported as Exceptions on the "Read", "Create Topics", and "Submit Replies" operations.', 'ppi'); ?></li>
                <li><?php _e('For bbPress, forum-specific Moderator roles are imported as Exceptions on the "Read", "Create Topics", "Submit Replies" and "Edit" operations.', 'ppi'); ?></li>
                <li><?php _e('Other direct roles assigned to specific terms or posts are not imported. If you modified a Role Usage setting from Pattern Role to Direct-Assigned, manually create exceptions as needed.', 'ppi'); ?></li>
            </ul>
            </p>
        <?php
        endif;

        if (!$offer_rs && !$offer_pp && empty($_POST['pp_rs_import']) && empty($_POST['pp_pp_import'])) : ?>
            <p>
                <?php _e('Nothing to import!', 'ppi'); ?>
            </p>
        <?php
        endif;

        if (presspermit()->getOption('display_hints')) :
            ?>
            <div class="pp-optionhint">
                <?php
                printf(__('Once your import task is complete, you can eliminate this tab by deactivating the %s plugin.', 'ppi'), __('PressPermit Import', 'ppi'));
                ?>
            </div>
        <?php
        endif;
        ?>

        </td>
        </tr>

        <?php
        global $wpdb, $blog_id;

        if (is_multisite())
            $site_clause = (1 === intval($blog_id)) ? "AND site > 0" : "AND site = '$blog_id'";  // if on main site, will undo import for all sites
        else
            $site_clause = '';

        if ($wpdb->get_col("SELECT run_id FROM $wpdb->ppi_imported WHERE run_id > 0 $site_clause")) : ?>
            <tr>
                <td>

                    <?php
                    $msg = __("All imported groups, roles, exceptions and options will be deleted. Are you sure?", 'ppi');
                    $js_call = "javascript:if (confirm('$msg')) {return true;} else {return false;}";
                    ?>
                    <div style="float:right">
                        <input name="pp_undo_imports" type="submit" value="<?php _e('Undo All Imports', 'ppi'); ?>"
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
