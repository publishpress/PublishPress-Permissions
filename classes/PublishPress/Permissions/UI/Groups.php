<?php

namespace PublishPress\Permissions\UI;

class Groups
{
    public function __construct()
    {
        // called by Dashboard\DashboardFilters::actMenuHandler

        $pp = presspermit();
        $pp_admin = $pp->admin();
        $pp_groups = $pp->groups();

        /**
         * Need to add dummy constants because this data dynamically comes from the database
         * and we want to use sync tool from loco translate to get this data automatically
         */
        $dummy_const = [
            'PP_GROUP_SHOP_MANAGER' => esc_html__("Shop manager", 'press-permit-core'),
            'PP_GROUP_TRANSLATOR' => esc_html__("Translator", 'press-permit-core'),
            'PP_GROUP_CUSTOMER' => esc_html__("Customer", 'press-permit-core'),
        ];

        // Nonce verification is not needed here because this is either:
        // (a) Confirming an update that already happened
        //  - or -
        // (b) Displaying a confirmation prompt (with nonce field) for deletion request

        if (!PWP::empty_REQUEST('action2') && !is_numeric(PWP::REQUEST_key('action2'))) {
            $action = PWP::REQUEST_key('action2');
        } elseif (!PWP::empty_REQUEST('action') && !is_numeric(PWP::REQUEST_key('action'))) {
            $action = PWP::REQUEST_key('action');
        } elseif (!PWP::empty_REQUEST('pp_action')) {
            $action = PWP::REQUEST_key('pp_action');
        } else {
            $action = '';
        }

        if (! in_array($action, ['delete', 'bulkdelete'])) {
            if (!$agent_type = PWP::REQUEST_key('agent_type')) {
                $agent_type = 'pp_group';
            }
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
                // phpcs Note: Nonce verification unnecessary because this is only generating a confirmation message,
                // or reporting on an update operation already completed.

                // group IDs processing is only to report number of groups that were updated

                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if (!empty($_REQUEST['groups'])) {
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    $groupids = array_map('intval', (array) $_REQUEST['groups']);
                } else {
                    $groupids = (PWP::is_REQUEST('group')) ? [PWP::REQUEST_int('group')] : [];
                }

?>
                <form action="" method="post" name="updategroups" id="updategroups">
                    <?php wp_nonce_field('pp-bulk-groups'); ?>

                    <div class="wrap pressshack-admin-wrapper" id="pp-permissions-wrapper">
                        <?php PluginPage::icon(); ?>
                        <h1><?php esc_html_e('Delete Groups'); ?></h1>
                        <p><?php echo esc_html(_n('You have specified this group for deletion:', 'You have specified these groups for deletion:', count($groupids), 'press-permit-core')); ?></p>
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
                                            . sprintf(esc_html__('ID #%1s: %2s'), esc_html($id), esc_html($group->name))
                                            . "</li>\n";

                                        $go_delete++;
                                    }
                                }
                            }
                            ?>
                        </ul>
                        <?php if ($go_delete) : ?>
                            <input type="hidden" name="action" value="dodelete" />
                            <?php submit_button(esc_html__('Confirm Deletion'), 'secondary'); ?>
                        <?php else : ?>
                            <p><?php esc_html_e('There are no valid groups selected for deletion.', 'press-permit-core'); ?></p>
                        <?php endif; ?>
                    </div>
                </form>
            <?php

                break;

            default:
                $groups_list_table->prepare_items();
                $total_pages = $groups_list_table->get_pagination_arg('total_pages');

                if ($update = PWP::GET_key('update')) :
                    switch ($update) {
                        case 'del':
                        case 'del_many':
                            $delete_count = PWP::GET_int('delete_count');

                            echo '<div id="message" class="updated"><p>'
                                . esc_html(sprintf(_n('%s group deleted', '%s groups deleted', (int) $delete_count, 'press-permit-core'), (int) $delete_count))
                                . '</p></div>';

                            break;
                        case 'add':
                            echo '<div id="message" class="updated"><p>' . esc_html__('New group created.', 'press-permit-core') . '</p></div>';
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
                                echo "<li>" . esc_html($err) . "</li>\n";
                            ?>
                        </ul>
                    </div>
                <?php
                endif;

                ?>

                <div class="wrap pressshack-admin-wrapper presspermit-groups" id="pp-permissions-wrapper">
                    <?php PluginPage::icon(); ?>
                    <h1 class="wp-heading-inline">
                        <?php
                        if (('pp_group' == $agent_type) || !$group_type_obj = $pp_groups->getGroupTypeObject($agent_type))
                            $groups_caption = (defined('PP_GROUPS_CAPTION')) ? PP_GROUPS_CAPTION : __('Permission Groups', 'press-permit-core');
                        else
                            $groups_caption = $group_type_obj->labels->name;

                        echo esc_html($groups_caption);
                        ?>
                    </h1>
                    <?php

                    $gvar = ($group_variant) ? $group_variant : 'pp_group';

                    if ($pp_groups->groupTypeEditable($gvar) && current_user_can('pp_create_groups')) :
                        $_url = admin_url('admin.php?page=presspermit-group-new');
                        if ($agent_type) {
                            $_url = add_query_arg(['agent_type' => $agent_type], $_url);
                        }
                    ?>
                        <a href="<?php echo esc_url($_url); ?>" class="page-title-action"><?php esc_html_e('Add New Group', 'press-permit-core'); ?></a>
                        <hr class="wp-header-end" />
                    <?php endif;

                    if ($pp->getOption('display_hints')) {
                        echo '<div class="pp-hint pp-no-hide">';

                        if (defined('PP_GROUPS_HINT')) {
                            echo esc_html(PP_GROUPS_HINT);
                        }

                        echo '</div>';
                    }

                    $group_types = [];

                    if (current_user_can('pp_administer_content'))
                        $group_types['wp_role'] = (object)['labels' => (object)['singular_name' => esc_html__('WordPress Role', 'press-permit-core'), 'plural_name' => esc_html__('WordPress Roles', 'press-permit-core')]];

                    $group_types['pp_group'] = (object)['labels' => (object)['singular_name' => esc_html__('Custom Group', 'press-permit-core'), 'plural_name' => esc_html__('Custom Groups', 'press-permit-core')]];

                    // currently faking WP Role as a "group type", but want it listed before BuddyPress Group
                    $group_types = apply_filters('presspermit_list_group_types', array_merge($group_types, $pp_groups->getGroupTypes([], 'object')));

                    echo '<ul class="subsubsub">';
                    printf(esc_html__('%1$sGroup Type:%2$s %3$s', 'press-permit-core'), '<li class="pp-gray">', '</li>', '');

                    $class = (!$group_variant) ? 'current' : '';

                    echo "<li><a href='admin.php?page=presspermit-groups' class='" . esc_attr($class) . "'>" . esc_html__('All', 'press-permit-core') . "</a>&nbsp;|&nbsp;</li>";

                    $i = 0;
                    foreach ($group_types as $_group_type => $gtype_obj) {
                        $agent_type_str = ('wp_role' == $_group_type) ? "&agent_type=pp_group" : "&agent_type=$_group_type";
                        $gvar_str = "&group_variant=$_group_type";
                        $class = strpos($agent_type_str, $agent_type) && ($group_variant && strpos($gvar_str, $group_variant)) ? 'current' : '';

                        $group_label = (!empty($gtype_obj->labels->plural_name)) ? $gtype_obj->labels->plural_name : $gtype_obj->labels->singular_name;

                        $i++;

                        echo "<li><a href='" . esc_url("admin.php?page=presspermit-groups{$agent_type_str}{$gvar_str}") . "' class='" . esc_attr($class) . "'>" . esc_html($group_label) . "</a>";

                        if ($i < count($group_types)) {
                            echo "&nbsp;|&nbsp;";
                        }

                        echo '</li>';
                    }

                    echo '</ul>';

                    if (!empty($groupsearch))
                        printf('<span class="subtitle">' . esc_html__('Search Results for &#8220;%s&#8221;', 'press-permit-core') . '</span>', esc_html($groupsearch)); ?>

                    <form action="<?php echo esc_url($url); ?>" method="get">
                        <input type="hidden" name="page" value="presspermit-groups" />
                        <input type="hidden" name="agent_type" value="<?php echo esc_attr($agent_type); ?>" />
                        <?php
                        $groups_list_table->search_box(esc_html__('Search Groups', 'press-permit-core'), 'group', '', 2);
                        ?>

                        <?php $groups_list_table->display(); ?>

                    </form>

                    <br class="clear" />

                    <?php
                    if (
                        defined('BP_VERSION') && !$pp->moduleActive('compatibility')
                        && $pp->getOption('display_extension_hints')
                    ) {
                        echo "<div class='pp-ext-promo'>";

                        if (presspermit()->isPro()) {
                            echo esc_html__('To assign roles or permissions to BuddyPress groups, activate the Compatibility Pack feature', 'press-permit-core');
                        } else {
                            printf(
                                esc_html__('To assign roles or permissions to BuddyPress groups, %1$supgrade to Permissions Pro%2$s and enable the Compatibility Pack feature.', 'press-permit-core'),
                                '<a href="https://publishpress.com/pricing/">',
                                '</a>'
                            );
                        }

                        echo "</div>";
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
