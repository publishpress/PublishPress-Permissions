<?php
namespace PublishPress\Permissions\Collab\UI\Dashboard;

class Users
{
    function __construct() {
        add_action('admin_print_footer_scripts', [$this, 'act_add_member_page_js']);
    }

    // @todo: move to js
    public function act_add_member_page_js()
    {
        if (!presspermit()->getOption('add_author_pages'))
            return;
        ?>
        <script type="text/javascript">
            /* <![CDATA[ */
            jQuery(document).ready(function ($) {
                <?php if ( !empty($_REQUEST['ppmessage']) ) {
                switch( $_REQUEST['ppmessage'] ) {
                case 1: ?>
                var msg = '<?php printf(_n('%s author page added', '%s author pages added', $_REQUEST['ppcount'], 'press-permit-core'), $_REQUEST['ppcount']);?>';
                var cls = 'updated';
                <?php
                break;
                case 2 :
                ?>
                var msg = '<?php _e('No users selected', 'press-permit-core');?>';
                var cls = 'error';
                <?php
                break;
                case 3 :
                ?>
                var msg = '<?php _e('Selected users already have specified author page', 'press-permit-core');?>';
                var cls = 'error';
                <?php
                break;
                } // end switch
                ?>
                $('div.wrap h2').after('<div id="message" class="' + cls + ' below-h2"><p>' + msg + '</p></div>');
                <?php
                } // endif message arg
                ?>

                elems =
                    '<div id="member_page_adder" class="alignleft actions" style="margin-left:5px;padding-left:5px;margin-bottom:5px">' +
                    '<label class="screen-reader-text" for="member_page_type"><?php _e('Add Author Page&hellip;', 'press-permit-core') ?></label>' +
                    '<select name="member_page_type" id="member_page_type" title="<?php _e('make each selected user the author of a new page', 'press-permit-core'); ?>" autocomplete="off">' +
                    '<option value=""><?php _e('Add Author Page&hellip;', 'press-permit-core') ?></option>' +
                    <?php
                    $post_types = get_post_types(['public' => true, 'show_ui' => true], 'object', 'or');
                    unset($post_types['attachment']);
                    foreach( $post_types as $post_type => $type_obj ):
                    if (!current_user_can($type_obj->cap->edit_others_posts)) {
                        unset($post_types[$post_type]);
                        continue;
                    }
                    ?>
                    '<option value="<?php echo $post_type;?>"><?php echo $type_obj->labels->singular_name;?></option>' +
                    <?php endforeach;?>
                    '</select>' +

                    <?php
                    global $wpdb;
                    $exclude = $wpdb->get_col("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_pp_auto_inserted'");

                    foreach( $post_types as $post_type => $type_obj ) :
                    if ($type_obj->hierarchical) {
                        $num_posts = wp_count_posts($post_type);
                        $total_posts = array_sum((array)$num_posts);
                    }

                    $input_name = "member_page_pattern_$post_type";
                    if (!$type_obj->hierarchical)
                        $title = __('pattern post sets default content and categories for author post', 'press-permit-core');
                    elseif ('page' == $post_type)
                        $title = __('pattern post sets parent, default content and template for author page', 'press-permit-core');
                    else
                        $title = __('pattern page sets parent and default content for author page', 'press-permit-core');
                    ?>
                    '<label class="screen-reader-text" for="member_page_pattern_<?php echo $post_type;?>"><?php _e('patterned on&hellip;', 'press-permit-core') ?></label>' +
                    '<span id="member_page_pattern_div_<?php echo $post_type;?>" class="member-page-pattern" style="display:none;margin-left:10px;" title="<?php echo $title;?>" >' +
                    <?php
                    if ($type_obj->hierarchical && ($total_posts < 200)) {
                        // @todo: ajax
                        $dropdown_args = [
                            'post_type' => $post_type,
                            'name' => $input_name,
                            'show_option_none' => __('patterned on&hellip;', 'press-permit-core'),
                            'sort_column' => 'menu_order, post_title',
                            'echo' => 0,
                            'exclude' => $exclude,
                        ];
                        $pages = str_replace("'", '"', wp_dropdown_pages($dropdown_args));
                        $pages = str_replace("\n", '', $pages);
                        $pages = str_replace("\r", '', $pages);
                    } else {
                        $pages = '';
                    }

                    if ( $pages ) :
                    ?>
                    '<?php echo $pages;?>' +
                    <?php else:?>
                    '<?php _e('Pattern ID:', 'press-permit-core');?><input type="text" name="<?php echo $input_name;?>" id="<?php echo $input_name;?>" size="20" placeholder="<?php _e('enter post ID/slug', 'press-permit-core');?>" /></span>' +
                    <?php endif;?>
                    '</span>' +
                    <?php endforeach;?>

                    '<span id="member_page_title" style="display:none;margin-left:10px;margin-right:5px"><?php _e('Title:', 'press-permit-core');?><input type="text" name="member_page_title" id="member_page_title" value="[username]" title="<?php _e('supported tags are [username] and [userid]', 'press-permit-core');?>" size="30" /></span>' +
                    '<span id="member_page_add" style="display:none"><?php submit_button(__('Add Pages', 'press-permit-core'), 'secondary', 'add_member_page', false); ?></span>' +
                    '</div>';

                $("select[name='action']").closest('div.top').find('div.tablenav-pages').after($(elems));

                $("#member_page_type").on('change', function()
                {
                    $("#member_page_title").toggle($(this).val() != '');
                    $("#member_page_add").toggle($(this).val() != '');
                    $("span.member-page-pattern").hide();
                    $("#member_page_pattern_div_" + $(this).val()).show();

                    if ($(this).val()) {
                        $('#member_page_adder').addClass('pp-bg-gray');
                    } else {
                        $('#member_page_adder').removeClass('pp-bg-gray');
                    }
                });
            });
            //]]>
        </script>
        <?php
    } // end function add_member_page_js
} // end class
