<?php

namespace PublishPress\Permissions\UI\Dashboard;

//use \PublishPress\Permissions\UI as UI;
//use \PressShack\LibWP as PWP;

// menu icons by Jonas Rask: https://www.jonasraskdesign.com/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

define('PRESSPERMIT_URLPATH', plugins_url('', PRESSPERMIT_FILE));

class DashboardFilters
{
    public function __construct()
    {
        global $pagenow;

        do_action('_presspermit_admin_ui');

        // ============== UI-related filters ================
        add_action('admin_menu', [$this, 'actBuildMenu'], 21);

        add_action('show_user_profile', [$this, 'actUserUi'], 2);
        add_action('edit_user_profile', [$this, 'actUserUi'], 2);
        add_action('admin_print_scripts-user-new.php', [$this, 'actInsertGroupsUi']);

        add_action('admin_menu', [$this, 'actNggUploaderWorkaround']);

        $is_post_admin = false;

        $pp_plugin_page = presspermitPluginPage();

        if (array_intersect([$pagenow], ['post-new.php', 'post.php'])) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/PostEdit.php');
            new PostEdit();
            $is_post_admin = true;

        } elseif (('term.php' == $pagenow) || (('edit-tags.php' == $pagenow)
                && !empty($_REQUEST['action']) && ('edit' == $_REQUEST['action']))
        ) {
            if (current_user_can('pp_assign_roles')) {
                require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/TermEdit.php');
                new TermEdit();
            }
        }

        if ('users.php' == $pagenow) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/UsersListing.php');
            new UsersListing();

        } elseif (('edit.php' == $pagenow) || PWP::isAjax('inline-save')) {
            $post_type = isset($_REQUEST['post_type']) ? sanitize_key($_REQUEST['post_type']) : 'post';
            if (in_array($post_type, presspermit()->getEnabledPostTypes(), true)) {
                require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/PostsListing.php');
                new PostsListing();
                $is_post_admin = true;
            }
        } elseif (
            in_array($pagenow, ['edit-tags.php']) || (defined('DOING_AJAX') && DOING_AJAX
                && isset($_REQUEST['action']) && in_array($_REQUEST['action'], ['inline-save-tax', 'add-tag']))
        ) {
            if (!empty($_REQUEST['taxonomy']) && presspermit()->isTaxonomyEnabled($_REQUEST['taxonomy'])) {
                require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/TermsListing.php');
                new TermsListing();
            }
        } elseif (in_array($pagenow, ['plugins.php', 'plugin-install.php'])) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/PluginAdmin.php');
            new PluginAdmin();
        } else {
            if (strpos($_SERVER['REQUEST_URI'], 'page=presspermit-groups') && isset($_REQUEST['wp_screen_options'])) {
                \PublishPress\Permissions\UI\PluginPage::handleScreenOptions();
            }

            if ('presspermit-edit-permissions' == $pp_plugin_page) {
                add_action('admin_head', [$this, 'actLoadScripts']);

            } elseif ('presspermit-settings' == $pp_plugin_page) {
                wp_enqueue_style('plugin-install');
                wp_enqueue_script('plugin-install');
                add_thickbox();

                if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION') && !version_compare(PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION, '3.8.0', '>=')) {
                    require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/PluginAdmin.php');
                    PluginAdmin::authorsVersionNotice(['ignore_dismissal' => true]);
                }
            } elseif ('plugins.php' == $pagenow) {
                add_thickbox();
            }
        }

        if ($is_post_admin) {
            do_action('presspermit_post_admin');
        }

        add_action('admin_head', [$this, 'actAdminHead']);

        wp_enqueue_style('presspermit', PRESSPERMIT_URLPATH . '/common/css/presspermit.css', [], PRESSPERMIT_VERSION);

        if ($pp_plugin_page || (!empty($_REQUEST['page']) && (0 === strpos($_REQUEST['page'], 'capsman')))) {
            wp_enqueue_style('presspermit-plugin-pages', PRESSPERMIT_URLPATH . '/common/css/plugin-pages.css', [], PRESSPERMIT_VERSION);
            wp_enqueue_style('presspermit-admin-common', PRESSPERMIT_URLPATH . '/common/css/pressshack-admin.css', [], PRESSPERMIT_VERSION);
        }

        if (in_array($pagenow, ['user-edit.php', 'user-new.php', 'profile.php'])) {
            wp_enqueue_style('presspermit-edit-permissions', PRESSPERMIT_URLPATH . '/common/css/edit-permissions.css', [], PRESSPERMIT_VERSION);
            wp_enqueue_style('presspermit-groups-checklist', PRESSPERMIT_URLPATH . '/common/css/groups-checklist.css', [], PRESSPERMIT_VERSION);
       
        } elseif (in_array($pp_plugin_page, ['presspermit-edit-permissions', 'presspermit-group-new'], true)) {
            wp_enqueue_style('presspermit-edit-permissions', PRESSPERMIT_URLPATH . '/common/css/edit-permissions.css', [], PRESSPERMIT_VERSION);
            wp_enqueue_style('presspermit-groups-checklist', PRESSPERMIT_URLPATH . '/common/css/groups-checklist.css', [], PRESSPERMIT_VERSION);
        } 
        
        if (('presspermit-settings' == presspermitPluginPage()) || (('plugin-install.php' == $pagenow)
            && strpos($_SERVER['HTTP_REFERER'], 'presspermit-settings'))
        ) {
            wp_enqueue_style('presspermit-settings', PRESSPERMIT_URLPATH . '/common/css/settings.css', [], PRESSPERMIT_VERSION);
        }

        if (in_array($pagenow, ['edit.php', 'post.php'])) {
            add_action('admin_menu', [$this, 'actReinstateSoloSubmenus']);
            add_action('network_admin_menu', [$this, 'actReinstateSoloSubmenus']);
        }

        do_action('presspermit_admin_ui');
    }

    public function actLoadScripts()
    {
        $pp = presspermit();

        $agent_type = ( isset($_REQUEST['agent_type']) ) ? sanitize_key($_REQUEST['agent_type']) : 'pp_group';
		$agent_id = ( isset($_REQUEST['agent_id']) ) ? (int) $_REQUEST['agent_id'] : 0;

        $load_role_scripts = $pp->groups()->userCan('pp_manage_members', $agent_id, $agent_type)
        || $pp->groups()->anyGroupManager() || current_user_can('pp_assign_roles')
        || $pp->admin()->bulkRolesEnabled();

        $load_exception_scripts = current_user_can('pp_assign_roles') || presspermit()->admin()->bulkRolesEnabled();

        if ( $load_role_scripts || $load_exception_scripts ) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/AgentPermissionsUI.php');
            
            if ( $load_role_scripts ) {
                \PublishPress\Permissions\UI\AgentPermissionsUI::roleAssignmentScripts();
            }

            if ( $load_exception_scripts ) {
                \PublishPress\Permissions\UI\AgentPermissionsUI::exceptionAssignmentScripts();
            }
        }
    }

    public function actReinstateSoloSubmenus()
    {
        global $submenu;

        // Add a dummy submenu item to prevent WP from stripping out solitary submenus.  
        // Otherwise menu access loses type sensitivity and requires "edit_posts" cap for all types.
        foreach (array_keys($submenu) as $key) {
            if (1 == count($submenu[$key]) && (0 === strpos($key, 'edit.php'))) {
                $submenu[$key][999] = ['', 'read', $key];
            }
        }
    }

    public static function actMenuHandler()
    {
        $pp_page = sanitize_key($_GET['page']);

        if (in_array($pp_page, [
            'presspermit-settings', 'presspermit-groups', 'presspermit-users',
            'presspermit-edit-permissions', 'presspermit-group-new',
        ], true)) {
            $class_name = ('presspermit-edit-permissions' == $pp_page)
            ? 'AgentPermissions' 
            : str_replace('-', '', ucwords( str_replace('presspermit-', '', $pp_page), '-') );

            require_once(PRESSPERMIT_CLASSPATH . "/UI/{$class_name}.php");
            $load_class = "\\PublishPress\Permissions\\UI\\$class_name";
            new $load_class();
        }

        do_action('presspermit_menu_handler', $pp_page);
    }

    public function actAdminHead()
    {
        global $pagenow;

        if (empty($_REQUEST['noheader'])) {
            global $wp_scripts;
            $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
            wp_enqueue_script('presspermit-misc', PRESSPERMIT_URLPATH . "/common/js/presspermit{$suffix}.js", ['jquery'], PRESSPERMIT_VERSION, true);
            $wp_scripts->in_footer[] = 'presspermit-misc'; // otherwise it will not be printed in footer @todo: review
        }

        if (('user-edit.php' == $pagenow) && presspermit()->getOption('display_user_profile_groups')) {
            add_thickbox();
        }
    }

    public function actBuildMenu()
    {
        if (strpos($_SERVER['REQUEST_URI'], 'wp-admin/network/')) {
            return;
        }

        $do_groups = current_user_can('pp_edit_groups') || presspermit()->groups()->anyGroupManager();
        $do_settings = current_user_can('pp_manage_settings');

        if (!$do_groups && !$do_settings) {
            return;
        }

        $admin = presspermit()->admin();

        $pp_cred_menu = $admin->getMenuParams('permits');
        $pp_options_menu = $admin->getMenuParams('options');

        if ('presspermit-groups' == $pp_cred_menu) {
            //  Manually set menu indexes for positioning below Users menu
            global $menu;

            /*
            $pp_cred_key = (!defined('PP_DISABLE_MENU_TWEAK') && !defined('OZH_MENU_VER')
                && isset($menu[70]) && $menu[70][2] == 'users.php' && !isset($menu[72]))
                ? 72 : null;
            */

            $permissions_title = __('Permissions', 'press-permit-core');

            $menu_order = 72;

            if (defined('PUBLISHPRESS_PERMISSIONS_MENU_GROUPING')) {
                foreach (get_option('active_plugins') as $plugin_file) {
                    if ( false !== strpos($plugin_file, 'publishpress.php') ) {
                        $menu_order = 27;
                    }
                }
            }

            add_menu_page(
                $permissions_title,
                $permissions_title,
                'read',
                $pp_cred_menu,
                [__CLASS__, 'actMenuHandler'],
                'dashicons-unlock',
                $menu_order
            );
        }

        $handler = [__CLASS__, 'actMenuHandler'];

        if ($do_groups) {
            add_submenu_page($pp_cred_menu, __('Groups', 'press-permit-core'), __('Groups', 'press-permit-core'), 'read', 'presspermit-groups', $handler);

            if (current_user_can('pp_create_groups') && ('presspermit-group-new' == presspermitPluginPage())) {
                add_submenu_page(
                    $pp_cred_menu,
                    __('Add New Permission Group', 'press-permit-core'),
                    '- ' . PWP::__wp('Add New'),
                    'read',
                    'presspermit-group-new',
                    $handler
                );
            }
        }

        if ($do_settings) {
            do_action('presspermit_permissions_menu', $pp_options_menu, $handler);

            $settings_caption = ('presspermit-groups' == $pp_options_menu)
                ? __('Settings', 'press-permit-core')
                : $permissions_title;

            add_submenu_page($pp_options_menu, $settings_caption, $settings_caption, 'read', 'presspermit-settings', $handler);
        }

        // register plugin pages not displayed as menu items
        $pp_plugin_page = presspermitPluginPage();

        if (in_array($pp_plugin_page, ['presspermit-edit-permissions'], true)) {
            $titles = ['presspermit-edit-permissions' => __('Edit Permissions', 'press-permit-core')];
            add_submenu_page(sanitize_key($permissions_title), $titles[$pp_plugin_page], '', 'read', $pp_plugin_page, $handler);
        }

        do_action('presspermit_admin_menu');
    }

    public function actUserUi()
    {
        global $profileuser;
        $user = presspermit()->getUser();
        $pp_profile_user = ($profileuser->ID == $user->ID) ? $user : new \PublishPress\PermissionsUser($profileuser->ID);

        $pp = presspermit();

        $is_administrator = $pp->isUserAdministrator() && $pp->admin()->bulkRolesEnabled() && current_user_can('list_users');

        if (
            $is_administrator || $pp->getOption('display_user_profile_roles')
            || $pp->getOption('display_user_profile_groups')
        ) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/Profile.php');
            require_once(PRESSPERMIT_CLASSPATH . '/UI/AgentPermissionsUI.php');
        }

        if ($is_administrator || $pp->getOption('display_user_profile_roles')) {
            Profile::displayUserAssignedRoles($pp_profile_user);
        }

        if ($is_administrator || $pp->getOption('display_user_profile_groups')) {
            Profile::displayUserGroups();
        }

        if ($is_administrator || $pp->getOption('display_user_profile_roles')) {
            Profile::displayUserRoles($pp_profile_user);
        }
    }

    public function actInsertGroupsUi()
    {
        if (is_multisite() || !presspermit()->getOption('new_user_groups_ui')) {
            return;
        }

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
        wp_enqueue_script('presspermit-new-user', PRESSPERMIT_URLPATH . "/common/js/new-user{$suffix}.js", [], PRESSPERMIT_VERSION);
        wp_localize_script('presspermit-new-user', 'ppUser', ['ajaxurl' => admin_url('')]);
    }

    // support NextGenGallery uploader and other custom jquery calls which WP treats as index.php ( otherwise user_can_access_admin_page() fails )
    // @todo: review
    public function actNggUploaderWorkaround()
    {
        global $pagenow;

        $site_url = parse_url(get_option('siteurl'));
        if (isset($site_url['path']) && $_SERVER['REQUEST_URI'] == $site_url['path'] . '/wp-admin/') {
            return;
        }

        if (('index.php' == $pagenow) && strpos($_SERVER['REQUEST_URI'], '.php')
            && !strpos($_SERVER['REQUEST_URI'], 'index.php')
        ) {
            $pagenow = '';
        }
    }

    public static function listAgentExceptions($agent_type, $id, $args = [])
    {
        require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/Profile.php');
        return Profile::listAgentExceptions($agent_type, $id, $args);
    }
} // end class
