<?php
namespace PublishPress\Permissions\Collab\UI\Gutenberg;

class PostEdit
{
    function __construct() 
    {
        // Gutenberg Block Editor support
        //
        // This script executes on the 'init' action if is_admin() and $pagenow is 'post-new.php' or 'post.php' and the block editor is active.
        //

        add_action('enqueue_block_editor_assets', [$this, 'act_object_guten_scripts']);
    }

    public function act_object_guten_scripts()
    {
        // Administrators don't need this script
        if (presspermit()->isContentAdministrator()) {
            return;
        }

        $args = [];

        if (!Collab::userCanAssociateMain(PWP::findPostType())) {
            if ($post_id = PWP::getPostID()) {
                if (!get_post_field('post_parent', $post_id)) {
                    return;
                }
            }

            $args['blockMainPage'] = true;
            $args['selectCaption'] = __('(select...)', 'press-permit-core');
        } else {
            return;
        }

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
        wp_enqueue_script('presspermit-object-edit', PRESSPERMIT_COLLAB_URLPATH . "/common/js/post-block-edit{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_COLLAB_VERSION, true);
        wp_localize_script('presspermit-object-edit', 'ppCollabEdit', $args);
    }
}
