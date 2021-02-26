<?php

namespace PublishPress\Permissions;

class CollabHooksAdmin
{
    function __construct()
    {
        // Late init following status registration, including moderation property for PublishPress statuses
        add_action('init', [$this, 'actDefaultPrivacyWorkaround'], 72);
        add_action('init', [$this, 'actAddAuthorPages'], 99);

        add_action('init', [$this, 'actImplicitNavMenuCaps']);
        add_action('current_screen', [$this, 'actImplicitNavMenuCaps']);

        add_action('presspermit_admin_handlers', [$this, 'actAdminHandlers']);
        add_action('load-post.php', [$this, 'actMaybeOverrideKses']);
        add_action('check_admin_referer', [$this, 'actCheckAdminReferer']);
        add_filter('pre_get_posts', [$this, 'actPreGetPosts']);

        add_filter('presspermit_user_has_group_cap', [$this, 'fltUserHasGroupCap'], 10, 4);
        add_filter('presspermit_can_set_exceptions', [$this, 'fltCanSetExceptions'], 10, 4);

        add_filter('presspermit_user_can_admin_role', [$this, 'fltUserCanAdminRole'], 10, 4);
        add_filter('presspermit_admin_groups', [$this, 'fltAdminGroups'], 10, 2);

        if (defined('PRESSPERMIT_ENABLE_PAGE_TEMPLATE_LIMITER') && PRESSPERMIT_ENABLE_PAGE_TEMPLATE_LIMITER) {
            if (strpos($_SERVER['REQUEST_URI'], 'wp-admin/post.php') || strpos($_SERVER['REQUEST_URI'], 'wp-admin/post-new.php')) {   
                require_once(PRESSPERMIT_PRO_ABSPATH . '/includes-pro/PageTemplateLimiter.php');
                new \PublishPress\Permissions\PageTemplateLimiter();
            }
        }

        add_action('_presspermit_admin_ui', [$this, 'actLoadUIFilters']);  // fires after user load if is_admin(), not XML-RPC, and not Ajax

        add_action('presspermit_init', [$this, 'actAdminWorkaroundFilters']);

        add_action('presspermit_update_item_exceptions', [$this, 'actAdminWorkaroundFilters'], 10, 3);

        add_filter('presspermit_posts_clauses_intercept', [$this, 'fltEditNavMenuFilterDisable'], 10, 2);

        if (class_exists('NestedPages') && !empty($_REQUEST['page']) && ('nestedpages' == $_REQUEST['page'])) {
            if (defined('PP_NESTED_PAGES_DISABLE_FILTERING') && !defined('PP_NESTED_PAGES_ENABLE_FILTERING')) {
                add_filter(
                    'presspermit_posts_clauses_intercept', 
                    function ($clauses, $orig_clauses) {
                        return $orig_clauses;
                    }, 10, 2
                );
            } else {
                add_action('admin_print_scripts', [$this, 'NestedPagesDisableQuickEdit']);
            }
        }
    }

    function NestedPagesDisableQuickEdit() {
        global $current_user;

        $allow_quickedit_roles = (defined('PP_NESTED_PAGES_QUICKEDIT_ROLES')) ? explode(',', str_replace(' ', '', strtolower(constant('PP_NESTED_PAGES_QUICKEDIT_ROLES')))) : [];
        $allow_context_menu_roles = (defined('PP_NESTED_PAGES_CONTEXT_MENU_ROLES')) ? explode(',', str_replace(' ', '', strtolower(constant('PP_NESTED_PAGES_CONTEXT_MENU_ROLES')))) : [];

        $hide_quickedit = !presspermit()->isUserUnfiltered() && !array_intersect($current_user->roles, $allow_quickedit_roles);
        $hide_context_menu = !presspermit()->isUserUnfiltered() && !array_intersect($current_user->roles, $allow_context_menu_roles);

        if ($hide_quickedit || $hide_context_menu) {
            ?>
            <style type="text/css">
            <?php if ($hide_quickedit):?>
            a.np-quick-edit {display:none;}
            <?php endif;?>
            
            <?php if ($hide_context_menu):?>
            div.nestedpages-dropdown a {display:none;}
            <?php endif;?>
            </style>
            <?php
        }
    }

    function actLoadUIFilters()
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Dashboard/DashboardFilters.php');
        new Collab\UI\Dashboard\DashboardFilters();

        if (!presspermit()->isUserUnfiltered()) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Dashboard/DashboardFiltersNonAdministrator.php');
            new Collab\UI\Dashboard\DashboardFiltersNonAdministrator();
        }
    }

    function actAdminWorkaroundFilters()
    {
        global $pagenow;

        if ('plugins.php' != $pagenow) {
            // low-level filtering for miscellaneous admin operations which are not well supported by the WP API
            $workaround_uris = [
                'index.php',
                'revision.php',
                'admin.php?page=rvy-revisions',
                'post.php',
                'post-new.php',
                'edit.php',
                'upload.php',
                'edit-comments.php',
                'edit-tags.php',
                'term.php',
                'profile.php',
                'admin-ajax.php',
                'link-manager.php',
                'link-add.php',
                'link.php',
                'edit-link-category.php',
                'edit-link-categories.php',
                'media-upload.php',
                'nav-menus.php',
            ];

            $workaround_uris = apply_filters('presspermit_admin_workaround_uris', $workaround_uris);

            if (in_array($pagenow, $workaround_uris, true) || in_array(presspermitPluginPage(), $workaround_uris, true)) {
                if (!presspermit()->isUserUnfiltered()) {
                    require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/AdminWorkarounds.php');
                    new Collab\AdminWorkarounds();
                }
            }
        }
    }

    function actImplicitNavMenuCaps()
    {
        global $current_user;

        if (empty($current_user->allcaps['manage_nav_menus']) && (!defined('PP_STRICT_MENU_CAPS') 
        && (!empty($current_user->allcaps['switch_themes']) || !empty($current_user->allcaps['edit_theme_options'])))
        ) {
            $current_user->allcaps['manage_nav_menus'] = true;
        }
    }

    function fltUserHasGroupCap($has_cap, $cap_name, $group_id, $group_type)
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/RoleAdmin.php');
        return Collab\RoleAdmin::hasGroupCap($has_cap, $cap_name, $group_id, $group_type);
    }

    // returns supplemental group which can be edited or member-managed via supplemental permissions
    function fltAdminGroups($editable_group_ids, $operation = 'manage')
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/RoleAdmin.php');
        return Collab\RoleAdmin::retrieveAdminGroups($editable_group_ids, $operation);
    }

    function fltCanSetExceptions($can, $operation, $for_item_type, $args = [])
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/RoleAdmin.php');
        return Collab\RoleAdmin::canSetExceptions($can, $operation, $for_item_type, $args);
    }

    // prevent default_privacy option from forcing a draft/pending post into private publishing
    function actDefaultPrivacyWorkaround()
    {
        global $pagenow;
        if (!empty($_POST) && in_array($pagenow, ['post.php', 'post-new.php'])) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostEdit.php');
            Collab\PostEdit::defaultPrivacyWorkaround();
        }
    }

    function actPreGetPosts($query_obj)
    {
        if (defined('DOING_AJAX') && DOING_AJAX && !empty($_REQUEST['action'])) {
            switch ($_REQUEST['action']) {
                case 'find_posts':
                    $query_obj->query_vars['suppress_filters'] = false;
                    break;
            }
        }
    }

    function actCheckAdminReferer($referer)
    {
        if (in_array($referer, ['bulk-posts', 'inlineeditnonce'], true)) {
            if ('bulk-posts' == $referer) {
                if (!empty($_REQUEST['action']) && !is_numeric($_REQUEST['action']))
                    $action = $_REQUEST['action'];
                elseif (!empty($_REQUEST['action2']) && !is_numeric($_REQUEST['action2']))
                    $action = $_REQUEST['action2'];
                else
                    $action = '';

                if ('edit' != $action)
                    return;
            }

            if (Collab::isLimitedEditor() && !current_user_can('pp_force_quick_edit'))
                wp_die(__('access denied', 'press-permit-core'));
        }
    }

    function actMaybeOverrideKses()
    {
        if (!empty($_POST) && !empty($_POST['action']) && ('editpost' == $_POST['action'])) {
            if (current_user_can('unfiltered_html')) // initial core cap check in kses_init() is unfilterable
                kses_remove_filters();
        }
    }

    function actAdminHandlers()
    {
        if (!empty($_POST)) {
            if ('presspermit-role-usage-edit' == presspermitPluginPage()) {
                add_action('presspermit_user_init', [$this, 'load_role_usage_edit_handler']);
            }
        }
    }

    function load_role_usage_edit_handler()
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Handlers/RoleUsage.php');
        Collab\UI\Handlers\RoleUsage::handleRequest();
    }

    function actAddAuthorPages()
    {
        if (!empty($_REQUEST['add_member_page'])) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Dashboard/BulkEdit.php');
            Collab\UI\Dashboard\BulkEdit::add_author_pages($_REQUEST);
        }
    }

    function fltUserCanAdminRole($can_admin, $role_name, $object_type, $item_id = 0)
    {
        return $this->userCanAdminRole($role_name, $object_type, $item_id);
    }

    function userCanAdminRole($role_name, $object_type, $item_id = 0)
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Permissions.php');
        return Collab\Permissions::userCanAdminRole($role_name, $object_type, $item_id);
    }

    function actUpdateItemExceptions($via_item_source, $item_id, $args)
    {
        if ('term' == $via_item_source) {
            Collab\ItemSave::itemUpdateProcessExceptions('term', 'term', $item_id, $args);
        }
    }

    function fltEditNavMenuFilterDisable($use_clauses, $orig_clauses)
    {
        if (presspermit()->isContentAdministrator() || defined('PPCE_DISABLE_NAV_MENU_UPDATE_FILTERS')) {
            if (did_action('wp_update_nav_menu') || did_action('wp_update_nav_menu_item')) {
                $use_clauses = $orig_clauses;
            }
        }

        return $use_clauses;
    }
}
