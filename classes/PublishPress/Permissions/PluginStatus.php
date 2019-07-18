<?php

namespace PublishPress\Permissions;

class PluginStatus
{
    public static function callHome($request_topic, $request_vars = [], $post_vars = false)
    {
    }

    /**
     * Loads the change log for an available update
     *
     * @return void
     **@author Kevin Behrens
     *
     * @author Jonathan Davis
     */
    public static function changeLog()
    {
    }

    public static function renewalMsg()
    {
        return sprintf(
            __('Your license key has expired.  For updates to PressPermit Pro and priority support, <a href="%1$s" target="_blank">please renew</a>.', 'press-permit-core'),
            admin_url('admin.php?page=presspermit-settings&pp_tab=install'),
            'https://publishpress.com/pricing/'
        );
    }

    public static function buyMsg()
    {
        return sprintf(
            __('Activate your <a href="%1$s">license key</a> for PressPermit Pro and priority support.', 'press-permit-core'),
            admin_url('admin.php?page=presspermit-settings&pp_tab=install'),
            'https://publishpress.com/pricing/'
        );
    }
}
