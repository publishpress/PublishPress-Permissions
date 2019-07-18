<?php

namespace PublishPress\Permissions\UI\Dashboard;

class ItemEdit
{
    // output restriction attributes JS and (if user can administer roles) role assignment js
    public static function scriptItemEdit($object_type = '')
    {
        if (!$object_type)
            $object_type = PWP::findPostType();

        if (in_array($object_type, ['revision'], true))
            return;

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
        wp_enqueue_script('presspermit-item-edit', PRESSPERMIT_URLPATH . "/common/js/item-edit{$suffix}.js", [], PRESSPERMIT_VERSION);

        if (taxonomy_exists($object_type))
            $type_obj = get_taxonomy($object_type);
        else
            $type_obj = get_post_type_object($object_type);

        if ($type_obj) {
            ?>
            <script type="text/javascript">
                /* <![CDATA[ */
                var pp_hier_type = '<?php echo $type_obj->hierarchical; ?>';
                /* ]]> */
            </script>
            <?php
        }
    }
}
