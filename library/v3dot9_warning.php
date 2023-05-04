<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

if (defined('PRESSPERMIT_V3DOT9_WARNING_LOADED')) {
    return;
}

define('PRESSPERMIT_V3DOT9_WARNING_LOADED', true);

if (! is_admin()) {
    return;
}

if (version_compare(get_bloginfo('version'), '5.5', '>=')) {
    return;
}

if (! current_user_can('activate_plugins')) {
    return;
}

// Dismiss the notice
add_action(
    'admin_init',
    function () {
        if (! isset($_GET['presspermit_v3dot9_warning_dismiss'])) {
            return;
        }

        if (! wp_verify_nonce($_GET['_wpnonce'], 'presspermit_v3dot9_warning_dismiss')) {
            return;
        }

        update_user_meta(get_current_user_id(), 'presspermit_v3dot9_warning_dismissed', 1);
    }
);

// Show a dismissible admin notice about the 3.9 upgrade.
add_action(
    'admin_notices',
    function () {
        $notice_id = 'presspermit-v3dot9-warning';
        $notice_dismissed = get_user_meta(get_current_user_id(), 'presspermit_v3dot9_warning_dismissed', true);

        if ($notice_dismissed) {
            return;
        }

        $notice = '<div class="notice notice-warning is-dismissible" id="' . $notice_id . '">';
        $notice .= '<p><strong>';
        $notice .= esc_html__('Attention:', 'press-permit-core');
        $notice .= '</strong>';
        $notice .= esc_html__('PublishPress Permissions next update (v3.9) will be dropping support for WordPress versions older than 5.5. Please update your WordPress installation to continue using PublishPress Permissions, or do not update beyond v3.8.7.', 'press-permit-core');
        $notice .= '</p>';

        // Add a acknowledge button
        $notice .= '<p>';
        $notice .= '<a href="' . esc_url(wp_nonce_url(add_query_arg('presspermit_v3dot9_warning_dismiss', 1), 'presspermit_v3dot9_warning_dismiss')) . '" class="button button-primary">';
        $notice .= esc_html__('Dismiss', 'press-permit-core');
        $notice .= '</a>';
        $notice .= '</p>';

        $notice .= '</div>';

        echo $notice;
    }
);
