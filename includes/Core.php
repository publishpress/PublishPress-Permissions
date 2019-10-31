<?php
namespace PublishPress\Permissions;

class Core {
    function __construct() {
        add_filter('presspermit_options', [$this, 'fltPressPermitOptions'], 15);
    }

    function fltPressPermitOptions($options) {
        $options['presspermit_display_extension_hints'] = true;
        return $options;
    }
}
