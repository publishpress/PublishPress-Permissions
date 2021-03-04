<?php

namespace PublishPress;

class PermissionsHooksAdmin
{
    private $config_updated = false;

    public function __construct()
    {
        //add_action('wp_dashboard_setup', [$this, '_getVersionInfo']);  // retrieve version info in case there are any alerts
        add_action('presspermit_duplicate_module', [$this, 'duplicateModule'], 10, 2);

        add_filter('presspermit_pattern_roles', [$this, 'fltPatternRoles']);

        add_action('admin_menu', [$this, 'actSettingsPageMaybeRedirect'], 999);

        if (defined('SSEO_VERSION')) {
            require_once(PRESSPERMIT_CLASSPATH . '/Compat/EyesOnlyAdmin.php');
            new Permissions\Compat\EyesOnlyAdmin();
        }

        // make sure empty terms are included in quick search results in "Set Specific Permissions" term selection metaboxes
        if (PWP::isAjax('pp-menu-quick-search')) {
            require_once(PRESSPERMIT_CLASSPATH.'/UI/ItemsMetabox.php' );
            add_action('wp_ajax_' . sanitize_key($_REQUEST['action']), ['\PublishPress\Permissions\UI\ItemsMetabox', 'ajax_menu_quick_search'], 1);
        }

        // thanks to GravityForms for the nifty dismissal script
        if (in_array(basename($_SERVER['PHP_SELF']), ['admin.php', 'admin-ajax.php'])) {
            add_action('wp_ajax_pp_dismiss_msg', [$this, 'dashboardDismissMsg']);
        }

        if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION')) {
            add_filter('authors_default_author', [$this, 'fltAuthorsPreventPostCreationLockout'], 99, 2);
            add_action('save_post', [$this, 'actAuthorsPreventPostUpdateLockout'], 1, 3);
        }

        add_action('presspermit_admin_ui', [$this, 'act_revisions_dependency']);

        add_filter('presspermit_exception_item_update_hooks', function($var) {return true;});
        add_filter('presspermit_exception_item_insertion_hooks', function($var) {return true;});
        add_filter('presspermit_exception_item_deletion_hooks', function($var) {return true;});

        add_action('presspermit_exception_items_updated', [$this, 'actPluginSettingsUpdated']);
        add_action('presspermit_inserted_exception_item', [$this, 'actPluginSettingsUpdated']);
        add_action('pp_inserted_exception_item', [$this, 'actPluginSettingsUpdated']);
        add_action('presspermit_removed_exception_items', [$this, 'actPluginSettingsUpdated']);

        add_action('presspermit_admin_handlers', [$this, 'actPluginSettingsUpdated']);
        add_action('presspermit_activate', [$this, 'actPluginSettingsUpdated']);
        add_action('shutdown', [$this, 'actConfigUpdateFollowup']);
    }

    public function init()
    {
        if (presspermit()->isPro()) {
            require_once(PRESSPERMIT_PRO_ABSPATH . '/includes-pro/pro-maint.php');
            Permissions\PressPermitMaint::adminRedirectCheck();
        }

        require_once(PRESSPERMIT_CLASSPATH . '/CapabilityFiltersAdmin.php');
        new Permissions\CapabilityFiltersAdmin();

        if (presspermitPluginPage()) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/PluginPage.php');
            Permissions\UI\PluginPage::instance();
        }

        if (!empty($_POST) || !empty($_REQUEST['action']) || !empty($_REQUEST['action2']) || !empty($_REQUEST['pp_action'])) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/Handlers/Admin.php');
            Permissions\UI\Handlers\Admin::handleRequest();
        }

        add_action('wp_loaded', [$this,'actLoadAjaxHandler'], 20);

        if (
            !empty($_POST['presspermit_submit']) || !empty($_POST['presspermit_defaults']) || !empty($_POST['pp_role_usage_defaults'])
            || !empty($_REQUEST['presspermit_refresh_updates']) || !empty($_REQUEST['pp_renewal'])
        ) {
            // For 'settings' admin panels, handle updated options right after current_user load (and before pp_init).
            // By then, check_admin_referer is available, but PP config and WP admin menu has not been loaded yet.
            require_once(PRESSPERMIT_CLASSPATH . '/UI/Handlers/Settings.php');
            new Permissions\UI\Handlers\Settings();
        }

        if (isset($_GET['pp_agent_search'])) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/AgentsAjax.php');
            new Permissions\UI\AgentsAjax();
            exit;
        }
    }

    public function actPluginSettingsUpdated() {
        $this->config_updated = true;
    }

    public function actConfigUpdateFollowup() {
        if ($this->config_updated) {
            $this->wpeCacheFlush();
        }
    }

    /**
     * Full WP Engine cache flush (Hold for possible future use as needed)
     *
     * Based on WP Engine Cache Flush by Aaron Holbrook
     * https://github.org/a7/wpe-cache-flush/
     * http://github.org/a7/
     */
    public function wpeCacheFlush() {
        // Don't cause a fatal if there is no WpeCommon class
        if ( ! class_exists( 'WpeCommon' ) ) {
            return false;
        }

        if ( function_exists( 'WpeCommon::purge_memcached' ) ) {
            \WpeCommon::purge_memcached();
        }

        if ( function_exists( 'WpeCommon::clear_maxcdn_cache' ) ) {
            \WpeCommon::clear_maxcdn_cache();
        }

        if ( function_exists( 'WpeCommon::purge_varnish_cache' ) ) {
            \WpeCommon::purge_varnish_cache();
        }

        global $wp_object_cache;
        // Check for valid cache. Sometimes this is broken -- we don't know why! -- and it crashes when we flush.
        // If there's no cache, we don't need to flush anyway.

        if ( !empty($wp_object_cache) && is_object( $wp_object_cache ) ) {
            @wp_cache_flush();
        }
    }

    public function actLoadAjaxHandler()
    {
        foreach (['item', 'agent_roles', 'agent_exceptions', 'agent_permissions', 'user', 'settings', 'items_metabox'] as $ajax_type) { // @todo: term_ui ?
            if (isset($_REQUEST["pp_ajax_{$ajax_type}"])) {
                $class_name = str_replace('_', '', ucwords( $ajax_type, '_') ) . 'Ajax';
                
                $class_parent = ( in_array($class_name, ['ItemAjax','UserAjax']) ) ? 'Dashboard' : '';
                
                $require_path = ( $class_parent ) ? "{$class_parent}/" : '';
                require_once(PRESSPERMIT_CLASSPATH . "/UI/{$require_path}{$class_name}.php");
                
                $load_class = "\\PublishPress\Permissions\UI\\";
                $load_class .= ($class_parent) ? $class_parent . "\\" . $class_name : $class_name;

                new $load_class();

                exit;
            }
        }
    }

    public function duplicateModule($ext_slug, $ext_folder)
    {
        presspermit()->admin()->errorNotice('duplicate_module', ['module_slug' => $ext_slug, 'module_folder' => $ext_folder]);
    }

    public function fltPatternRoles($roles)
    {
        $roles['subscriber']->labels = (object)['name' => __('Subscribers', 'press-permit-core'), 'singular_name' => __('Subscriber', 'press-permit-core')];
        $roles['contributor']->labels = (object)['name' => __('Contributors', 'press-permit-core'), 'singular_name' => __('Contributor', 'press-permit-core')];
        $roles['author']->labels = (object)['name' => __('Authors', 'press-permit-core'), 'singular_name' => __('Author', 'press-permit-core')];
        $roles['editor']->labels = (object)['name' => __('Editors', 'press-permit-core'), 'singular_name' => __('Editor', 'press-permit-core')];

        return $roles;
    }

    function act_revisions_dependency() {
        global $pagenow;

        if (defined('REVISIONARY_VERSION')) {
            if (!defined('PRESSPERMIT_COLLAB_VERSION')) {
                if (!presspermitPluginPage() && (empty($_REQUEST['page']) || !in_array($_REQUEST['page'], ['revisionary-q', 'revisionary-settings'])) && ('edit.php' !== $pagenow)) {
                    return;
                }

                $msg = current_user_can('pp_manage_settings')
                ? sprintf(
                    __('Please %senable the Collaborative Publishing module%s for PublishPress Revisions integration.', 'press-permit-core'),
                    '<a href="' . admin_url('admin.php?page=presspermit-settings') . '" style="text-decoration:underline">',
                    '</a>'
                )
                : __('PublishPress Revisions integration requires the Collaborative Publishing module. Please notify your Administrator.', 'press-permit-core');

                presspermit()->admin()->notice($msg);
            }
        }
    }

    // For old extensions linking to page=pp-settings.php, redirect to page=presspermit-settings, preserving other request args
    public function actSettingsPageMaybeRedirect()
    {
        foreach ([
                     'pp-settings' => 'presspermit-settings',
                     'pp-groups' => 'presspermit-groups',
                     'pp-group-new' => 'presspermit-group-new',
                     'pp-users' => 'presspermit-users',
                     'pp-edit-permissions' => 'presspermit-edit-permissions',
                 ] as $old_slug => $new_slug) {
            if (
                strpos($_SERVER['REQUEST_URI'], "page=$old_slug")
                && (false !== strpos($_SERVER['REQUEST_URI'], 'admin.php'))
            ) {
                global $submenu;

                // Don't redirect if pp-settings is registered by another plugin or theme
                foreach (array_keys($submenu) as $i) {
                    foreach (array_keys($submenu[$i]) as $j) {
                        if (isset($submenu[$i][$j][2]) && ($old_slug == $submenu[$i][$j][2])) {
                            return;
                        }
                    }
                }

                $arr_url = parse_url($_SERVER['REQUEST_URI']);
                wp_redirect(admin_url('admin.php?' . str_replace("page=$old_slug", "page=$new_slug", $arr_url['query'])));
                exit;
            }
        }
    }

    public function dashboardDismissMsg()
    {
        $dismissals = get_option('presspermit_dismissals');
        if (!is_array($dismissals)) {
            $dismissals = [];
        }

        $msg_id = (isset($_REQUEST['msg_id'])) ? $_REQUEST['msg_id'] : 'post_blockage_priority';
        $dismissals[$msg_id] = true;
        update_option('presspermit_dismissals', $dismissals);
    }

    // Prevent users lacking edit_others capability from being locked out of their newly created post due to Authors' "default author for new posts" setting
    function fltAuthorsPreventPostCreationLockout($default_author, $post) {
        global $current_user;

        if (presspermit()->isAdministrator()) {
            return $default_author;
        }
        
        if ($post_type = PWP::findPostType()) {
            if ($type_obj = get_post_type_object($post_type)) {
                if (!empty($type_obj->cap->edit_others_posts) && !current_user_can($type_obj->cap->edit_others_posts)) {
                    if (apply_filters('presspermit_override_default_author', true, ['post_type' => $post_type])) {
                        return $current_user->ID;
                    }
                }
            }
        }

        return $default_author;
    }

    function actAuthorsPreventPostUpdateLockout($post_id, $post, $update) {
        global $current_user;
        
        if (!$update
        || presspermit()->isAdministrator() 
        || !function_exists('get_multiple_authors') 
        || !function_exists('is_multiple_author_for_post')
        || !isset($_POST['authors'])
        || !apply_filters('presspermit_maybe_override_authors_change', true, $post)
        ) {
            return;
        }
        
        if ($post_type = PWP::findPostType()) {
            if ($type_obj = get_post_type_object($post_type)) {
                if (!empty($type_obj->cap->edit_others_posts) 
                && empty($current_user->allcaps[$type_obj->cap->edit_others_posts])
                && is_multiple_author_for_post($current_user, $post_id)
                && method_exists('MultipleAuthors\Classes\Objects\Author', 'get_by_user_id')
                ) {
                    if (!$current_author = \MultipleAuthors\Classes\Objects\Author::get_by_user_id($current_user->ID)) {
                        return;
                    }

                    if (!in_array($current_author->term_id, $_POST['authors'])) {
                        if (apply_filters('presspermit_override_authors_change', true, $post)) {
                            $_POST['authors'] = array_merge([strval($current_author->term_id)], $_POST['authors']);
                        }
                    }
                }
            }
        }
    }
}
