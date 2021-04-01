<?php

namespace PublishPress\Permissions\UI\Handlers;

class Settings
{
    public function __construct()
    {
        if (!current_user_can('pp_manage_settings'))
            wp_die(PWP::__wp('Cheatin&#8217; uh?'));

        if (!empty($_REQUEST['presspermit_refresh_updates'])) {
            delete_site_transient('update_plugins');
            delete_option('_site_transient_update_plugins');
            //presspermit()->admin()->getVersionInfo(['force_refresh'=>true]);
            wp_update_plugins();
            wp_redirect(admin_url('admin.php?page=presspermit-settings&presspermit_refresh_done=1'));
            exit;
        }

        if (!empty($_REQUEST['pp_renewal'])) {
            if (presspermit()->isPro()) {
                include_once(PRESSPERMIT_PRO_ABSPATH . '/includes-pro/pro-renewal-redirect.php');
            } else {
                include_once(PRESSPERMIT_ABSPATH . '/includes/renewal-redirect.php');
            }

            exit;
        }

        if (isset($_POST['presspermit_submit'])) {
            $this->handleSubmission('update');

        } elseif (isset($_POST['presspermit_defaults'])) {
            $this->handleSubmission('default');

        } elseif (isset($_POST['pp_role_usage_defaults'])) {
            delete_option('presspermit_role_usage');
            presspermit()->refreshOptions();
        }
    }

    private function handleSubmission($action)
    {
        $args = apply_filters('presspermit_handle_submission_args', []); // @todo: is this used?

        if (empty($_POST['pp_submission_topic']))
            return;

        if ('options' == $_POST['pp_submission_topic']) {
            $method = "{$action}Options";
            if (method_exists($this, $method))
                call_user_func([$this, $method], $args);

            do_action('presspermit_handle_submission', $action, $args);

            presspermit()->refreshOptions();
        }
    }

    private function updateOptions($args)
    {
        check_admin_referer('pp-update-options');

        $this->updatePageOptions($args);

        global $wpdb;

        $wpdb->query(
            "UPDATE $wpdb->options SET autoload = 'no' WHERE option_name LIKE 'presspermit_%'"
            . " AND option_name NOT LIKE '%_version' AND option_name NOT IN ('presspermit_custom_conditions_post_status')"
        );
    }

    private function defaultOptions($args)
    {
        $pp = presspermit();

        check_admin_referer('pp-update-options');

        $default_prefix = apply_filters('presspermit_options_apply_default_prefix', '', $args);

        $reviewed_options = array_merge(explode(',', $_POST['all_options']), explode(',', $_POST['all_otype_options']));
        foreach ($reviewed_options as $option_name) {
            $pp->deleteOption($default_prefix . $option_name, $args);
        }
    }

    private function updatePageOptions($args)
    {
        $pp = presspermit();

        do_action('presspermit_update_options', $args);

        $default_prefix = apply_filters('presspermit_options_apply_default_prefix', '', $args);

        foreach (explode(',', $_POST['all_options']) as $option_basename) {
            $value = isset($_POST[$option_basename]) ? $_POST[$option_basename] : '';

            if (!is_array($value))
                $value = trim($value);

            $pp->updateOption($default_prefix . $option_basename, stripslashes_deep($value), $args);
        }

        foreach (explode(',', $_POST['all_otype_options']) as $option_basename) {
            // support stored default values (to apply to any post type which does not have an explicit setting)
            if (isset($_POST[$option_basename][0])) {
                $_POST[$option_basename][''] = $_POST[$option_basename][0];
                unset($_POST[$option_basename][0]);
            }

            $value = (isset($pp->default_options[$option_basename])) ? $pp->default_options[$option_basename] : [];

            // retain setting for any types which were previously enabled for filtering but are currently not registered
            $current = $pp->getOption($option_basename);

            if ($current = $pp->getOption($option_basename)) {
                $value = array_merge($value, $current);
            }

            if (isset($_POST[$option_basename])) {
                $value = array_merge($value, $_POST[$option_basename]);
            }

            foreach (array_keys($value) as $key) {
                $value[$key] = stripslashes_deep($value[$key]);
            }

            $pp->updateOption($default_prefix . $option_basename, $value, $args);
        }

        if (!empty($_POST['post_blockage_priority'])) {  // once this is switched on manually, don't ever default-disable it again
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
        if (!empty($_POST['presspermit_reviewed_modules'])) {
            $reviewed_modules = array_fill_keys( explode(',', $_POST['presspermit_reviewed_modules']), (object)[]);

            $deactivated = array_merge(
                $deactivated,
                array_diff_key(
                    $reviewed_modules,
                    !empty($_POST['presspermit_active_modules']) ? array_filter($_POST['presspermit_active_modules']) : []
                )
            );
        }

        // remove deactivations (checked in Inactive list)
        if (! empty($_POST['presspermit_deactivated_modules'])) {
            $deactivated = array_diff_key(
                $deactivated, 
                $_POST['presspermit_deactivated_modules']
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
            $tab = (!empty($_POST['pp_tab'])) ? "&pp_tab={$_POST['pp_tab']}" : '';
            wp_redirect(admin_url("admin.php?page=presspermit-settings$tab&presspermit_submit_redirect=1"));
        }
        // =====================================================
    }
}
