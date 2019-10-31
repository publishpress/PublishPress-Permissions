<?php

namespace PublishPress\Permissions\UI;

class ItemsMetaboxAjax
{
    public function __construct() 
    {
        if ( ! current_user_can( 'pp_assign_roles' ) )
            wp_die( -1 );

        require_once( PRESSPERMIT_CLASSPATH . '/UI/ItemsMetabox.php' );

        if ( isset( $_POST['item-type'] ) && 'post_type' == $_POST['item-type'] ) {
            $type = 'posttype';
            $callback = ['\PublishPress\Permissions\UI\ItemsMetabox', 'post_type_meta_box'];
            $items = (array) presspermit()->getEnabledPostTypes([], 'object');
        } elseif ( isset( $_POST['item-type'] ) && 'taxonomy' == $_POST['item-type'] ) {
            $type = 'taxonomy';
            $callback = ['\PublishPress\Permissions\UI\ItemsMetabox', 'taxonomy_meta_box'];
            $items = (array) get_taxonomies( [ 'show_ui' => true ], 'object' );
        }

        if ( ! empty( $_POST['item-object'] ) && isset( $items[$_POST['item-object']] ) ) {
            $item = $items[ $_POST['item-object'] ];

            ob_start();
            call_user_func_array($callback, [
                null,
                [
                    'id' => 'add-' . $item->name,
                    'title' => $item->labels->name,
                    'callback' => $callback,
                    'args' => $item,
                ]
            ]);

            $markup = ob_get_clean();

            echo json_encode([
                'replace-id' => $type . '-' . $item->name,
                'markup' => $markup,
            ]);
        }

        exit;
    }
}
