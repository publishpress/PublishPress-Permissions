<?php

namespace PublishPress\Permissions\UI;

class Settings
{
    public function __construct() {
        // called by Dashboard\DashboardFilters::actMenuHandler

        @load_plugin_textdomain('press-permit-core-hints', false, dirname(plugin_basename(PRESSPERMIT_FILE)) . '/languages');

        require_once(PRESSPERMIT_CLASSPATH . '/UI/SettingsAdmin.php');

        require_once(PRESSPERMIT_CLASSPATH . '/UI/SettingsTabInstall.php');
        new SettingsTabInstall();

        require_once(PRESSPERMIT_CLASSPATH . '/UI/SettingsTabCore.php');
        new SettingsTabCore();

        require_once(PRESSPERMIT_CLASSPATH . '/UI/SettingsTabAdvanced.php');
        new SettingsTabAdvanced();

        // enqueue JS for footer
        global $wp_scripts;
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
        wp_enqueue_script('presspermit-settings', PRESSPERMIT_URLPATH . "/common/js/settings{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_VERSION, true);
        $wp_scripts->in_footer[] = 'presspermit-settings';  // otherwise it will not be printed in footer  @todo: review

        $vars = [
            'displayHints' => presspermit()->getOption('display_hints'), 
            'hintImg' => plugins_url('', PRESSPERMIT_FILE) . "/common/img/comment-grey-bubble.png"
        ];
        wp_localize_script('presspermit-settings', 'ppCoreSettings', $vars);

        if (presspermit()->isPro()) {
            wp_enqueue_script('presspermit-pro-settings', plugins_url('', PRESSPERMIT_PRO_FILE) . "/includes-pro/settings-pro{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_PRO_VERSION, true);
            $wp_scripts->in_footer[] = 'presspermit-pro-settings';  // otherwise it will not be printed in footer  @todo: review
        }

        if (!current_user_can('pp_manage_settings'))
            wp_die(PWP::__wp('Cheatin&#8217; uh?'));

        do_action('presspermit_options_ui');

        //$pp->load_config(); // @todo: confirm this is not needed ( $role_defs->defineRoles() )
        //$pp->loadContentFilters();

        $ui = SettingsAdmin::instance();

        $ui->all_options = [];

        $ui->tab_captions = apply_filters('presspermit_option_tabs', []);
        $ui->section_captions = apply_filters('presspermit_section_captions', []);
        $ui->option_captions = apply_filters('presspermit_option_captions', []);
        $ui->form_options = apply_filters('presspermit_option_sections', []);

        $ui->display_hints = presspermit()->getOption('display_hints');

        if ($_hidden = apply_filters('presspermit_hide_options', [])) {
            $hidden = [];
            foreach (array_keys($_hidden) as $option_name) {
                if (!is_array($_hidden[$option_name]) && strlen($option_name) > 3)
                    $hidden[] = substr($option_name, 3);
            }

            foreach (array_keys($ui->form_options) as $tab) {
                foreach (array_keys($ui->form_options[$tab]) as $section)
                    $ui->form_options[$tab][$section] = array_diff($ui->form_options[$tab][$section], $hidden);
            }
        }

        ?>
        <div class="pressshack-admin-wrapper wrap" id="pp-permissions-wrapper">
            <?php
            echo '<form id="pp_settings_form" action="" method="post">';
            wp_nonce_field('pp-update-options');

            do_action('presspermit_options_form');
            ?>
        <header>
            <?php PluginPage::icon(); ?>

            <div class="submit pp-submit" style="border:none;position:absolute;right:20px;top:0;">
                <input type="submit" name="presspermit_submit" class="button-primary" value="<?php _e('Save Changes', 'press-permit-core'); ?>"/>
            </div>
            <h1>
                <?php
                $title = apply_filters('presspermit_options_form_title', _e('Permissions Settings', 'press-permit-core'));
                _e($title);
                ?>
            </h1>

            <?php

            if ($subheading = apply_filters('presspermit_options_form_subheading', ''))
                echo $subheading;

            $color_class = apply_filters('presspermit_options_form_color_class', 'pp-backtan');

            $class_selected = "agp-selected_agent agp-agent $color_class";
            $class_unselected = "agp-unselected_agent agp-agent";

            ?>
            <script type="text/javascript">
                /* <![CDATA[ */
                jQuery(document).ready(function ($) {
                    $('li.agp-agent a').on('click', function()
                    {
                        $('li.agp-agent').removeClass('agp-selected_agent <?php echo $color_class; ?>');
                        $('li.agp-agent').addClass('agp-unselected_agent');
                        $(this).parent().addClass('agp-selected_agent <?php echo $color_class; ?>');
                        $('.pp-options-wrapper > div').hide();
                        presspermitShowElement($(this).attr('class'), $);
                    });
                });
                /* ]]> */
            </script>

        </header>
        
            <?php
            $default_tab = (isset($_REQUEST['pp_tab']) && isset($ui->tab_captions[$_REQUEST['pp_tab']]))
                ? $_REQUEST['pp_tab'] : 'install';

            $default_tab = apply_filters('presspermit_options_default_tab', $default_tab);

            // @todo: prevent line breaks in these links
            echo "<ul class='pp-list_horiz' style='margin-bottom:-0.1em'>";

            foreach ($ui->tab_captions as $tab => $caption) {
                if (!empty($ui->form_options[$tab])) {
                    $class = ($default_tab == $tab) ? $class_selected : $class_unselected;  // @todo: return to last tab

                    echo "<li class='$class'><a class='pp-{$tab}' href='javascript:void(0)'>"
                        . $ui->tab_captions[$tab] . '</a></li>';
                }
            }
            echo '</ul>';

            echo '<div class="pp-options-wrapper">';
            $table_class = 'form-table pp-form-table pp-options-table';

            if (isset($_REQUEST['presspermit_submit']) || isset($_REQUEST['presspermit_submit_redirect'])) :
                ?>
                <div id="message" class="updated">
                    <p>
                        <strong><?php _e('All settings were updated.', 'press-permit-core'); ?>&nbsp;</strong>
                    </p>
                </div>
            <?php
            elseif (isset($_REQUEST['presspermit_defaults'])) :
                ?>
                <div id="message" class="updated">
                    <p>
                        <strong><?php _e('All settings were reset to defaults.', 'press-permit-core'); ?>&nbsp;</strong>
                    </p>
                </div>
            <?php
            endif;

            foreach (array_keys($ui->tab_captions) as $tab) {
                $display = ($default_tab == $tab) ? '' : 'display:none';
                echo "<div id='pp-{$tab}' style='clear:both;margin:0;{$display}' class='pp-options $color_class'>";

                do_action("presspermit_{$tab}_options_pre_ui");

                echo "<table class='$table_class' id='pp-{$tab}_table'>";

                do_action("presspermit_{$tab}_options_ui");

                echo '</table></div>';
            }

            echo '</div>'; // pp-options-wrapper

            $ui->filterNetworkOptions();

            echo "<input type='hidden' name='all_options' value='" . implode(',', $ui->all_options) . "' />";
            echo "<input type='hidden' name='all_otype_options' value='" . implode(',', $ui->all_otype_options) . "' />";

            echo "<input type='hidden' name='pp_submission_topic' value='options' />";
            ?>

            <p class="submit pp-submit" style="border:none;">
                <input type="submit" name="presspermit_submit" class="button-primary" value="<?php _e('Save Changes', 'press-permit-core'); ?>"/>
                <input type="hidden" name="pp_tab"
                        value="<?php if (!empty($_REQUEST['pp_tab'])) echo esc_attr($_REQUEST['pp_tab']); ?>"/>
            </p>

            <?php
            $msg = __("All settings in this form (including those on undisplayed tabs) will be reset to DEFAULTS.  Are you sure?", 'press-permit-core');
            $js_call = "javascript:if (confirm('$msg')) {return true;} else {return false;}";
            ?>
            <p class="submit pp-submit-alternate" style="border:none;float:left">
                <input type="submit" name="presspermit_defaults" value="<?php _e('Revert to Defaults', 'press-permit-core') ?>"
                        onclick="<?php echo $js_call; ?>"/>
            </p>
            </form>
            <p style='clear:both'></p>

            <?php 
            presspermit()->admin()->publishpressFooter();
            ?>
        </div>

        <?php
    }

    public static function pluginInfoURL($plugin_slug)
    {
        return self_admin_url("plugin-install.php?tab=plugin-information&plugin=$plugin_slug&TB_iframe=true&width=640&height=678");
    }
}