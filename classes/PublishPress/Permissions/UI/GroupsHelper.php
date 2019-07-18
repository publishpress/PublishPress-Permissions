<?php
namespace PublishPress\Permissions\UI;

class GroupsHelper
{
    public static function getUrlProperties(&$url, &$referer, &$redirect)
    {
        $url = apply_filters('presspermit_groups_base_url', 'admin.php');

        if (empty($_REQUEST)) {
            $referer = '<input type="hidden" name="wp_http_referer" value="' . esc_attr(stripslashes($_SERVER['REQUEST_URI'])) . '" />';
        } elseif (!empty($_REQUEST['wp_http_referer'])) {
            $redirect = esc_url_raw(remove_query_arg(['wp_http_referer', 'updated', 'delete_count'], stripslashes($_REQUEST['wp_http_referer'])));
            $referer = '<input type="hidden" name="wp_http_referer" value="' . esc_attr($redirect) . '" />';
        } else {
            $redirect = "$url?page=presspermit-groups";
            if (! empty($_REQUEST['group_variant'])) {
                $redirect = add_query_arg('group_variant', $_REQUEST['group_variant']);
            }
            $referer = '';
        }
    }
}
