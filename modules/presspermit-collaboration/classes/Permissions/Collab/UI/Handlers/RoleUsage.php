<?php
namespace PublishPress\Permissions\Collab\UI\Handlers;

class RoleUsage
{
    public static function handleRequest() 
    {
        $action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : '';

        $url = apply_filters('presspermit_role_usage_base_url', 'admin.php');
        $redirect = $err = false;

        if (!current_user_can('pp_manage_settings'))
            wp_die(__('You are not permitted to do that.', 'press-permit-core'));

        $pp = presspermit();

        switch ($action) {
            case 'update' :
                $pp = presspermit();

                $role_name = sanitize_text_field($_REQUEST['role']);
                check_admin_referer('pp-update-role-usage_' . $role_name);

                // overall pattern role enable
                $role_usage = $pp->getOption('role_usage');
                if (!is_array($role_usage)) {
                    $role_usage = array_fill_keys(array_keys($pp->role_defs->pattern_roles), 'pattern');
                    $role_usage = array_merge($role_usage, array_fill_keys(array_keys($pp->role_defs->direct_roles), 'direct'));
                }

                $role_usage[$role_name] = (isset($_POST['pp_role_usage'])) ? $_POST['pp_role_usage'] : 0;

                $pp->updateOption('role_usage', $role_usage);

                $pp->refreshOptions();
                do_action('presspermit_registrations');
                do_action('presspermit_roles_defined');

                $pp->refreshOptions();

                break;
        } // end switch

        if ($redirect) {
            if (!empty($_REQUEST['wp_http_referer']))
                $redirect = add_query_arg('wp_http_referer', urlencode($_REQUEST['wp_http_referer']), $redirect);

            $redirect = esc_url_raw(add_query_arg('update', 1, $redirect));

            wp_redirect($redirect);
            exit;
        }
    }
}
