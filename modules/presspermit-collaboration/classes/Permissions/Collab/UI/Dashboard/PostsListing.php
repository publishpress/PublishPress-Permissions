<?php
namespace PublishPress\Permissions\Collab\UI\Dashboard;

class PostsListing
{
    function __construct() {
        if (defined('PRESSPERMIT_STATUSES_VERSION')) {
            add_action('admin_print_footer_scripts', [$this, 'act_modify_inline_edit_ui']);
        }

        add_action('admin_print_scripts', [$this, 'act_maybe_hide_quickedit']);
        
        add_action('admin_head', [$this, 'act_register_column_filters']);
        
        add_action('init', [$this, 'act_tax_force_show_admin_col'], 99);
    }

    public function act_tax_force_show_admin_col()
    {
        if (presspermit()->getOption('force_taxonomy_cols')) {
            global $wp_taxonomies;

            foreach (presspermit()->getEnabledTaxonomies(['object_type' => PWP::findPostType()], 'names') as $taxonomy) {
                $wp_taxonomies[$taxonomy]->show_admin_column = true;
            }
        }
    }

    public function act_register_column_filters()
    {
        global $typenow;
        add_action("manage_{$typenow}_posts_custom_column", [$this, 'actManagePostsCustomColumn'], 10, 2);
    }

    public function actManagePostsCustomColumn($column_name, $id)
    {
        if ('status' == $column_name) {
            global $post;

            if (!in_array($post->post_status, ['draft', 'public', 'private', 'pending'], true)) {
                // duplicate status display avoidance is currently handled elsewhere
                /*
                global $publishpress;

                if ($publishpress && method_exists($publishpress->custom_status, 'get_custom_status_by')) {
                    if ($publishpress->custom_status->get_custom_status_by('slug', $post->post_status)) {
                        return;
                    }
                }
                */

                if ($status_obj = get_post_status_object($post->post_status)) {
                    if (!empty($status_obj->private) || (!empty($status_obj->moderation) && ('future' != $post->post_status))) {
                        echo $status_obj->label;
                    }
                }
            }
        }
    }

    // Quick Edit provides access to some properties which some content-specific editors cannot modify (Page parent, post/page visibility and status)
    // For now, avoid this complication and filtering overhead by turning off Quick Edit for users lacking site-wide edit_others capability
    public function act_maybe_hide_quickedit()
    {
        if (Collab::isLimitedEditor() && !current_user_can('pp_force_quick_edit')) {
            // @todo: better quick/bulk edit support for limited editors
            ?>
            <style type="text/css">
                .editinline, div.tablenav div.actions select option[value="edit"] {
                    display: none;
                }
            </style>
            <?php
        }
    }

    public function act_modify_inline_edit_ui()
    {
        $screen = get_current_screen();

        $post_type = (!empty($screen) && isset($screen->post_type)) ? $screen->post_type : PWP::findPostType();

        if (!$post_type || apply_filters('presspermit_disable_object_cond_metaboxes', false, 'post', 0)) {
            return;
        }

        $post_type_object = get_post_type_object($screen->post_type);
        ?>
        <script type="text/javascript">
            /* <![CDATA[ */
            jQuery(document).ready(function ($) {
                <?php
                $pp = presspermit();
                $moderation_statuses = [];
                foreach (PWP::getPostStatuses(
                    ['_builtin' => false, 
                    'moderation' => true, 
                    'post_type' => $screen->post_type], 
                    'object') as $status => $status_obj 
                ) {
                    $set_status_cap = "set_{$status}_posts";

                    $check_cap = (!empty($post_type_object->cap->$set_status_cap)) 
                    ? $post_type_object->cap->$set_status_cap 
                    : $post_type_object->cap->publish_posts;

                    if ($pp->isContentAdministrator() || current_user_can($check_cap)) {
                        $moderation_statuses[$status] = $status_obj;
                    }
                }

                foreach( $moderation_statuses as $status => $status_obj ) :
                ?>
                if (!$('select[name="_status"] option[value="<?php echo $status;?>"]').length) {
                    $('<option value="<?php echo $status;?>"><?php echo $status_obj->label;?></option>').insertBefore('select[name="_status"] option[value="pending"]');
                }
                <?php endforeach;?>
            });
            //]]>
        </script>
        <?php
    } // end function add_inline_edit_ui
}

