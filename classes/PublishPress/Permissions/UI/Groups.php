<?php

namespace PublishPress\Permissions\UI;

class Groups
{
    public function __construct() {
        // called by Dashboard\DashboardFilters::actMenuHandler

        $pp = presspermit();
        $pp_admin = $pp->admin();
        $pp_groups = $pp->groups();

        if (!empty($_REQUEST['action2']) && !is_numeric($_REQUEST['action2']))
            $action = $_REQUEST['action2'];
        elseif (!empty($_REQUEST['action']) && !is_numeric($_REQUEST['action']))
            $action = $_REQUEST['action'];
        elseif (!empty($_REQUEST['pp_action']))
            $action = $_REQUEST['pp_action'];
        else
            $action = '';

        if ( ! in_array($action, ['delete', 'bulkdelete'])) {
            $agent_type = (!empty($_REQUEST['agent_type'])) ? $_REQUEST['agent_type'] : 'pp_group';
        } else {
            $agent_type = '';
        }

        $agent_type = PluginPage::getAgentType($agent_type);
        $group_variant = PluginPage::getGroupVariant();

        if (! empty(PluginPage::instance()->table)) {
            $groups_list_table = PluginPage::instance()->table;
        } else {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/GroupsListTable.php');
            $groups_list_table = new GroupsListTable(compact('agent_type', 'group_variant'));
        }

        $pagenum = $groups_list_table->get_pagenum();

        $url = $referer = $redirect = $update = '';

        require_once(PRESSPERMIT_CLASSPATH . '/UI/GroupsHelper.php');
        GroupsHelper::getUrlProperties($url, $referer, $redirect);

        switch ($action) {

            case 'delete':
            case 'bulkdelete':
                if (empty($_REQUEST['groups']))
                    $groupids = [intval($_REQUEST['group'])];
                else
                    $groupids = (array)$_REQUEST['groups'];
                ?>
                <form action="" method="post" name="updategroups" id="updategroups">
                    <?php wp_nonce_field('pp-bulk-groups');?>

                    <div class="wrap pressshack-admin-wrapper" id="pp-permissions-wrapper">
                        <?php PluginPage::icon(); ?>
                        <h1><?php _e('Delete Groups'); ?></h1>
                        <p><?php echo _n('You have specified this group for deletion:', 'You have specified these groups for deletion:', count($groupids), 'press-permit-core'); ?></p>
                        <ul>
                            <?php
                            $go_delete = 0;

                            if (!$agent_type = apply_filters('presspermit_query_group_type', ''))
                                $agent_type = 'pp_group';

                            foreach ($groupids as $id) {
                                $id = (int)$id;
                                if ($group = $pp_groups->getGroup($id, $agent_type)) {
                                    if (
                                        empty($group->metagroup_type)
                                        || ('wp_role' == $group->metagroup_type && \PublishPress\Permissions\DB\Groups::isDeletedRole($group->metagroup_id))
                                    ) {
                                        echo "<li><input type=\"hidden\" name=\"users[]\" value=\"" . esc_attr($id) . "\" />"
                                            . sprintf(__('ID #%1s: %2s'), $id, $group->name)
                                            . "</li>\n";

                                        $go_delete++;
                                    }
                                }
                            }
                            ?>
                        </ul>
                        <?php if ($go_delete) : ?>
                            <input type="hidden" name="action" value="dodelete"/>
                            <?php submit_button(__('Confirm Deletion'), 'secondary'); ?>
                        <?php else : ?>
                            <p><?php _e('There are no valid groups selected for deletion.', 'press-permit-core'); ?></p>
                        <?php endif; ?>
                    </div>
                </form>
                <?php

                break;

            default:
                $groups_list_table->prepare_items();
                $total_pages = $groups_list_table->get_pagination_arg('total_pages');

                $messages = [];
                if (isset($_GET['update'])) :
                    switch ($_GET['update']) {
                        case 'del':
                        case 'del_many':
                            $delete_count = isset($_GET['delete_count']) ? (int)$_GET['delete_count'] : 0;

                            $messages[] = '<div id="message" class="updated"><p>'
                                . sprintf(_n('%s group deleted', '%s groups deleted', $delete_count, 'press-permit-core'), $delete_count)
                                . '</p></div>';

                            break;
                        case 'add':
                            $messages[] = '<div id="message" class="updated"><p>' . __('New group created.', 'press-permit-core') . '</p></div>';
                            break;
                    }
                endif;
                ?>

                <?php
                $pp = presspermit();

                if (isset($pp_admin->errors) && is_wp_error($pp_admin->errors)) :
                    ?>
                    <div class="error">
                        <ul>
                            <?php
                            foreach ($pp_admin->errors->get_error_messages() as $err)
                                echo "<li>$err</li>\n";
                            ?>
                        </ul>
                    </div>
                <?php
                endif;

                if (!empty($messages)) {
                    foreach ($messages as $msg)
                        echo $msg;
                } ?>

                <div class="wrap pressshack-admin-wrapper presspermit-groups" id="pp-permissions-wrapper">
                    <header>
                    <?php PluginPage::icon(); ?>
                    <h1 class="wp-heading-inline">
                        <?php
                        if (('pp_group' == $agent_type) || !$group_type_obj = $pp_groups->getGroupTypeObject($agent_type))
                            $groups_caption = (defined('PP_GROUPS_CAPTION')) ? PP_GROUPS_CAPTION : __('Permission Groups', 'press-permit-core');
                        else
                            $groups_caption = $group_type_obj->labels->name;

                        echo esc_html($groups_caption);

                        echo '</h1>';

                        $gvar = ($group_variant) ? $group_variant : 'pp_group';

                        if ($pp_groups->groupTypeEditable($gvar) && current_user_can('pp_create_groups')) :
                            $_url = admin_url('admin.php?page=presspermit-group-new');
                            if ($agent_type) {
                                $_url = add_query_arg(['agent_type' => $agent_type], $_url);
                            }
                            ?>
                            <a href="<?php echo $_url;?>" class="page-title-action"><?php _e('Add New');?></a>
                        <?php endif;

                        if ($pp->getOption('display_hints')) {
                            echo '<div class="pp-hint pp-no-hide">';

                            if (defined('PP_GROUPS_HINT')) {
                                echo esc_html(PP_GROUPS_HINT);
                            } else {
                                echo esc_html(__("Permission Groups adjust user access with type-specific Roles and item-specific Permissions. To customize permissions for a single user instead, click their Role in the Users listing.", 'press-permit-core'));
                            }

                            echo '</div><br />';
                        }

                        $group_types = [];

                        if (current_user_can('pp_administer_content'))
                            $group_types['wp_role'] = (object)['labels' => (object)['singular_name' => __('WordPress Role', 'press-permit-core'), 'plural_name' => __('WordPress Roles', 'press-permit-core')]];

                        $group_types['pp_group'] = (object)['labels' => (object)['singular_name' => __('Custom Group', 'press-permit-core'), 'plural_name' => __('Custom Groups', 'press-permit-core')]];

                        // currently faking WP Role as a "group type", but want it listed before BuddyPress Group
                        $group_types = apply_filters('presspermit_list_group_types', array_merge($group_types, $pp_groups->getGroupTypes([], 'object')));

                        $class = (!$group_variant) ? 'class="current"' : '';
                        $links = ["<li><a href='admin.php?page=presspermit-groups' $class>" . __('All', 'press-permit-core') . "</a></li>"];
                        
                        foreach ($group_types as $_group_type => $gtype_obj) {
                            $agent_type_str = ('wp_role' == $_group_type) ? "&agent_type=pp_group" : "&agent_type=$_group_type";
                            $gvar_str = "&group_variant=$_group_type";
                            $class = strpos($agent_type_str, $agent_type) && ($group_variant && strpos($gvar_str, $group_variant))
                                ? 'class="current"' : '';

                            $links[] = "<li><a href='admin.php?page=presspermit-groups{$agent_type_str}{$gvar_str}' $class>{$gtype_obj->labels->plural_name}</a></li>";
                        }

                        echo '<ul class="subsubsub">';
                        printf(__('%1$sGroup Type:%2$s %3$s', 'press-permit-core'), '<li class="pp-gray">', '</li>', implode('&nbsp;|&nbsp;', $links));
                        echo '</ul>';

                        if (!empty($groupsearch))
                            printf('<span class="subtitle">' . __('Search Results for &#8220;%s&#8221;', 'press-permit-core') . '</span>', esc_html($groupsearch)); ?>
                    </h1>
                    </header>
                    
                    <?php $groups_list_table->views(); ?>

                    <form action="<?php echo "$url" ?>" method="get">
                        <input type="hidden" name="page" value="presspermit-groups"/>
                        <input type="hidden" name="agent_type" value="<?php echo $agent_type ?>"/>
                        <?php
                        $groups_list_table->search_box(__('Search Groups', 'press-permit-core'), 'group', '', 2);
                        ?>

                        <?php $groups_list_table->display(); ?>

                    </form>

                    <br class="clear"/>

                    <?php
                    if (
                        defined('BP_VERSION') && !$pp->moduleActive('compatibility')
                        && $pp->getOption('display_extension_hints')
                    ) {
                        if (presspermit()->isPro()) {
                            $msg = __('To assign roles or permissions to BuddyPress groups, activate the Compatibility Pack module', 'press-permit-core');
                        } else {
                            $msg = sprintf(
                                __('To assign roles or permissions to BuddyPress groups, %1$supgrade to Permissions Pro%2$s and enable the Compatibility Pack module.', 'press-permit-core'),
                                '<a href="https://publishpress.com/pricing/">',
                                '</a>'
                            );
                        }

                        echo "<div class='pp-ext-promo'>$msg</div>";
                    }
                    ?>

                    <?php 
                    presspermit()->admin()->publishpressFooter();
                    ?>

                </div>
                <?php

                break;
        } // end of the $doaction switch
    }
}
