<?php

namespace PublishPress;

class PermissionsHooks
{
    // object references
    private $admin_hooks;
    private $cap_filters;

    // status
    public $direct_file_access = false;
    private $filters_loaded = false;
    private $post_filters_loaded = false;
    private $filtering_enabled = true;

    public function __construct($args = [])
    {
        $defaults = ['load_filters' => true];
        $args = array_merge($defaults, (array)$args);

        if (is_multisite()) {
            add_action('switch_blog', [$this, 'actSwitchBlog']);
        }

        add_filter('presspermit_options', [$this, 'fltForceAdvancedDefaults']);

        // REST logging and blockers
        add_filter('rest_pre_dispatch', [$this, 'fltRestPreDispatch'], 10, 3);

        if ($args['load_filters']) {
            $this->loadFilters();
        }

		if (presspermit()->isPro()) {
        	add_action('admin_init', [$this, 'loadUpdater']);
        }
        
        add_action('user_has_cap', [$this, 'fltEarlyUserHasCap'], 50, 3);

        // filter pre_option_category_children to disable/enable terms filtering
        foreach (presspermit()->getEnabledTaxonomies(['object_type' => false]) as $taxonomy) {
            add_action("pre_update_option_{$taxonomy}_children", [$this, 'actClearTermChildrenCache'], 99, 3);
            add_action("update_option_{$taxonomy}_children", [$this, 'actClearTermChildrenCache'], 99, 3);
        }
    }

	public function loadUpdater() {
        return presspermit()->load_updater();
    }

    public function filtersLoaded()
    {
        return $this->filters_loaded;
    }

    public function filteringEnabled()
    {
        return $this->filtering_enabled;
    }

    // if Advanced Options are not enabled, ignore stored settings
    public function fltForceAdvancedDefaults($options)
    {
        if (!presspermit()->getOption('advanced_options')) {
            foreach (presspermit()->default_advanced_options as $option_basename => $val) {
                $options["presspermit_{$option_basename}"] = $val;
            }
        }
        return $options;
    }

    public function loadFilters()
    {
        add_action('set_current_user', [$this, 'actSetCurrentUser'], 99);
        add_action('init', [$this, 'actInit'], 50);
        add_action('wp_loaded', [presspermit(), 'refreshUserAllcaps'], 18);   // account for any type / condition caps adding by late registration

        if (is_admin()) {
            require_once(PRESSPERMIT_ABSPATH . '/classes/PublishPress/PermissionsHooksAdmin.php');
            $this->admin_hooks = new PermissionsHooksAdmin();
        } else {
            add_action('presspermit_pre_init', [$this, 'actMediaFilters']);
        }

        require_once(PRESSPERMIT_CLASSPATH . '/Roles.php');
        presspermit()->role_defs = new Permissions\Roles();

        if (defined('SSEO_VERSION')) {
            require_once(PRESSPERMIT_CLASSPATH . '/Compat/EyesOnly.php');
            new Permissions\Compat\EyesOnly();
        }

        if (did_action('set_current_user')) { // sometimes third party code causes user to be loaded prematurely
            $this->actSetCurrentUser();
        }

        if (defined('PRESSPERMIT_NO_USER_LOCALE')) {
            // Prevent numerous user queries with Block Editor
            add_filter('pre_determine_locale', function($locale) {return get_locale();});
        }
    }

    // log request and handler parameters for possible reference by subsequent PP filters; block unpermitted create/edit/delete requests 
    function fltRestPreDispatch($rest_response, $rest_server, $request)
    {
        /*
        if (presspermit()->isContentAdministrator()) {
            return $rest_response;
        }
        */

        require_once(PRESSPERMIT_CLASSPATH . '/REST.php');
        return Permissions\REST::instance()->pre_dispatch($rest_response, $rest_server, $request);
    }

    public function actSwitchBlog()
    {
        require(PRESSPERMIT_ABSPATH . '/db-config.php');
    }

    public function actMediaFilters()
    {
        if (in_array('attachment', presspermit()->getEnabledPostTypes(), true)) {
            require_once(PRESSPERMIT_CLASSPATH . '/MediaFilters.php');
            new Permissions\MediaFilters();
        }
    }

    public function actSetCurrentUser()
    {
        global $current_user;

        if (presspermit()->checkInitInterrupt()) {
            return;
        }

        presspermit()->setUser($current_user->ID);

        if (isset($this->cap_filters)) {
            $this->cap_filters->clearMemcache();
        }

        if (did_action('init')) {
            // reload certain filters and configuration data on user change
            $this->actInitUser();
        } else {
            // late priority because actInit() and 3rd party filters related to type / taxonomy / cap definitions must execute first
            add_action('init', [$this, 'actNormalInitUser'], 70);
        }
    }

    public function fltEarlyUserHasCap($wp_sitecaps, $orig_reqd_caps, $args)
    {
        // Deal with plugins (WP Bakery Page Builder) that apply an 'edit_post' metacap check on the init action
        if (isset($args[0]) && in_array($args[0], ['edit_post']) && !did_action('presspermit_init_user_complete')) {
            $this->actInitUser();
        }

        return $wp_sitecaps;
    }

    public function actNormalInitUser() {
        $this->actInitUser();
        do_action('presspermit_init_user_complete');
    }

    // executes late on the 'init' action (priority 50)
    public function actInit()
    {
        static $done = null;

        if (!is_null($done)) {
            return;
        }
        $done = true;

        $pp = presspermit();

        // --- version check ---
        if ( ! $ver = get_option('presspermitpro_version') ) {
            if (!defined('PRESSPERMIT_PRO_VERSION')) {
            	if ( ! $ver = get_option('presspermit_version') ) {
                	$ver = get_option('pp_c_version');
                }
            }
        }

        if (!$ver || empty($ver['db_version']) || version_compare(PRESSPERMIT_DB_VERSION, $ver['db_version'], '!=')) {
            //require_once(PRESSPERMIT_CLASSPATH . '/DB/DatabaseSetup.php');  // do this earlier
            //new Permissions\DB\DatabaseSetup($ver['db_version']);

            if (!$ver) {
                require_once(PRESSPERMIT_CLASSPATH . '/PluginUpdated.php');
                new Permissions\PluginUpdated('');
            }

            update_option('presspermit_version', ['version' => PRESSPERMIT_VERSION, 'db_version' => PRESSPERMIT_DB_VERSION]);

            if (defined('PRESSPERMIT_PRO_VERSION')) {
                update_option('presspermitpro_version', ['version' => PRESSPERMIT_PRO_VERSION, 'db_version' => PRESSPERMIT_DB_VERSION]);
            }
        }

        if ($ver && !empty($ver['version'])) {
            // These maintenance operations only apply when a previous version of PP was installed 
            if (version_compare(PRESSPERMIT_VERSION, $ver['version'], '!=')) {
                require_once(PRESSPERMIT_CLASSPATH . '/PluginUpdated.php');
                new Permissions\PluginUpdated($ver['version']);
                update_option('presspermit_version', ['version' => PRESSPERMIT_VERSION, 'db_version' => PRESSPERMIT_DB_VERSION]);

                if (defined('PRESSPERMIT_PRO_VERSION')) {
                    update_option('presspermitpro_version', ['version' => PRESSPERMIT_PRO_VERSION, 'db_version' => PRESSPERMIT_DB_VERSION]);
                }
            }

            if (is_multisite() && !$pp->getOption('wp_role_sync')) {
                require_once(PRESSPERMIT_CLASSPATH . '/PluginUpdated.php');
                Permissions\PluginUpdated::syncWordPressRoles();
            }
        } /*else { // execute earlier
            // first execution after install
            if (!get_option('ppperm_added_role_caps_21beta')) {
                require_once(PRESSPERMIT_CLASSPATH . '/PluginUpdated.php');
                Permissions\PluginUpdated::populateRoles(true);
            }
        }
        */
        // --- end version check ---

        // already loaded these early, so apply filter again for modules
        $pp->default_options = apply_filters('presspermit_default_options', $pp->default_options);
        $pp->default_advanced_options = apply_filters('presspermit_default_advanced_options', $pp->default_advanced_options);
        $pp->default_options = array_merge($pp->default_options, $pp->default_advanced_options);

        $pp->site_options = apply_filters('presspermit_options', $pp->site_options);

        if (is_multisite()) {
            $opts = ['edd_key', 'beta_updates'];
            $pp->netwide_options = apply_filters('presspermit_netwide_options', $opts);
        }

        // Capabilities() instantiation forces type-specific cap names for enabled post types and taxonomies
        $pp->capDefs();

        do_action('presspermit_pre_init');

        if (is_admin()) {
            @load_plugin_textdomain('press-permit-core', false, dirname(plugin_basename(PRESSPERMIT_FILE)) . '/languages');

            $this->admin_hooks->init();
        }
    }

    // Load filters that depend on user capabilities. Executes very late on the 'init' action (priority 70)
    public function actInitUser()
    {
        $pp = presspermit();

        if (!did_action('init') || !$pp->isUserSet()) {
            return;
        }

        // determine if query filtering has been disabled by option storage or API
        if (($pp->isUserUnfiltered() && !is_user_logged_in()) || (defined('DOING_CRON') && PWP::doingCron())) {
            $this->filtering_enabled = false;
        }

        // Don't filter legacy / development versions of REST api unless constant defined
        if (
            defined('JSON_API_VERSION') && !defined('PP_FILTER_JSON_REST')
            && (false !== strpos($_SERVER['REQUEST_URI'], apply_filters('json_url_prefix', 'wp-json')))
        ) {
            return;
        }

        if (!$this->filters_loaded) {
            // Normal execution: loadInitFilters() will call loadContentFilters()
            $this->loadInitFilters();
        } else {
            // Support content filter reload on user change, without reloading filters that are loaded regardless of user capabilities
            $this->loadContentFilters();
        }

        // retrieve BP groups and other group types registered by 3rd party  @todo: default retrieve_site_roles arg to false?
        $pp_user = $pp->getUser(false, '', ['retrieve_site_roles' => false]);
        $pp_user->retrieveExtraGroups();
        $pp_user->getSiteRoles();
        
        $pp->refreshUserAllcaps();

        do_action('presspermit_user_init');
    }

    private function loadInitFilters()
    {
        global $pagenow;

        do_action('presspermit_register_role_attributes');

        presspermit()->role_defs->defineRoles();

        do_action('presspermit_roles_defined');

        // determine if query filtering has been disabled by option storage or API
        if (defined('DOING_CRON') && PWP::doingCron()) {
            $this->filtering_enabled = false;
        }

        if ($no_filter_uris = apply_filters('presspermit_nofilter_uris', [])) {
            if (in_array($pagenow, $no_filter_uris, true) || in_array(presspermitPluginPage(), (array)$no_filter_uris, true)) {
                $this->filtering_enabled = false;
            }
        }

        if (did_action('presspermit_load_error') && defined('PRESSPERMIT_DISABLE_QUERYFILTERS')) {
            $this->filtering_enabled = false;
        }

        if (!$this->direct_file_access = isset($_REQUEST['pp_rewrite']) && !empty($_REQUEST['attachment'])) {
            $this->addMaintenanceTriggers();
        }

        $this->filters_loaded = true;

        // no further filtering on update requests for other plugins 
        if (is_admin() && ('update.php' == $pagenow)) {
            // @todo: review with EDD

            if ( empty($_REQUEST['action']) || ('presspermit-pro' != $_REQUEST['action'] ) ) {
                do_action('presspermit_init');
                return;
            }
        }

        // content filters, loaded conditionally depending on whether the current user is a content administrator
        $this->loadContentFilters();

        if (is_admin() && ('async-upload.php' != $pagenow) && !defined('XMLRPC_REQUEST') && (!defined('DOING_AJAX') || !DOING_AJAX)) {
            // filters which are only needed for the wp-admin UI
            require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/DashboardFilters.php');
            new Permissions\UI\Dashboard\DashboardFilters();
        }

        add_filter('the_posts', [$this, 'fltPostsListing'], 50);
        add_action('admin_enqueue_scripts', [$this, 'fltAdminPostsListing'], 50);  // 'the_posts' filter is not applied on edit.php for hierarchical types

        if (!defined('PP_UNFILTERED_PAGE_URI'))
            add_filter('get_page_uri', [$this, 'fltGetPageUri'], 5, 2);

        do_action('presspermit_init');
    }

    public function addMaintenanceTriggers()
    {
        // ===== Filters which support automated role maintenance following content creation/update
        if (!defined('PP_NO_FRONTEND_ADMIN') || !PWP::isFront()) {  // advanced users can save some memory if no content/users will be edited via front end
            require_once(PRESSPERMIT_CLASSPATH . '/Triggers.php');
            new Permissions\Triggers();

            do_action('presspermit_maintenance_triggers');
        }
    }

    public function fltPostsListing($results)
    {
        $pp = presspermit();

        $default_type = PWP::findPostType();

        // buffer all IDs in the results set
        if ($results) { // JReviews plugin sets $results to null under some conditions
            foreach ($results as $row) {
                $post_type = (!isset($row->post_type) || ('revision' == $row->post_type)) ? $default_type : $row->post_type;
                $pp->listed_ids[$post_type][$row->ID] = true;
            }
        }
        
        return $results;
    }

    public function fltAdminPostsListing() {
		global $wp_query, $typenow;
		
		if ( ! empty( $wp_query->posts ) && empty( presspermit()->listed_ids[$typenow] ) ) {
			$this->fltPostsListing( $wp_query->posts );
		}
	}

    // restore pre-4.4 behavior of not requiring 'publish' status for inclusion in page uri hierarchy
    public function fltGetPageUri($uri, $page)
    {
        $page = get_post($page);

        if (!$page)
            return false;

        $uri = $page->post_name;

        foreach ($page->ancestors as $parent) {
            if ($_post = get_post($parent)) {
                $uri = $_post->post_name . '/' . $uri;
            }
        }

        return $uri;
    }

    // configuration / filter addition which depends on whether the current user is an Administrator
    private function loadContentFilters()
    {
        $pp = presspermit();

        if (!$pp->filteringEnabled()) {
            return;
        }

        // ===== Query Filters to limit/enable the current user
        global $pagenow;

        $is_unfiltered = $pp->isUserUnfiltered();
        $is_administrator = $pp->isContentAdministrator();

        // even users who are unfiltered in terms of their own access will normally have some of these filters applied to force inclusion of readable private posts in get_pages() listing, post counts, etc.
        if ($is_front = PWP::isFront()) {
            $front_filtering = !$is_unfiltered || !defined('PP_ALLOW_UNFILTERED_FRONT');
        }

        // (also use content filters on front end to FILTER IN private content which WP inappropriately hides from administrators)
        if (($is_front && $front_filtering) 
        || !$is_unfiltered 
        || ('nav-menus.php' == $pagenow) 
        || (defined('DOING_AJAX') && DOING_AJAX && !empty($_REQUEST['action']) && ('menu-quick-search' == $_REQUEST['action']))) {
            if (! $this->post_filters_loaded) { // since this could possibly fire on multiple 'set_current_user' calls, avoid redundancy
                require_once(PRESSPERMIT_CLASSPATH . '/PostFilters.php');
                Permissions\PostFilters::instance(['direct_file_access' => $this->direct_file_access]);
                $this->post_filters_loaded = true;
            }
        }

        if ($is_front && $front_filtering) {
            require_once(PRESSPERMIT_CLASSPATH . '/PostFiltersFront.php');
            new Permissions\PostFiltersFront();

            require_once(PRESSPERMIT_CLASSPATH . '/FrontFilters.php');
            new Permissions\FrontFilters();

            if ($is_unfiltered && $is_administrator) {
                require_once(PRESSPERMIT_CLASSPATH . '/CommentFiltersAdministrator.php');
                new Permissions\CommentFiltersAdministrator();
            }
        }

        if (!$is_unfiltered) {
            if (!isset($this->cap_filters)) {
                require_once(PRESSPERMIT_CLASSPATH . '/CapabilityFilters.php');
                $this->cap_filters = new Permissions\CapabilityFilters();
            }

            require_once(PRESSPERMIT_CLASSPATH . '/CommentFilters.php');
            new Permissions\CommentFilters();

            // Legacy: This has never been referenced internally or by any extensions, but leave in case a custom implmentation looks at it.
            if (!defined('FILTERED_PP')) {
                define('FILTERED_PP', true);
            }
        }

        if (($is_front && $front_filtering) || (!$is_unfiltered && (!defined('DOING_AUTOSAVE') || !DOING_AUTOSAVE))) {
            require_once(PRESSPERMIT_CLASSPATH . '/TermFilters.php');
            new Permissions\TermFilters();
        } elseif (is_admin() && $is_unfiltered) {
            require_once(PRESSPERMIT_CLASSPATH . '/TermFiltersAdministrator.php');  // for filtering of post count
            new Permissions\TermFiltersAdministrator();
        }

        if (
            !$this->direct_file_access && (!$is_front || $front_filtering)
            && (!defined('XMLRPC_REQUEST') || !$is_administrator)
        ) {  // don't add for direct file access or administrator XML-RPC
            if (!is_admin() || !$pp->isContentAdministrator() || !defined('PP_GET_PAGES_LIMIT_ADMIN_FILTERING')) {
                $priority = (defined('PP_GET_PAGES_PRIORITY')) ? PP_GET_PAGES_PRIORITY : 1;
                add_filter('get_pages', [$this, 'fltGetPages'], $priority, 2);
            }
        }
    }

    public function fltGetPages($pages, $args)
    {
        if (!isset($args['post_type'])) {
            return $pages;
        }

        require_once(PRESSPERMIT_CLASSPATH . '/PageFilters.php');
        do_action('presspermit_page_filters');

        return Permissions\PageFilters::fltGetPages($pages, $args);
    }

    public function actClearTermChildrenCache($children, $option_val, $option_name)
    {  // fires on pre_update_option_$taxonomy filter
        if (defined('DOING_AJAX') && DOING_AJAX) {
            delete_option($option_name);
        }
    }
} // end class
