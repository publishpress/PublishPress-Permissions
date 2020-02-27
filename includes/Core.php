<?php
namespace PublishPress\Permissions;

class Core {
    function __construct() {
        add_filter('presspermit_options', [$this, 'fltPressPermitOptions'], 15);
		add_action('presspermit_admin_menu', [$this, 'actAdminMenu'], 999);

        add_action('admin_enqueue_scripts', function() {
            if (presspermitPluginPage()) {
                wp_enqueue_style('presspermit-settings-free', plugins_url('', PRESSPERMIT_FILE) . '/includes/css/settings.css', [], PRESSPERMIT_VERSION);
            }
        });

		add_action('presspermit_plugin_page_admin_header', [$this, 'adminHeader']);
		
		add_action('admin_print_scripts', [$this, 'setUpgradeMenuLink'], 50);
	}
	function actAdminMenu() {
        $pp_cred_menu = presspermit()->admin()->getMenuParams('permits');

        add_submenu_page(
            $pp_cred_menu, 
            __('Upgrade to Pro', 'press-permit-core'), 
            __('Upgrade to Pro', 'press-permit-core'), 
            'read', 
            'presspermit-settings', 
            ['PublishPress\Permissions\UI\Dashboard\DashboardFilters', 'actMenuHandler']
        );
    }

    function setUpgradeMenuLink() {
        $url = 'https://publishpress.com/links/permissions-menu';
        ?>
        <style type="text/css">
        #toplevel_page_presspermit-groups ul li:last-of-type a {font-weight: bold !important; color: #7064A4 !important;}
        </style>

		<script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#toplevel_page_presspermit-groups ul li:last a').attr('href', '<?php echo $url;?>').attr('target', '_blank').css('font-weight', 'bold').css('color', '#7064A4');
            });
        </script>
		<?php
    }
	function adminHeader() {
        $descript = sprintf(
            __('You\'re using PublishPress Permissions Free. To unlock more features, consider upgrading to <a href="%s" target="_blank">Permissions Pro</a>.', 'press-permit-core'),
            'https://publishpress.com/presspermit/'
        );
        ?>

        <div id="presspermit-pro-notice" class="activating"><?php echo $descript; ?></div>
        <?php
    }

    function fltPressPermitOptions($options) {
        $options['presspermit_display_extension_hints'] = true;
        return $options;
    }
}
