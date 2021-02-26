<?php
namespace PublishPress\Permissions\Collab;

class Constants
{
    function __construct()
    {
        add_filter('presspermit_constants', [$this, 'flt_pp_constants']);

    }

    function flt_pp_constants($pp_constants)
    {

        $type = 'permissions-admin';
        $consts = [
            'PP_NON_EDITORS_SET_EDIT_EXCEPTIONS',
        ];
        foreach ($consts as $k) $pp_constants[$k] = (object)['descript' => SettingsAdmin::getConstantString($k), 'type' => $type];


        $type = 'editing';
        $consts = [
            'PP_DISABLE_FORKING_SUPPORT',
            'PP_LOCK_OPTION_PAGES_ONLY',
            'PPCE_LIMITED_EDITORS_TOP_LEVEL_PUBLISH',
            'PPC_ASSOCIATION_NOFILTER',
            'PP_AUTO_DEFAULT_TERM',
            'PP_AUTO_DEFAULT_CATEGORY',
            'PP_AUTO_DEFAULT_POST_TAG',
            'PP_AUTO_DEFAULT_CUSTOM_TAXOMY_NAME_HERE',
            'PP_NO_AUTO_DEFAULT_TERM',
            'PP_AUTO_DEFAULT_CATEGORY',
            'PP_NO_AUTO_DEFAULT_POST_TAG',
            'PP_NO_AUTO_DEFAULT_CUSTOM_TAXOMY_NAME_HERE',
            'PPCE_DISABLE_CATEGORY_RETENTION',
            'PPCE_DISABLE_POST_TAG_RETENTION',
            'PPCE_DISABLE_CUSTOM_TAXOMY_NAME_HERE_RETENTION',
            'PP_NO_MODERATION',
        ];
        foreach ($consts as $k) $pp_constants[$k] = (object)['descript' => SettingsAdmin::getConstantString($k), 'type' => $type];


        $type = 'nav-menu-manage';
        $consts = [
            'PP_SUPPRESS_APPEARANCE_LINK',
            'PP_STRICT_MENU_CAPS',
            'PPCE_RESTRICT_MENU_TOP_LEVEL',
            'PP_NAV_MENU_DEFAULT_TO_SUBITEM',
            'PP_LEGACY_MENU_SETTINGS_ACCESS',
            'PPCE_DISABLE_NAV_MENU_UPDATE_FILTERS',
        ];
        foreach ($consts as $k) $pp_constants[$k] = (object)['descript' => SettingsAdmin::getConstantString($k), 'type' => $type];


        $type = 'media';
        $consts = [
            'PP_BLOCK_UNATTACHED_UPLOADS',
        ];
        foreach ($consts as $k) $pp_constants[$k] = (object)['descript' => SettingsAdmin::getConstantString($k), 'type' => $type];


        $type = 'admin';
        $consts = [
            'PPCE_CAN_ASSIGN_OWN_ROLE',
            'PP_AUTHOR_POST_META',
        ];
        foreach ($consts as $k) $pp_constants[$k] = (object)['descript' => SettingsAdmin::getConstantString($k), 'type' => $type];


        return $pp_constants;
    }

}
