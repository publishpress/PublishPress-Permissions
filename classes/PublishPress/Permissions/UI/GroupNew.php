<?php

namespace PublishPress\Permissions\UI;

class GroupNew
{
    public function __construct() {
        // called by Dashboard\DashboardFilters::actMenuHandler

        require_once(PRESSPERMIT_CLASSPATH . '/UI/AgentPermissionsUI.php');

        $url = apply_filters('presspermit_groups_base_url', 'admin.php');

        if (isset($_REQUEST['wp_http_referer']))
            $wp_http_referer = $_REQUEST['wp_http_referer'];
        elseif (isset($_SERVER['HTTP_REFERER'])) {
            if (!strpos($_SERVER['HTTP_REFERER'], 'page=presspermit-group-new'))
                $wp_http_referer = $_SERVER['HTTP_REFERER'];
            else
                $wp_http_referer = '';

            $wp_http_referer = remove_query_arg(['update', 'delete_count'], stripslashes($wp_http_referer));
        } else
            $wp_http_referer = '';

        if (!current_user_can('pp_create_groups'))
            wp_die(__('You are not permitted to do that.', 'press-permit-core'));
        ?>

        <?php
        $pp = presspermit();
        $pp_admin = $pp->admin();
        $pp_groups = $pp->groups();

        if (isset($_GET['update']) && empty($pp_admin->errors)) : ?>
            <div id="message" class="updated">
                <p><strong><?php _e('Group created.', 'press-permit-core') ?>&nbsp;</strong>
                    <?php
                    $group_variant = ! empty($_REQUEST['group_variant']) ? $_REQUEST['group_variant'] : 'pp_group';

                    $groups_link = ($wp_http_referer && strpos($wp_http_referer, 'presspermit-groups')) 
                    ? $wp_http_referer
                    : admin_url("admin.php?page=presspermit-groups&group_variant=$group_variant"); 
                    ?>

                    <a href="<?php echo esc_url($groups_link); ?>"><?php _e('Back to groups list', 'press-permit-core'); ?></a>
                </p>
            </div>
        <?php endif; ?>

            <?php
        if (!empty($pp_admin->errors) && is_wp_error($pp_admin->errors)) : ?>
            <div class="error">
                <p><?php echo implode("</p>\n<p>", $pp_admin->errors->get_error_messages()); ?></p>
            </div>
        <?php endif; ?>

            <div class="wrap pressshack-admin-wrapper" id="pp-permissions-wrapper">
                <header>
                <?php
                PluginPage::icon();
                ?>
                <h1><?php
                    $agent_type = (isset($_REQUEST['agent_type']) && $pp_groups->groupTypeEditable($_REQUEST['agent_type']))
                        ? sanitize_key($_REQUEST['agent_type'])
                        : 'pp_group';

                    if (('pp_group' == $agent_type) || !$group_type_obj = $pp_groups->getGroupTypeObject($agent_type))
                        _e('Create New Permission Group', 'press-permit-core');
                    else
                        printf(__('Create New %s', 'press-permit-core'), $group_type_obj->labels->singular_name);
                    ?></h1>
                </header>

                <form action="" method="post" id="creategroup" name="creategroup" class="pp-admin">
                    <input name="action" type="hidden" value="creategroup"/>
                    <input name="agent_type" type="hidden" value="<?php echo $agent_type; ?>"/>
                    <?php wp_nonce_field('pp-create-group', '_wpnonce_pp-create-group') ?>

                    <?php if ($wp_http_referer) : ?>
                        <input type="hidden" name="wp_http_referer" value="<?php echo esc_url($wp_http_referer); ?>"/>
                    <?php endif; ?>

                    <table class="form-table">
                        <tr class="form-field form-required">
                            <th scope="row"><label for="group_name"><?php echo __('Name', 'press-permit-core'); ?></label></th>
                            <td><input type="text" name="group_name" id="group_name" value="" class="regular-text"
                                    tabindex="1"/></td>
                        </tr>

                        <tr class="form-field">
                            <th><label for="description"><?php echo __('Description', 'press-permit-core'); ?></label></th>
                            <td><input type="text" name="description" id="description" value="" class="regular-text" size="80"
                                    tabindex="2"/></td>
                        </tr>
                    </table>

                    <?php
                    if ($pp_groups->userCan('pp_manage_members', 0, $agent_type)) {
                        AgentPermissionsUI::drawMemberChecklists(0, $agent_type);
                    }

                    echo '<div class="pp-settings-caption" style="clear:both;"><br />';
                    _e('Note: Supplemental Roles and other group settings can be configured here after the new group is created.', 'press-permit-core');
                    echo '</div>';

                    do_action('presspermit_new_group_ui');
                    ?>

                    <?php
                    submit_button(__('Create Group', 'press-permit-core'), 'primary large pp-submit', '', true, 'tabindex="3"');
                    ?>

                </form>

                <?php 
                presspermit()->admin()->publishpressFooter();
                ?>
            </div>
        <?php
    }
}
