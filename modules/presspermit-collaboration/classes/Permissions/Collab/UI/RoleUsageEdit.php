<?php
namespace PublishPress\Permissions\Collab\UI;

//use \PressShack\LibWP as PWP;

/**
 * Edit user administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

class RoleUsageEdit {
    function __construct() {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/RoleUsageHelper.php');
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/RoleUsageQuery.php');

        $this->display();
    }

    private function display() {
        global $wp_roles;
        
        $pp = presspermit();

        $url = apply_filters('presspermit_role_usage_base_url', 'admin.php');

        if (isset($_REQUEST['wp_http_referer']))
            $wp_http_referer = $_REQUEST['wp_http_referer'];
        elseif (isset($_SERVER['HTTP_REFERER']))
            $wp_http_referer = remove_query_arg(['update', 'edit', 'delete_count'], stripslashes($_SERVER['HTTP_REFERER']));
        else
            $wp_http_referer = '';

        if (!current_user_can('pp_manage_settings'))
            wp_die(__('You are not permitted to do that.', 'press-permit-core'));

        if (!isset($_REQUEST['role']))
            wp_die('No role specified.');

        $role_name = sanitize_text_field($_REQUEST['role']);

        $cap_caster = $pp->capCaster();
        $cap_caster->definePatternCaps();

        if (isset($pp->role_defs->pattern_roles[$role_name])) {
            $role_obj = $pp->role_defs->pattern_roles[$role_name];
        } elseif (isset($wp_roles->role_names[$role_name])) {
            $role_obj = (object)['labels' => (object)['singular_name' => $wp_roles->role_names[$role_name]]];
        } else
            wp_die('Role does not exist.');

        if (!empty($_POST))
            $_GET['update'] = 1; // temp workaround

        if (isset($_GET['update']) && empty($pp->admin()->errors)) : ?>
            <div id="message" class="updated">
                <p><strong><?php _e('Role Usage updated.', 'press-permit-core') ?>&nbsp;</strong>
                </p>
            </div>
        <?php endif; ?>

        <?php
        if (!empty($pp->admin()->errors) && is_wp_error($pp->admin()->errors)) : ?>
            <div class="error">
                <p><?php echo implode("</p>\n<p>", $pp->admin()->errors->get_error_messages()); ?></p>
            </div>
        <?php endif; ?>

        <div class="wrap pressshack-admin-wrapper" id="pp-permissions-wrapper">
            <header>
            <?php \PublishPress\Permissions\UI\PluginPage::icon(); ?>
            <h1><?php echo esc_html(sprintf(__('Role Usage: %s', 'press-permit-core'), $role_obj->labels->singular_name));
                ?></h1>
            </header>
            
            <form action="" method="post" id="edit_role_usage" name="edit_role_usage">
                <input name="action" type="hidden" value="update"/>
                <?php wp_nonce_field('pp-update-role-usage_' . $role_name) ?>

                <?php if ($wp_http_referer) : ?>
                    <input type="hidden" name="wp_http_referer" value="<?php echo esc_url($wp_http_referer); ?>"/>
                <?php endif; ?>

                <table class="form-table">
                    <tr class="form-field">
                        <th><label for="role_usage_label"><?php _e('Usage', 'press-permit-core') ?></label></th>
                        <td>
                            <div id='pp_role_usage_limitations'>
                                <div>
                                    <?php
                                    $usage = RoleUsageQuery::get_role_usage($role_name);
                                    ?>
                                    <select id='pp_role_usage' name='pp_role_usage' autocomplete='off'>
                                    <option value='0' <?php if ($usage == 0) echo 'selected="selected"'; ?>><?php _e('no supplemental assignment', 'press-permit-core'); ?></option>
                                    <option value='pattern' <?php if ($usage == 'pattern') echo 'selected="selected"'; ?>><?php _e('Pattern Role', 'press-permit-core'); ?></option>
                                    <option value='direct' <?php if ($usage == 'direct') echo 'selected="selected"'; ?>><?php _e('Direct Assignment', 'press-permit-core'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php
                    if (!empty($cap_caster->pattern_role_type_caps[$role_name])) : ?>
                        <tr class="form-field">
                            <th><label for="post_caps_label"><?php _e('Post Capabilities', 'press-permit-core') ?></label></th>
                            <td class='pp-cap_list'>
                                <?php
                                printf(
                                    __('Type-specific and/or status-specific equivalents of the following capabilities are included in supplemental %s roles:', 'press-permit-core'), 
                                    $role_obj->labels->singular_name
                                );
                                
                                $cap_names = array_keys($cap_caster->pattern_role_type_caps[$role_name]);
                                sort($cap_names);
                                echo "<ul><li>" . implode("</li><li>", $cap_names) . "</li></ul>";
                                ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php
                    if (!empty($cap_caster->pattern_role_arbitrary_caps[$role_name])) :
                        ?>
                        <tr>
                            <th></th>
                            <td></td>
                        </tr>
                        <tr class="form-field">
                            <th><label for="arbitrary_caps_label"><?php _e('Arbitrary Capabilities', 'press-permit-core') ?></label></th>
                            <td class='pp-cap_list'>
                                <?php
                                printf(
                                    __('The following capabilities are included in supplemental %s roles:', 'press-permit-core')
                                    , $role_obj->labels->singular_name
                                );
                                
                                $site_caps = array_keys($cap_caster->pattern_role_arbitrary_caps[$role_name]);
                                sort($site_caps);
                                echo "<ul><li>" . implode("</li><li>", $site_caps) . "</li></ul>";
                                ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php if (empty($pp->role_defs->pattern_roles[$role_name]) && !empty($wp_roles->role_objects[$role_name])) : ?>
                        <tr>
                            <th></th>
                            <td></td>
                        </tr>
                        <tr class="form-field">
                            <th><label for="role_caps_label"><?php _e('Role Capabilities', 'press-permit-core') ?></label></th>
                            <td class='pp-cap_list'>
                                <?php
                                _e('All capabilities defined for this WordPress role will be applied in supplemental assignments:', 'press-permit-core');
                                $role_caps = array_keys($wp_roles->role_objects[$role_name]->capabilities);
                                sort($role_caps);
                                echo "<ul><li>" . implode("</li><li>", $role_caps) . "</li></ul>";
                                ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>

                <br/>
                <?php
                do_action('presspermit_edit_role_usage_ui', $role_name);

                if (($usage == 'pattern') && $pp->getOption('display_hints')) {
                    echo '<br />';
                    $hint = '';
                    RoleUsageHelper::other_notes(__('Notes regarding Pattern Roles', 'press-permit-core'));
                }
                ?>

                <?php
                submit_button(PWP::__wp('Update'), 'primary large pp-submit');
                ?>

                <p>
                    <a href="<?php echo(esc_url(add_query_arg('page', 'presspermit-role-usage', admin_url($url)))); ?>">
                    <?php _e('Back to Role Usage List', 'press-permit-core'); ?>
                    </a>
                </p>

            </form>

            <?php 
            presspermit()->admin()->publishpressFooter();
            ?>
        </div>
    <?php
    }
}
