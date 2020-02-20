<?php
namespace PublishPress\Permissions\Collab\UI\Dashboard;

//use \PublishPress\Permissions\Collab as Collab;
//use \PublishPress\Permissions\Collab\UI as UI;

class DashboardFilters
{
    function __construct()
    {
        global $pagenow;

        define('PRESSPERMIT_COLLAB_URLPATH', plugins_url('', PRESSPERMIT_COLLAB_FILE));

        if ('nav-menus.php' == $pagenow) {  // Administrators also need this, to add private posts to available items list
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Dashboard/NavMenuQuery.php');
            new NavMenuQuery();
        }

        if (('index.php' == $pagenow) && !defined('USE_RVY_RIGHTNOW')) {
            include_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Dashboard/DashboardWidgetsFilters.php');
            new DashboardWidgetsFilters();
        }

        wp_enqueue_style('presspermit-collab', PRESSPERMIT_COLLAB_URLPATH . '/common/css/collab.css', [], PRESSPERMIT_COLLAB_VERSION);

        if (presspermitPluginPage()) {
            wp_enqueue_style('presspermit-collab-plugin-pages', PRESSPERMIT_COLLAB_URLPATH . '/common/css/plugin-pages.css', [], PRESSPERMIT_COLLAB_VERSION);
        }

        add_action('admin_head', [$this, 'actAdminHead']);
        add_action('admin_print_footer_scripts', [$this, 'quickpress_workaround'], 99);

        add_action('presspermit_permissions_menu', [$this, 'permissions_menu'], 10, 2);
        add_action('presspermit_menu_handler', [$this, 'menu_handler']);

        add_action('presspermit_term_edit_ui', [$this, 'term_edit_ui']);
        add_action('presspermit_post_edit_ui', [$this, 'post_edit_ui']);
        add_action('presspermit_post_listing_ui', [$this, 'post_listing_ui']);
        add_action('presspermit_options_ui', [$this, 'optionsUI']);

        add_filter('presspermit_term_include_clause', [$this, 'flt_term_include_clause'], 10, 2);

        add_filter('presspermit_post_status_types', [$this, 'flt_status_links'], 5);

        if (defined('REVISIONARY_VERSION')) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Revisionary/PostFilters.php');
            new \PublishPress\Permissions\Collab\Revisionary\PostFilters();
        }

        if ('users.php' == $pagenow) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Dashboard/Users.php');
            new Users();
        }
    }

    function flt_term_include_clause($clause, $args = [])
    {
        if (empty($args['required_operation']) || ('read' == $args['required_operation'])) {
            $defaults = ['src_table' => '', 'required_operation' => ''];
            $args = array_merge($defaults, $args);
            foreach (array_keys($defaults) as $var) {
                $$var = $args[$var];
            }

            // Ensure that Gutenberg contributors can edit their own newly created draft
            $clause = "( ( $clause ) OR "
            . "( $src_table.post_status = 'auto-draft' AND $src_table.post_author = '" . presspermit()->getUser()->ID . "' ) )";
        }

        return $clause;
    }

    function menu_handler($pp_page)
    {
        if (in_array($pp_page, ['presspermit-role-usage', 'presspermit-role-usage-edit'], true)) {
            $class_name = str_replace('-', '', ucwords( str_replace('presspermit-', '', $pp_page), '-') );

            require_once(PRESSPERMIT_COLLAB_CLASSPATH . "/UI/{$class_name}.php");
            $load_class = "\\PublishPress\Permissions\\Collab\\UI\\$class_name";
            new $load_class();
        }
    }

    function permissions_menu($pp_options_menu, $handler)
    {
        if (presspermit()->getOption('advanced_options'))
            add_submenu_page(
                $pp_options_menu, 
                __('Role Usage', 'press-permit-core'), 
                __('Role Usage', 'press-permit-core'), 
                'read', 
                'presspermit-role-usage', 
                $handler
            );

        // satisfy WordPress' demand that all admin links be properly defined in menu
        $pp_plugin_page = presspermitPluginPage();

        if (in_array($pp_plugin_page, ['presspermit-role-usage-edit'], true)) {
            $permissions_menu = presspermit()->admin()->getMenuParams('permits');
            $titles = ['presspermit-role-usage-edit' => __('Edit Role Usage', 'press-permit-core')];
            add_submenu_page($permissions_menu, $titles[$pp_plugin_page], '', 'read', $pp_plugin_page, $handler);
        }
    }

    function actAdminHead()
    {
        if (!empty($_REQUEST['page']) && ('presspermit-role-usage' == $_REQUEST['page'])) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/RoleUsageListTable.php');
            \PublishPress\Permissions\Collab\UI\RoleUsageListTable::instance();
        }
    }

    function quickpress_workaround()
    {  // need this for multiple qp entries by limited user
        if (!presspermit()->isUserUnfiltered()) :
            ?>
            <?php
            preg_match('/MSIE (.*?);/', $_SERVER['HTTP_USER_AGENT'], $matches);
            $ie_version = (count($matches)) ? $matches[1] : 0;
            if (!$ie_version || ($ie_version >= 9)) :
                ?>
                <script type="text/javascript">
                    /* <![CDATA[ */
                    if (typeof wp == 'undefined') {
                        var wp = new Object();
                        wp.media = new Object();
                        wp.media.view = new Object();
                        wp.media.view.settings = new Object();
                        wp.media.view.settings.post = new Object();

                        wp.media.editor = new Object();
                        wp.media.editor.remove = new Function();
                        wp.media.editor.add = new Function();
                    }
                    /* ]]> */
                </script>
            <?php endif; ?>
        <?php
        endif;
    }

    function optionsUI()
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/SettingsTabEditing.php');
        new \PublishPress\Permissions\Collab\UI\SettingsTabEditing();
    }

    function post_listing_ui()
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Dashboard/PostsListing.php');
        new PostsListing();

        if (!presspermit()->isUserUnfiltered()) {
            // listing filter used for role status indication in edit posts/pages
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Dashboard/PostsListingNonAdministrator.php');
            new PostsListingNonAdministrator();
        }
    }

    function post_edit_ui()
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Dashboard/PostEdit.php');
        new PostEdit();

        if (PWP::isBlockEditorActive()) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Gutenberg/PostEdit.php');
            new \PublishPress\Permissions\Collab\UI\Gutenberg\PostEdit();
        }
    }

    function term_edit_ui()
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/Dashboard/TermEdit.php');
        new TermEdit();
    }

    function flt_status_links($links)
    {
        if (current_user_can('pp_define_post_status') || current_user_can('pp_define_moderation')) {
            $links = array_merge(
                [(object)[
                    'attrib_type' => 'moderation', 
                    'url' => "admin.php?page=presspermit-statuses&attrib_type=moderation", 
                    'label' => __('Workflow', 'press-permit-core')
                    ]
                ],
                $links
            );
        }
        return $links;
    }
}
