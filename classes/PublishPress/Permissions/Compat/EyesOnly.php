<?php

namespace PublishPress\Permissions\Compat;

class EyesOnly
{
    public function __construct() {
        add_action('init', [$this, 'actRegister']);
        add_filter('eo_shortcode_matched', [$this, 'fltShortcodeMatched'], 10, 3);
    }
    
    public function actRegister()
    {
        sseo_register_parameter('pp_group', __('Permission Group', 'press-permit-core'));
    }

    public function fltShortcodeMatched($matched, $params, $shortcode_content)
    {
        if (!empty($params['pp_group'])) {
            if (array_intersect($params['pp_group'], array_keys(presspermit()->getUser()->groups['pp_group'])))
                return true;
        }

        return $matched;
    }
}
