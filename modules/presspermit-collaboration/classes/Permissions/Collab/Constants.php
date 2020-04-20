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
            'PP_NON_EDITORS_SET_EDIT_EXCEPTIONS' => __("Enable post contributors or authors with pp_set_edit_exceptions capability to set edit exceptions on posts authored by others", 'press-permit-core'),
        ];
        foreach ($consts as $k => $v) $pp_constants[$k] = (object)['descript' => $v, 'type' => $type];


        $type = 'editing';
        $consts = [
            'PP_DISABLE_FORKING_SUPPORT' => __("Don't try to integrate with the Post Forking plugin", 'press-permit-core'),
            'PP_LOCK_OPTION_PAGES_ONLY' => __("Permissions setting 'Pages can be set or removed from Top Level by' applies to 'page' type only", 'press-permit-core'),
            'PPCE_LIMITED_EDITORS_TOP_LEVEL_PUBLISH' => __("If user cannot generally save pages to top level but a page they are editing is already there, allow it to stay at top level even if not yet published ", 'press-permit-core'),
            'PPC_ASSOCIATION_NOFILTER' => __("Circle membership does not limit page association (page parent setting) ability", 'press-permit-core'),
            'PP_AUTO_DEFAULT_TERM' => __("When saving a post, if default term (of any taxonomy) is not in user's subset of assignable terms, substitute first available", 'press-permit-core'),
            'PP_AUTO_DEFAULT_CATEGORY' => __("When saving a post, if default category is not in user's subset of assignable categories, substitute first available", 'press-permit-core'),
            'PP_AUTO_DEFAULT_POST_TAG' => __("When saving a post, if default tag is not in user's subset of assignable tags, substitute first available", 'press-permit-core'),
            'PP_AUTO_DEFAULT_CUSTOM_TAXOMY_NAME_HERE' => __("When saving a post, if default term (of specified taxonomy) is not in user's subset of assignable tags, substitute first available", 'press-permit-core'),
            'PP_NO_AUTO_DEFAULT_TERM' => __("When saving a post, never auto-assign a term (of any taxonomy), even if it is the user's only assignable term", 'press-permit-core'),
            'PP_AUTO_DEFAULT_CATEGORY' => __("When saving a post, never auto-assign a category, even if it is the user's only assignable category", 'press-permit-core'),
            'PP_NO_AUTO_DEFAULT_POST_TAG' => __("When saving a post, never auto-assign a tag, even if it is the user's only assignable tag", 'press-permit-core'),
            'PP_NO_AUTO_DEFAULT_CUSTOM_TAXOMY_NAME_HERE' => __("When saving a post, never auto-assign a term (of specified taxonomy), even if it is the user's only assignable term", 'press-permit-core'),
            'PPCE_DISABLE_CATEGORY_RETENTION' => __("When a limited user updates a post, strip out currently stored categories they don't have permission to assign", 'press-permit-core'),
            'PPCE_DISABLE_POST_TAG_RETENTION' => __("When a limited user updates a post, strip out currently stored tags they don't have permission to assign", 'press-permit-core'),
            'PPCE_DISABLE_CUSTOM_TAXOMY_NAME_HERE_RETENTION' => __("When a limited user updates a post, strip out currently stored terms (of specified taxonomy) they don't have permission to assign", 'press-permit-core'),
            'PP_NO_MODERATION' => __("Don't define an 'Approved' status, even if Status Control module is active", 'press-permit-core'),
        ];
        foreach ($consts as $k => $v) $pp_constants[$k] = (object)['descript' => $v, 'type' => $type];


        $type = 'nav-menu-manage';
        $consts = [
            'PP_SUPPRESS_APPEARANCE_LINK' => __("If user has Nav Menu management capabilities but can't 'edit_theme_options', strip link out of wp-admin Appearance Menu instead of linking it to nav-menus", 'press-permit-core'),
            'PP_STRICT_MENU_CAPS' => __("Don't credit implicit 'manage_nav_menus' capability to users who have 'edit_theme_options' or 'switch_themes' capability", 'press-permit-core'),
            'PPCE_RESTRICT_MENU_TOP_LEVEL' => __("Prevent non-Administrators from adding new Nav Menu items to top level (add below existing editable items instead)", 'press-permit-core'),
            'PP_NAV_MENU_DEFAULT_TO_SUBITEM' => __("For non-Administrators, new Nav Menu items default to being a child of first editable item ", 'press-permit-core'),
            'PP_LEGACY_MENU_SETTINGS_ACCESS' => __("Don't require any additional capabilities for management of Nav Menu settings (normally require 'manage_menu_settings', 'edit_others_pages' or 'publish_pages') ", 'press-permit-core'),
            'PPCE_DISABLE_NAV_MENU_UPDATE_FILTERS' => __("Eliminate extra filtering queries on Nav Menu update, even for non-Administrators", 'press-permit-core'),
        ];
        foreach ($consts as $k => $v) $pp_constants[$k] = (object)['descript' => $v, 'type' => $type];


        $type = 'media';
        $consts = [
            'PP_BLOCK_UNATTACHED_UPLOADS' => __("Don't allow non-Administrators to see others' unattached uploads, regardless of Permissions settings.  Their own unattached uploads are still accessible unless option 'own_attachments_always_editable' is set false", 'press-permit-core'),
        ];
        foreach ($consts as $k => $v) $pp_constants[$k] = (object)['descript' => $v, 'type' => $type];


        $type = 'admin';
        $consts = [
            'PPCE_CAN_ASSIGN_OWN_ROLE' => __("Limited User Editors can assign their own role", 'press-permit-core'),
            'PP_AUTHOR_POST_META' => __("Post Meta fields to copy when using 'Add Author Page' dropdown on Users screen", 'press-permit-core'),
        ];
        foreach ($consts as $k => $v) $pp_constants[$k] = (object)['descript' => $v, 'type' => $type];


        if (class_exists('BuddyPress', false)) {
            $type = 'buddypress';
            $consts = [
                'PPBP_GROUP_MODERATORS_ONLY' => __("Count users as a member of a BuddyPress Permissions Group only if they are a moderator of the BP group", 'press-permit-core'),
                'PPBP_GROUP_ADMINS_ONLY' => __("Count users as a member of a BuddyPress Permissions Group only if they are an administrator of the BP group", 'press-permit-core'),
            ];
            foreach ($consts as $k => $v) $pp_constants[$k] = (object)['descript' => $v, 'type' => $type];
        }

        if (is_multisite()) {
            $type = 'user-selection';
            $consts = [
                'PP_NETWORK_GROUPS_SITE_USERS_ONLY' => __("When searching for users via Permissions ajax, return return only users registered to current site", 'press-permit-core'),
                'PP_NETWORK_GROUPS_MAIN_SITE_ALL_USERS' => __("If user is a super admin or has 'pp_manage_network_members' capability, user searches via Permissions ajax return users from all sites", 'press-permit-core'),
            ];
            foreach ($consts as $k => $v) $pp_constants[$k] = (object)['descript' => $v, 'type' => $type];
        }

        if (defined('CMS_TPV_VERSION')) {
            $type = 'cms-tree-page-view';
            $consts = [
                'PP_CMS_TREE_NO_ADD' => __("CMS Page Tree View plugin: hide 'add' links (for all hierarchical post types) based on user's association permissions", 'press-permit-core'),
                'PP_CMS_TREE_NO_ADD_PAGE' => __("CMS Page Tree View plugin: hide 'add' links (for pages) based on user's page association permissions", 'press-permit-core'),
                'PP_CMS_TREE_NO_ADD_CUSTOM_POST_TYPE_NAME_HERE' => __("CMS Page Tree View plugin: hide 'add' links (for specified hierarchical post type) based on user's association permissions", 'press-permit-core'),
            ];
            foreach ($consts as $k => $v) $pp_constants[$k] = (object)['descript' => $v, 'type' => $type];
        }

        return $pp_constants;
    }

}
