<?php

namespace PublishPress\Permissions\UI;

class SettingsAjax
{
    public function __construct() {
        if (defined('PRESSPERMIT_PRO_VERSION')) {
			include_once(PRESSPERMIT_ABSPATH . '/includes-pro/pro-activation-ajax.php');
		}
    }
}
