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

            if (in_array(presspermitPluginPage(), ['presspermit-statuses', 'presspermit-visibility-statuses', 'presspermit-sync', 'presspermit-posts-teaser'])) {
                wp_enqueue_style('presspermit-admin-promo', plugins_url('', PRESSPERMIT_FILE) . '/includes/promo/admin-core.css', [], PRESSPERMIT_VERSION, 'all');
            }
        });

        add_action('admin_print_scripts', [$this, 'setUpgradeMenuLink'], 50);

        add_filter(\PPVersionNotices\Module\TopNotice\Module::SETTINGS_FILTER, function ($settings) {
            $settings['press-permit-core'] = [
                'message' => esc_html__("You're using PublishPress Permissions Free. The Pro version has more features and support. %sUpgrade to Pro%s", 'press-permit-core'),
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

        add_action('presspermit_modules_ui', [$this, 'actProModulesUI'], 10, 2);

        add_filter("presspermit_unavailable_modules", 
            function($modules){
                return array_merge(
                    $modules, 
                    [
                        'presspermit-circles', 
                        'presspermit-compatibility', 
                        'presspermit-file-access', 
                        'presspermit-membership', 
                        'presspermit-sync', 
                        'presspermit-status-control', 
                        'presspermit-teaser'
                    ]
                );
            }
        );
    }

    function actAdminMenuPromos($pp_options_menu, $handler) {
        // Disable custom status promos until PublishPress Statuses and compatible version of Permissions Pro are released
        
        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        /*
        add_submenu_page(
            $pp_options_menu, 
            esc_html__('Workflow Statuses', 'press-permit-core'), 
            esc_html__('Workflow Statuses', 'press-permit-core'), 
            'read', 
            'presspermit-statuses', 
            $handler
        );

        add_submenu_page(
            $pp_options_menu, 
            esc_html__('Visibility Statuses', 'press-permit-core'), 
            esc_html__('Visibility Statuses', 'press-permit-core'), 
            'read', 
            'presspermit-visibility-statuses', 
            $handler
        );
        */

        add_submenu_page(
            $pp_options_menu, 
            esc_html__('Sync Posts', 'press-permit-core'), 
            esc_html__('Sync Posts', 'press-permit-core'), 
            'read', 
            'presspermit-sync', 
            $handler
        );

        add_submenu_page(
            $pp_options_menu, 
            esc_html__('Teaser', 'press-permit-core'), 
            esc_html__('Teaser', 'press-permit-core'), 
            'read', 
            'presspermit-posts-teaser', 
            $handler
        );
    }

    function menuHandler($pp_page)
    {
        if (in_array($pp_page, ['presspermit-statuses', 'presspermit-visibility-statuses', 'presspermit-sync', 'presspermit-posts-teaser'], true)) {
            $slug = str_replace('presspermit-', '', $pp_page);
            require_once(PRESSPERMIT_ABSPATH . "/includes/promo/{$slug}-promo.php");
        }
    }

    function actAdminMenu() {
        $pp_cred_menu = presspermit()->admin()->getMenuParams('permits');

        add_submenu_page(
            $pp_cred_menu, 
            esc_html__('Upgrade to Pro', 'press-permit-core'), 
            esc_html__('Upgrade to Pro', 'press-permit-core'), 
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
        /* <![CDATA[ */
            jQuery(document).ready(function($) {
                $('#toplevel_page_presspermit-groups ul li:last a').attr('href', '<?php echo esc_url($url);?>').attr('target', '_blank').css('font-weight', 'bold').css('color', '#FEB123');
            });
        /* ]]> */
        </script>
		<?php
    }

    function actProModulesUI($active_module_plugin_slugs, $inactive) {
        $pro_modules = array_diff(
            presspermit()->getAvailableModules(['force_all' => true]), 
            $active_module_plugin_slugs, 
            array_keys($inactive)
        );

        sort($pro_modules);
        if ($pro_modules) :
            $ext_info = presspermit()->admin()->getModuleInfo();
            ?>
            <h4 style="margin:20px 0 5px 0"><?php esc_html_e('Pro Modules:', 'press-permit-core'); ?></h4>
            <table class="pp-extensions">
                <?php foreach ($pro_modules as $plugin_slug) :
                    $slug = str_replace('presspermit-', '', $plugin_slug);
                    ?>
                    <tr>
                        <td>
                        
                        <?php $id = "module_deactivated_{$slug}";?>

                        <label for="<?php echo esc_attr($id); ?>">
                            <input type="checkbox" id="<?php echo esc_attr($id); ?>" disabled 
                                    name="presspermit_deactivated_modules[<?php echo esc_attr($plugin_slug);?>]"
                                    value="1" />

                            <?php echo esc_html($this->prettySlug($slug));?>
                        </label>
                        </td>

                        <?php if (!empty($ext_info)) : ?>
                            <td>
                                <?php if (isset($ext_info->blurb[$slug])) : ?>
                                    <span class="pp-ext-info"
                                        title="<?php if (isset($ext_info->descript[$slug])) {
                                            echo esc_attr($ext_info->descript[$slug]);
                                        }
                                        ?>">
                                    <?php echo esc_html($ext_info->blurb[$slug]); ?>
                                </span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php
        endif;
    }

    private function prettySlug($slug)
    {
        $slug = str_replace('presspermit-', '', $slug);
        $slug = str_replace('Pp', 'PP', ucwords(str_replace('-', ' ', $slug)));
        $slug = str_replace('press', 'Press', $slug); // temp workaround
        $slug = str_replace('Wpml', 'WPML', $slug);
        return $slug;
    }
}
