<?php
namespace PublishPress\Permissions;

class CoreAdmin {
    function __construct() {
        add_action('presspermit_permissions_menu', [$this, 'actAdminMenuPromos'], 12, 2);
        add_action('presspermit_menu_handler', [$this, 'menuHandler']);

        add_action('presspermit_admin_menu', [$this, 'actAdminMenu'], 999);

        add_action('admin_enqueue_scripts', function() {
            if (presspermitPluginPage()) {
                wp_enqueue_style('presspermit-settings-free', plugins_url('', PRESSPERMIT_FILE) . '/includes/css/settings.css', [], PRESSPERMIT_VERSION);
            }

            if (in_array(presspermitPluginPage(), ['presspermit-statuses', 'presspermit-sync', 'presspermit-teaser'])) {
                wp_enqueue_style('presspermit-admin-promo', plugins_url('', PRESSPERMIT_FILE) . '/includes/promo/admin-core.css', [], PRESSPERMIT_VERSION, 'all');
            }
        });

        add_action('admin_print_scripts', [$this, 'setUpgradeMenuLink'], 50);

        $autoloadPath = PRESSPERMIT_ABSPATH . '/vendor/autoload.php';
        if (file_exists($autoloadPath)) {
            require_once $autoloadPath;
        }

        require_once PRESSPERMIT_ABSPATH . '/vendor/publishpress/wordpress-version-notices/includes.php';

        add_filter(\PPVersionNotices\Module\TopNotice\Module::SETTINGS_FILTER, function ($settings) {
            $settings['press-permit-core'] = [
                'message' => 'You\'re using PublishPress Permissions Free. The Pro version has more features and support. %sUpgrade to Pro%s',
                'link'    => 'https://publishpress.com/links/permissions-banner',
                'screens' => [
                    ['base' => 'toplevel_page_presspermit-groups'],
                    ['base' => 'permissions_page_presspermit-group-new'],
                    ['base' => 'permissions_page_presspermit-users'],
                    ['base' => 'permissions_page_presspermit-settings'],
                ]
            ];

            return $settings;
        });
    }

    function actAdminMenuPromos($pp_options_menu, $handler) {
        add_submenu_page(
            $pp_options_menu, 
            __('Post Statuses', 'press-permit-core'), 
            __('Post Statuses', 'press-permit-core'), 
            'read', 
            'presspermit-statuses', 
            $handler
        );

        add_submenu_page(
            $pp_options_menu, 
            __('Sync Posts', 'press-permit-core'), 
            __('Sync Posts', 'press-permit-core'), 
            'read', 
            'presspermit-sync', 
            $handler
        );

        add_submenu_page(
            $pp_options_menu, 
            __('Teaser', 'press-permit-core'), 
            __('Teaser', 'press-permit-core'), 
            'read', 
            'presspermit-teaser', 
            $handler
        );
    }

    function menuHandler($pp_page)
    {
        if (in_array($pp_page, ['presspermit-statuses', 'presspermit-sync', 'presspermit-teaser'], true)) {
            $slug = str_replace('presspermit-', '', $pp_page);
            require_once(PRESSPERMIT_ABSPATH . "/includes/promo/{$slug}-promo.php");
        }
    }

    function actAdminMenu() {
        $pp_cred_menu = presspermit()->admin()->getMenuParams('permits');

        add_submenu_page(
            $pp_cred_menu, 
            __('Upgrade to Pro', 'press-permit-core'), 
            __('Upgrade to Pro', 'press-permit-core'), 
            'read', 
            'permissions-pro', 
            ['PublishPress\Permissions\UI\Dashboard\DashboardFilters', 'actMenuHandler']
        );
    }

    function setUpgradeMenuLink() {
        $url = 'https://publishpress.com/links/permissions-menu';
        ?>
        <style type="text/css">
        #toplevel_page_presspermit-groups ul li:last-of-type a {font-weight: bold !important; color: #FEB123 !important;}
        </style>

		<script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#toplevel_page_presspermit-groups ul li:last a').attr('href', '<?php echo $url;?>').attr('target', '_blank').css('font-weight', 'bold').css('color', '#FEB123');
            });
        </script>
		<?php
    }
}
