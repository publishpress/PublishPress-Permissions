<?php
namespace PublishPress\Permissions;

class Core {
    function __construct() {
        add_filter('presspermit_options', [$this, 'fltPressPermitOptions'], 15);
		add_action('presspermit_plugin_page_admin_header', [$this, 'adminHeader']);
		
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
