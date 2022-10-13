<?php

namespace PublishPress\Permissions\UI\Handlers;

class Settings
{
    public function __construct()
    {
        check_admin_referer('pp-update-options');

        if (!current_user_can('pp_manage_settings')) {
            wp_die(esc_html(PWP::__wp('Cheatin&#8217; uh?')));
        }

        $args = apply_filters('presspermit_handle_submission_args', []); // todo: is this used?

        if (presspermit_is_POST('pp_submission_topic', 'options')) {

            if (isset($_POST['presspermit_submit'])) {
                $this->updateOptions($args);
                do_action('presspermit_handle_submission', 'update', $args);
            
            } elseif (isset($_POST['presspermit_defaults'])) {
                $this->defaultOptions($args);
                do_action('presspermit_handle_submission', 'default', $args);
            }

            presspermit()->refreshOptions();

        } elseif (isset($_POST['pp_role_usage_defaults'])) {
            delete_option('presspermit_role_usage');
            presspermit()->refreshOptions();
        }
    }

    private function updateOptions($args)
    {
        $this->updatePageOptions($args);

        global $wpdb;

        $wpdb->query(
            "UPDATE $wpdb->options SET autoload = 'no' WHERE option_name LIKE 'presspermit_%'"
            . " AND option_name NOT LIKE '%_version' AND option_name NOT IN ('presspermit_custom_conditions_post_status')"
        );
    }

    private function defaultOptions($args)
    {
        check_admin_referer('pp-update-options');

        $pp = presspermit();

        if (!$all_options = presspermit_POST_var('all_options')) {
            return;
        }

        $default_prefix = apply_filters('presspermit_options_apply_default_prefix', '', $args);

        $reviewed_options = array_map('sanitize_key', explode(',', sanitize_text_field($all_options)));

        if ($all_otype_options = presspermit_POST_var('all_otype_options')) {
            $reviewed_options = array_merge(
                $reviewed_options, 
                array_map('sanitize_key', explode(',', sanitize_text_field($all_otype_options)))
            );
        }

        foreach ($reviewed_options as $option_name) {
            $pp->deleteOption($default_prefix . $option_name, $args);
        }

        require_once(PRESSPERMIT_CLASSPATH . '/PluginUpdated.php');
        \PublishPress\Permissions\PluginUpdated::deactivateModules(['current_deactivations' => []]);

        $tab = (!presspermit_empty_POST('pp_tab')) ? "&pp_tab={" . presspermit_POST_key('pp_tab') . "}" : '';
        wp_redirect(admin_url("admin.php?page=presspermit-settings$tab&presspermit_submit_redirect=1"));
        exit;
    }

    private function updatePageOptions($args)
    {
        check_admin_referer('pp-update-options');

        $pp = presspermit();

        if (!$all_options = presspermit_POST_var('all_options')) {
            return;
        }

        do_action('presspermit_update_options', $args);

        $default_prefix = apply_filters('presspermit_options_apply_default_prefix', '', $args);

        foreach (array_map('pp_permissions_sanitize_entry', explode(',', sanitize_text_field($all_options))) as $option_basename) {
            $value = presspermit_POST_var($option_basename);

            if (in_array($option_basename, ['teaser_redirect_page', 'teaser_redirect_anon_page'])) {
                $compare_option = ('teaser_redirect_anon_page' == $option_basename) ? 'teaser_redirect_anon' : 'teaser_redirect';
                
                if (('[login]' == presspermit_POST_var($compare_option))) {
                    $value = '[login]';
                }
            }

            $_value = apply_filters('presspermit_custom_sanitize_setting', null, $option_basename, $value);

            if ($_value !== null) {
                $value = $_value;
            } elseif (!is_array($value)) {
                $value = trim(sanitize_text_field($value));
            } else {
                $value = array_map('sanitize_text_field', $value);
            }

            $pp->updateOption($default_prefix . $option_basename, $value, $args);
        }

        if ($all_otype_options = presspermit_POST_var('all_otype_options')) {
            foreach (array_map('pp_permissions_sanitize_entry', explode(',', sanitize_text_field($all_otype_options))) as $option_basename) {
                // support stored default values (to apply to any post type which does not have an explicit setting)
                if (isset($_POST[$option_basename][0])) {
                    $_POST[$option_basename][''] = pp_permissions_sanitize_entry(sanitize_text_field($_POST[$option_basename][0]));
                    unset($_POST[$option_basename][0]);
                }

                $value = (isset($pp->default_options[$option_basename])) ? $pp->default_options[$option_basename] : [];

                // retain setting for any types which were previously enabled for filtering but are currently not registered
                $current = $pp->getOption($option_basename);

                if ($current = $pp->getOption($option_basename)) {
                    $value = array_merge($value, $current);
                }

                if ($option_val = presspermit_POST_var($option_basename)) {
                    $value = array_merge($value, array_map('pp_permissions_sanitize_entry', $option_val));
                }

                $pp->updateOption($default_prefix . $option_basename, $value, $args);
            }
        }

        if (!presspermit_empty_POST('post_blockage_priority')) {  // once this is switched on manually, don't ever default-disable it again
            if (get_option('presspermit_legacy_exception_handling')) {
                delete_option('presspermit_legacy_exception_handling');
            }
        }
        
        // =============== Module Activation ================
        if (!$_deactivated = $pp->getOption('deactivated_modules')) {
            $_deactivated = [];
        }
        
        $deactivated = $_deactivated;

        // add deactivations (unchecked from Active list)
        if ($reviewed_modules = presspermit_POST_var('presspermit_reviewed_modules')) {
            $reviewed_modules = array_fill_keys(array_map('sanitize_key', explode(',', sanitize_text_field($reviewed_modules))), (object)[]);

            $deactivated = array_merge(
                $deactivated,
                array_diff_key(
                    $reviewed_modules,
                    !presspermit_empty_POST('presspermit_active_modules') ? array_filter((array) presspermit_POST_key('presspermit_active_modules')) : []
                )
            );
        }

        // remove deactivations (checked in Inactive list)
        if ($deactivated_modules = presspermit_POST_var('presspermit_deactivated_modules')) {
            $deactivated = array_diff_key(
                $deactivated, 
                array_map('sanitize_key', (array) $deactivated_modules)
            );
        }

        if ($_deactivated !== $deactivated) {
            foreach(array_diff_key($deactivated, $_deactivated) as $module_name => $module) {
                do_action($module_name . '_deactivate');
            }

            foreach(array_diff_key($_deactivated, $deactivated) as $module_name => $module) {
                if (in_array($module_name, ['presspermit-file-access'])) {
                    update_option(str_replace('-', '_', $module_name) . '_deactivate', 1);
                }
            }

            $pp->updateOption('deactivated_modules', $deactivated);
            $tab = (!presspermit_empty_POST('pp_tab')) ? "&pp_tab={" . presspermit_POST_key('pp_tab') . "}" : '';
            wp_redirect(admin_url("admin.php?page=presspermit-settings$tab&presspermit_submit_redirect=1"));
            exit;
        }
        // =====================================================
    }
}
