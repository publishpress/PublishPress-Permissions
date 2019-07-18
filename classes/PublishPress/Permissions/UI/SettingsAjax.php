<?php

namespace PublishPress\Permissions\UI;

class SettingsAjax
{
    public function __construct() {
        switch ($_GET['pp_ajax_settings']) {
            case 'refresh_version':
                check_admin_referer('wp_ajax_pp_refresh_version');
                $this->refreshVersion();
                break;
        }
    }

    private function refreshVersion()
    {
        exit();
    }
}
