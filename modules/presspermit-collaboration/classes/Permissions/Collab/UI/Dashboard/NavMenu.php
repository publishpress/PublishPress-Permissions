<?php
namespace PublishPress\Permissions\Collab\UI\Dashboard;

//use \PressShack\LibWP as PWP;
use \PublishPress\Permissions\Collab\NavMenus as NavMenus;

require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/NavMenus.php');

/**
 * NavMenu class
 *
 * @package PressPermit
 * @author Kevin Behrens <kevin@agapetry.net>
 * @copyright Copyright (c) 2011-2018, Agapetry Creations LLC
 *
 */
class NavMenu
{
    function __construct()
    {
        add_filter('presspermit_posts_where_limit_statuses', [$this, 'posts_where_limit_statuses'], 10, 2);

        add_action('admin_print_scripts', [$this, 'header_scripts']);
        add_action('admin_print_footer_scripts', [$this, 'footer_scripts']);

        add_action('admin_head', [$this, 'act_disable_uneditable_items_ui']);

        add_filter('get_user_option_nav_menu_recently_edited', [$this, 'fltNavMenuRecent']);

        if (!empty($_POST)) {
            add_action('pre_post_update', [$this, 'act_police_menu_item_edit']);
            add_action('presspermit_delete_object', [$this, 'act_police_menu_item_deletion'], 10, 3);
            add_filter('wp_insert_post_parent', [$this, 'flt_police_menu_item_parent'], 10, 4);
        }

        add_action('admin_head', [$this, 'act_nav_menu_ui']);
    }

    public function fltNavMenuRecent($opt)
    {
        if ($tx_obj = get_taxonomy('nav_menu')) {
            $menu_tt_id = PWP::termidToTtid((int)$opt, 'nav_menu');

            $user = presspermit()->getUser();

            $exclude_tt_ids = $user->getExceptionTerms('manage', 'exclude', 'nav_menu', 'nav_menu');

            $include_tt_ids = $user->getExceptionTerms('manage', 'include', 'nav_menu', 'nav_menu');

            if (!current_user_can($tx_obj->cap->manage_terms, $menu_tt_id)
            || in_array($menu_tt_id, $exclude_tt_ids) 
            || ($include_tt_ids && !in_array($menu_tt_id, $include_tt_ids))
            ) {
                $user = presspermit()->getUser();

                // note: item_type is taxonomy here
                if ($tt_ids = $user->getExceptionTerms('manage', 'additional', 'nav_menu', 'nav_menu')) {
                    if (!in_array($menu_tt_id, $tt_ids)) {
                        $tx_by_ref_arg = '';
                        $opt = PWP::ttidToTermid(reset($tt_ids), $tx_by_ref_arg);
                        update_user_option($user->ID, 'nav_menu_recently_edited', $opt);
                    }
                } else {
                    delete_user_option($user->ID, 'nav_menu_recently_edited');
                    $opt = 0;
                }
            }
        }

        return $opt;
    }

    function posts_where_limit_statuses($limit_statuses, $post_types)
    {
        if ($stati = array_merge(
            PWP::getPostStatuses(['public' => true, 'post_type' => $post_types]), 
            PWP::getPostStatuses(['private' => true, 'post_type' => $post_types]))
        )
            
        $limit_statuses = array_merge($limit_statuses, array_fill_keys($stati, true));

        return $limit_statuses;
    }

    // users with editing access to a limited subset of menus are not allowed to set menu for theme locations
    function act_nav_menu_ui()
    {
        if (!NavMenus::can_edit_theme_locs()) {
            global $wp_meta_boxes;
            unset($wp_meta_boxes['nav-menus']['side']['default']['nav-menu-theme-locations']);

            if (strpos($_SERVER['REQUEST_URI'], 'nav-menus.php?action=locations')) {
                wp_die(__('You are not permitted to manage menu locations', 'press-permit-core'));
            }
        }
    }

    function act_police_menu_item_edit($object_id)
    {
        // don't allow modification of menu items for posts which user can't edit  
        // (this is a pain because WP fires update for each menu item even if unmodified)
        NavMenus::modify_nav_menu_item($object_id, 'edit');
    }

    function act_police_menu_item_deletion($source_name, $pp_args, $object_id)
    {
        // don't allow deletion of menu items for posts which user can't edit
        NavMenus::modify_nav_menu_item($object_id, 'delete');
    }

    function flt_police_menu_item_parent($post_parent, $object_id, $post_arr_keys, $post_arr)
    {
        return NavMenus::flt_police_menu_item_parent($post_parent, $object_id, $post_arr_keys, $post_arr);
    }

    function act_disable_uneditable_items_ui()
    {
        // Exposing uneditable items for labeling makes it necessary to block menu item re-ordering.  
        // Otherwise drag-drop functionality is confused by the mingling of editable and un-editable items.
        if (presspermit()->getOption('admin_nav_menu_partial_editing') && !NavMenus::is_unrestricted()) : ?>
            <style type="text/css">fieldset.field-move {
                    display: none !important;
                }</style>
            <?php
            return;
        endif;

        if (!$menu_id = NavMenus::determine_selected_menu())
            return;

        $menu_items = wp_get_nav_menu_items($menu_id, ['post_status' => 'any']);

        $uneditable_items = [];
        foreach (array_keys($menu_items) as $key) {
            if (!NavMenus::can_edit_menu_item($menu_items[$key]->ID))
                $uneditable_items[] = $menu_items[$key]->ID;
        }

        if ($uneditable_items) :
            ?>
            <style type="text/css">
                <?php
                $comma = '';
                foreach( $uneditable_items as $id ) {
                    echo "#delete-{$id},#cancel-{$id}";
                    $comma = ',';
                }

                echo '{display:none;}';
                ?>
            </style>
        <?php endif;
    }

    function header_scripts()
    {
        $user = presspermit()->getUser();

        if (!empty($user->allcaps['edit_theme_options'])) {
            return;
        }

        $tx_obj = get_taxonomy('nav_menu');

        if (empty($user->allcaps[$tx_obj->cap->manage_terms])) :
            $editable_menus = get_terms('nav_menu');

            // note: menu-add-new,#nav-menu-theme-locations,.menu-theme-locations apply to older WP versions.  TODO: remove?
            ?>
            <style type="text/css">
                .add-edit-menu-action, .add-new-menu-action, a.nav-tab[href~=locations], a.nav-tab[href*="locations"], .menu-add-new, #nav-menu-theme-locations, .menu-theme-locations {
                    display: none;
                }

                <?php if( count($editable_menus) < 2 ) :?>
                .manage-menus {
                    display: none;
                }

                <?php endif;?>
            </style>
        <?php endif;

        // In case we are fudging the edit_theme_options cap for nav menu management, keep other Appearance menu items hidden
        if (presspermit()->getOption('admin_nav_menu_lock_custom')) : ?>
            <style type="text/css">
                #add-custom-links {
                    display: none;
                }
            </style>
        <?php endif;


        if (!NavMenus::can_edit_menu_settings()) :
            ?>
            <style type="text/css">
                div.menu-settings {
                    display: none;
                }
            </style>
        <?php endif;


        if (empty($user->allcaps['delete_menus'])) :
            ?>
            <style type="text/css">
                a.menu-delete, span.delete-action {
                    display: none;
                }
            </style>
        <?php endif;

        // remove html for uneditable menu items (simply hiding them leads to unexpected menu item addition behavior)
        if (!$menu_id = NavMenus::determine_selected_menu())
            return;

        $menu_items = wp_get_nav_menu_items($menu_id, ['post_status' => 'any']);

        $uneditable_items = [];
        foreach (array_keys($menu_items) as $key) {
            if (!NavMenus::can_edit_menu_item($menu_items[$key]->ID)) {
                $uneditable_items[] = $menu_items[$key]->ID;
            }
        }

        if ($uneditable_items) :
            ?>
            <style type="text/css">
                <?php
                $comma = '';
                if ( presspermit()->getOption( 'admin_nav_menu_partial_editing' ) ) {
                    foreach( $uneditable_items as $id ) {
                        echo "{$comma}#menu-item-{$id} a.item-delete,#menu-item-{$id} span.meta-sep,#menu-item-{$id}";
                        $comma = ',';
                    }
                } else {
                    foreach( $uneditable_items as $id ) {
                        echo "{$comma}#menu-item-{$id},#delete-{$id},#cancel-{$id}";
                        $comma = ',';
                    }
                }

                echo '{display:none;}';
                ?>
            </style>
        <?php endif;

    } // end function ui_hide_add_menu_footer_scripts

    function footer_scripts()
    {
        global $current_user;

        if (!empty($current_user->allcaps['edit_theme_options']))
            return;

        if (empty($current_user->allcaps['edit_menus'])) :
            if ($menu_id = NavMenus::determine_selected_menu()) {
                $menu_obj = get_term($menu_id, 'nav_menu');
                $menu_name = $menu_obj->name;
            } else {
                $menu_name = '';
            }
            ?>
            <script type="text/javascript">
                /* <![CDATA[ */
                jQuery(document).ready(function ($) {
                    $('#menu-name').attr('disabled', 'disabled').attr('name', 'menu-name-label').after('<input type="hidden" name="menu-name" value="<?php echo $menu_name;?>" />');
                });
                /* ]]> */
            </script>
        <?php endif;

        // remove html for uneditable menu items (simply hiding them leads to unexpected menu item addition behavior)
        if (!$menu_id = NavMenus::determine_selected_menu()) {
            return;
        }

        $menu_items = wp_get_nav_menu_items($menu_id, ['post_status' => 'any']);

        $uneditable_items = [];
        foreach (array_keys($menu_items) as $key) {
            if (!NavMenus::can_edit_menu_item($menu_items[$key]->ID)) {
                $uneditable_items[] = $menu_items[$key]->ID;
            }
        }

        if ($uneditable_items) :
            ?>
            <script type="text/javascript">
                /* <![CDATA[ */
                jQuery(document).ready(function ($) {
                    <?php
                    foreach ($uneditable_items as $id) {
                        //echo "$('#menu-item-{$id}').removeClass().addClass('menu-item-edit-inactive');";
                        echo "$('#menu-item-{$id}').removeClass('menu-item').addClass('menu-item-edit-inactive');";
                    }
                    ?>
                });
                /* ]]> */
            </script>
        <?php endif;
    }
} // end class
