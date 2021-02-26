<?php

namespace PublishPress\Permissions\UI;

//use \PressShack\LibWP as PWP;
//use \PressShack\LibArray as Arr;

class AgentPermissionsUI
{
    public static function roleAssignmentScripts()
    {
        $agent_type = (isset($_REQUEST['agent_type'])) ? sanitize_key($_REQUEST['agent_type']) : 'pp_group';
        $agent_id = (isset($_REQUEST['agent_id'])) ? (int)$_REQUEST['agent_id'] : 0;

        $vars = [
            'addRoles' => __('Add Roles', 'press-permit-core'),
            'clearRole' => __('clear', 'press-permit-core'),
            'noConditions' => __('No statuses selected!', 'press-permit-core'),
            'pleaseReview' => __('Review selection(s) below, then click Save.', 'press-permit-core'),
            'alreadyRole' => __('Role already selected!', 'press-permit-core'),
            'noAction' => __('No Action selected!', 'press-permit-core'),
            'submissionMsg' => __('Role submission in progress...', 'press-permit-core'),
            'reloadRequired' => __('Reload form for further changes to this role', 'press-permit-core'),
            'ajaxurl' => admin_url(''),
        ];

        $vars['agentType'] = $agent_type;
        $vars['agentID'] = $agent_id;

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
        wp_enqueue_script('presspermit-role-edit', PRESSPERMIT_URLPATH . "/common/js/role-edit{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_VERSION);
        wp_localize_script('presspermit-role-edit', 'ppCred', $vars);

        return $vars;
    }

    public static function exceptionAssignmentScripts()
    {
        $vars = [
            'addExceptions' => __('Set Specific Permissions', 'press-permit-core'),
            'clearException' => __('clear', 'press-permit-core'),
            'pleaseReview' => __('Review selection(s) below, then click Save. Saved permissions can be mirrored to other operations by bulk edit.', 'press-permit-core'),
            'alreadyException' => __('Permission already selected!', 'press-permit-core'),
            'noAction' => __('No Action selected!', 'press-permit-core'),
            'submissionMsg' => __('Permissions submission in progress...', 'press-permit-core'),
            'reloadRequired' => __('Reload form for further changes to this permission', 'press-permit-core'),
            'mirrorDone' => __('Permissions mirrored. Reload form to view newly saved permissions.', 'press-permit-core'),
            'noMode' => __('No Qualification selected!', 'press-permit-core'),
            'ajaxurl' => admin_url(''),
        ];

        $vars['agentType'] = (isset($_REQUEST['agent_type'])) ? sanitize_key($_REQUEST['agent_type']) : 'pp_group';
        $vars['agentID'] = (isset($_REQUEST['agent_id'])) ? (int)$_REQUEST['agent_id'] : 0;

        // Simulate Nav Menu setup
        require_once( PRESSPERMIT_CLASSPATH.'/UI/ItemsMetabox.php' );

        $vars['noItems'] = __('No items selected!', 'press-permit-core');
        $vars['noParent'] = __('(no parent)', 'press-permit-core');
        $vars['none'] = __('(none)', 'press-permit-core');

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';

        wp_enqueue_script('presspermit-item-metabox', PRESSPERMIT_URLPATH . "/common/js/item-metabox{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_VERSION);
        wp_localize_script('presspermit-item-metabox', 'ppItems', ['ajaxurl' => admin_url(''), 'noResultsFound' => __('No results found.', 'press-permit-core')]);

        wp_enqueue_script('presspermit-exception-edit', PRESSPERMIT_URLPATH . "/common/js/exception-edit{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_VERSION);
        wp_localize_script('presspermit-exception-edit', 'ppRestrict', $vars);

        wp_enqueue_script('common');
        wp_enqueue_script('postbox');

        return $vars;
    }

    private static function drawTypeOptions($type_objects, $args = [])
    {
        $defaults = ['option_any' => false, 'option_na' => false];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if (!$type_objects) {
            return;
        }

        echo "<option class='pp-opt-none' value=''>" . __('select...', 'press-permit-core') . '</option>';

        foreach ($type_objects as $_type => $type_obj) {
            echo "<option value='$type_obj->name'>" . $type_obj->labels->singular_name . '</option>';
        }

        if ($option_any) {
            echo "<option value='(all)'>" . __('(all)', 'press-permit-core') . '</option>';
        }

        if ($option_na) {
            if (presspermit()->role_defs->direct_roles) {
                echo "<option value='-1'>" . __('n/a', 'press-permit-core') . '</option>';
            }
        }
    }

    private static function selectExceptionsUi($type_objects, $taxonomy_objects, $args = [])
    {
        $pp = presspermit();

        // Discourage anon/all metagroups having read exceptions for specific posts. Normally, that's what post visibility is for.
        $is_all_anon = (isset($args['agent']) && !empty($args['agent']->metagroup_id) && in_array($args['agent']->metagroup_id, ['wp_anon', 'wp_all']));
        ?>
    <img id="pp_add_exception_waiting" class="waiting" style="display:none;position:absolute" src="<?php echo esc_url(admin_url('images/wpspin_light.gif')) ?>" alt="" />
    <table id="pp_add_exception">
        <thead>
            <tr>
                <th><?php _e('Post Type', 'press-permit-core'); ?></th>
                <th class="pp-select-x-operation" style="display:none"><?php _e('Operation', 'press-permit-core'); ?></th>
                <th class="pp-select-x-mod-type" style="display:none"><?php _e('Adjustment', 'press-permit-core'); ?></th>
                <th class="pp-select-x-via-type" style="display:none"><?php _e('Qualification', 'press-permit-core'); ?></th>
                <th class="pp-select-x-status" style="display:none"><?php _e('Statuses', 'press-permit-core'); ?></th>
                <th class="pp-add-exception" style="display:none"></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>

                    <select name="pp_select_x_for_type" autocomplete="off">
                        <?php
                        unset($type_objects['attachment']); // may be re-added by module
                        $type_objects = apply_filters('presspermit_append_exception_types', $pp->admin()->orderTypes(apply_filters('presspermit_exception_types', $type_objects)));

                        if (!empty($args['external']))
                            $type_objects = array_merge($type_objects, $args['external']);

                        self::drawTypeOptions($type_objects, ['option_any' => true]);
                        do_action('presspermit_exception_types_dropdown', $args);

                        ?></select></td>

                <td class="pp-select-x-operation" style="display:none"></td>
                <td class="pp-select-x-mod-type" style="display:none"></td>

                <td class="pp-select-x-via-type" style="display:none"><select name="pp_select_x_via_type" autocomplete="off"></select>

                    <div class="pp-select-x-assign-for" id="pp_select_x_assign_for" style="display:none">
                        <p class="pp-checkbox">
                            <input type="checkbox" name="pp_select_x_item_assign" id="pp_select_x_item_assign" checked="checked" value="1">
                            <label for="pp_select_x_item_assign"><?php _e('for item', 'press-permit-core'); ?></label>
                        </p>

                    </div>
                </td>

                <td class="pp-select-x-status" style="display:none">
                    <p class="pp-checkbox">
                        <input type="checkbox" id="pp_select_x_cond_" name="pp_select_x_cond[]" checked="checked" value="" />
                        <label id="lbl_pp_select_x_cond_" for="pp_select_x_cond_"> <?php _e('(all)', 'press-permit-core'); ?></label>
                    </p>
                </td>

                <td class="pp-select-items" style="display:none;padding-right:0">
                    <?php self::itemSelectUI(array_merge($type_objects, $taxonomy_objects)); ?>
                </td>

            </tr>
        </tbody>
    </table>

    <?php if ($is_all_anon) : ?>
        <div id="pp-all-anon-warning" class="pp-red" style="display:none;">
            <?php _e('Warning: Content hidden by these Permissions will be displayed if PP is deactivated. Consider setting a private Visibility on Edit Post screen instead.', 'press-permit-core'); ?>
        </div>

        <script type="text/javascript">
            /* <![CDATA[ */
            jQuery(document).ready(function($) {
                $(document).on('change', 'select[name="pp_select_x_for_type"]', function()
                {
                    $('#pp-all-anon-warning').hide();
                });

                var handle_anon_warning = function()
                {
                    if (('read' == $('input[name="pp_select_x_operation"]').val()) &&
                        ('additional' != $('input[name="pp_select_x_mod_type"]').val()) &&
                        ('pp-post-object' == $('select[name="pp_select_x_via_type"] option:selected').attr('class'))) {
                        $('#pp-all-anon-warning').show();
                    } else {
                        $('#pp-all-anon-warning').hide();
                    }
                }

                $(document).on('pp_exceptions_ui', handle_anon_warning);
                $(document).on('change', 'select[name="pp_select_x_via_type"]', handle_anon_warning);
            });
        </script>
    <?php endif; ?>

    <div class='pp-ext-promo'>
        <?php
        if (!$pp->moduleActive('collaboration') && $pp->getOption('display_extension_hints')) {
            $msg = __('To assign page-specific Permissions for editing, parent selection or term assignment, enable the Collaborative Publishing module.', 'press-permit-core');
            echo "<div>$msg</div>";
        }
        ?>

        <?php
        if (
            function_exists('bbp_get_version') && !$pp->moduleActive('compatibility')
            && $pp->getOption('display_extension_hints')
        ) {
            if (presspermit()->isPro())
                $msg = __('To assign forum-specific Permissions for bbPress, activate the Compatibility Pack module.', 'press-permit-core');
            else
                $msg = sprintf(
                    __('To assign forum-specific Permissions for bbPress, %1$supgrade to Permissions Pro%2$s and enable the Compatibility Pack module.', 'press-permit-core'),
                    '<a href="https://publishpress.com/pricing/">',
                    '</a>'
                );

            echo "<div>$msg</div>";
        }
        ?>
    </div><?php
    }

    private static function selectRolesUI($type_objects, $taxonomy_objects)
    {
        $pp = presspermit();

        ?>
    <img id="pp_add_role_waiting" class="waiting" style="display:none;position:absolute" src="<?php echo esc_url(admin_url('images/wpspin_light.gif')) ?>" alt="" />
    <table id="pp_add_role">
        <thead>
            <tr>
                <th><?php _e('Post Type', 'press-permit-core'); ?></th>
                <th class="pp-select-role" style="display:none"><?php echo __('Role', 'press-permit-core'); ?></th>
                <th class="pp-select-cond" style="display:none"><?php _e('For Statuses', 'press-permit-core'); ?></th>
                <th class="pp-add-site-role" style="display:none"></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <select name="pp_select_type" autocomplete="off">
                        <?php
                        self::drawTypeOptions($type_objects, ['option_na' => true]);
                        do_action('presspermit_role_types_dropdown');
                        ?></select></td>

                <td class="pp-select-role" style="display:none"><select name="pp_select_role" autocomplete="off"></select></td>

                <td class="pp-select-cond" id="pp_cond_ui" style="display:none">
                    <p class="pp-checkbox">
                        <input type="checkbox" id="pp_select_cond_" name="pp_select_cond[]" checked="checked" value="" />
                        <label id="lbl_pp_select_cond_" for="pp_select_cond_"> <?php _e('Standard statuses', 'press-permit-core'); ?>
                        </label>
                    </p>
                    <p class="pp-checkbox pp_select_private_status" style="display:none">
                        <input type="checkbox" id="pp_select_cond_post_status_private" name="pp_select_cond[]" value="post_status:private" style="display:none" />
                        <label for="pp_select_cond_post_status_private"> <?php _e('Status: Private', 'press-permit-core'); ?></label>
                    </p>
                </td>

                <td class="pp-add-site-role" style="display:none">
                    <input id="pp_add_site_role" class="button-secondary pp-default-button" type="submit" name="pp_add_site_role" value="<?php _e('Add Role', 'press-permit-core'); ?>" />
                </td>

            </tr>
        </tbody>
    </table>

    <div class='pp-ext-promo'>
        <?php
        if (!$pp->moduleActive('status-control') && $pp->getOption('display_extension_hints')) {
            if (presspermit()->isPro())
                $msg = __('To assign roles for custom post statuses, activate the Status Control module.', 'press-permit-core');
            else
                $msg = sprintf(
                    __('To assign roles for custom post statuses, %1$supgrade to Permissions Pro%2$s and enable the Status Control module.', 'press-permit-core'),
                    '<a href="https://publishpress.com/pricing/">',
                    '</a>'
                );

            echo "<div>$msg</div>";
        }
        ?>

        <?php
            if (function_exists('bbp_get_version') && !$pp->moduleActive('compatibility') && $pp->getOption('display_extension_hints')) {
                if (presspermit()->isPro())
                    $msg = __('To assign roles for bbPress forums, activate the Compatibility Pack module.', 'press-permit-core');
                else
                    $msg = sprintf(
                        __('To assign roles for bbPress forums, %1$supgrade to Permissions Pro%2$s and enable the Compatibility Pack module.', 'press-permit-core'),
                        '<a href="https://publishpress.com/pricing/">',
                        '</a>'
                    );

            echo "<div>$msg</div>";
        }
        ?>

        <?php
        if (defined('REVISIONARY_VERSION') && !$pp->moduleActive('collaboration') && $pp->getOption('display_extension_hints')) {
            $msg = __('To assign page-specific PublishPress Revision permissions, enable the Collaborative Publishing module.', 'press-permit-core');
            echo "<div>$msg</div>";
        }
        ?>
    </div><?php
    }

    private static function itemSelectUI($type_objects)
    {
        require_once( PRESSPERMIT_CLASSPATH.'/UI/ItemsMetabox.php' );

        add_filter('get_terms_args', [__CLASS__, 'fltTermSelectNoPaging'], 50, 2);
        add_action('admin_print_footer_scripts', [__CLASS__, 'actScriptHideTermSelectPaging']);

        foreach ($type_objects as $type_obj) {
            if (defined('PP_' . strtoupper($type_obj->name) . '_NO_EXCEPTIONS'))
                continue;

            $type_obj = apply_filters('presspermit_permit_items_meta_box_object', $type_obj);

            if (post_type_exists($type_obj->name))
                $metabox_function = ['\PublishPress\Permissions\UI\ItemsMetabox', 'post_type_meta_box'];

            elseif (taxonomy_exists($type_obj->name))
                $metabox_function = ['\PublishPress\Permissions\UI\ItemsMetabox', 'taxonomy_meta_box'];

            elseif (in_array($type_obj->name, ['pp_group', 'pp_net_group'], true))
                $metabox_function = ['\PublishPress\Permissions\UI\ItemsMetabox', 'group_meta_box'];

            elseif (!$metabox_function = apply_filters('presspermit_item_select_metabox_function', '', $type_obj))
                continue;

            add_meta_box("select-exception-{$type_obj->name}", sprintf(__('Select %s', 'press-permit-core'), $type_obj->labels->name), $metabox_function, 'edit-exceptions', 'side', 'default', $type_obj);
        }
        ?>
    <div id="nav-menus-frame">
        <div id="menu-settings-column" class="metabox-holder pp-menu-settings-column">
            <form id="nav-menu-meta" class="nav-menu-meta" method="post" enctype="multipart/form-data">
                <?php
                wp_nonce_field('add-exception_item', 'menu-settings-column-nonce');
                ?>
                <?php do_meta_boxes('edit-exceptions', 'side', null); ?>
            </form>
        </div><?php
                ?>
        <div class="nav-tabs-wrapper" style="display:n-one">
            <div class="nav-tabs">
                <span class="nav-tab menu-add-new nav-tab-active"></span>
                <ul id="menu-to-edit" class="menu ui-sortable"> </ul>
            </div><?php
                    ?>
        </div><?php
                ?>
    </div><?php
    }

    private static function confirmRolesUI()
    {
        ?>
    <div id="pp_site_selection_msg" class="pp-error-note pp-edit-msg" style="display:none"></div>

    <div id="pp_review_roles" class="pp-save-roles" style="display:none">

        <table id="pp_tbl_role_selections">
            <thead>
                <tr>
                    <th><?php _e('Post Type', 'press-permit-core'); ?></th>
                    <th><?php echo __('Role', 'press-permit-core'); ?></th>
                    <th><?php _e('Status', 'press-permit-core'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>

        <div id="pp_save_roles">
            <p class="submit">
                <input id="submit_roles" class="button button-primary pp-primary-button" type="submit" value="<?php _e('Save Roles', 'press-permit-core'); ?>" name="submit">
            </p>
        </div>
    </div>

    <div id="pp_new_submission_msg" style="display:none">
    </div><?php
    }

    private static function confirmExceptionsUI()
    {
        ?>
    <div id="pp_item_selection_msg" class="pp-error-note pp-edit-msg" style="display:none"></div>

    <div id="pp_review_exceptions" class="pp-save-exceptions" style="display:none">

        <table id="pp_tbl_exception_selections">
            <thead>
                <tr>
                    <th><?php _e('Post Type', 'press-permit-core'); ?></th>
                    <th><?php _e('Operation', 'press-permit-core'); ?></th>
                    <th><?php _e('Adjustment', 'press-permit-core'); ?></th>
                    <th><?php _e('Qualification', 'press-permit-core'); ?></th>
                    <th></th>
                    <th><?php _e('Status', 'press-permit-core'); ?></th>
                    <th><a class="pp_clear_all" href="javascript:void(0)"><?php _e('clear', 'press-permit-core'); ?></a></th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>

        <div id="pp_save_exceptions"><?php
                                        ?>
            <!--
            <p class="mirror-wrap">
                <div class="mirror-label">
                    <?php _e('Mirror all selections to other operation(s)', 'press-permit-core');?>
                </div>
                <div class='pp_mirror_to_operations'>    
                </div>
            </p>
            -->

            <p class="submit">
                <input id="submit_exc" class="button button-primary" type="submit" value="<?php _e('Save Permissions', 'press-permit-core'); ?>" name="submit">
            </p>

        </div>
    </div>
    <div id="pp_new_x_submission_msg" style="display:none">
    </div><?php
    }

    public static function drawGroupPermissions($agent_id, $agent_type, $url, $wp_http_referer = '', $args = [])
    {
        $pp = presspermit();

        $post_types = $pp->admin()->orderTypes($pp->getEnabledPostTypes([], 'object'));
        $taxonomies = $pp->admin()->orderTypes($pp->getEnabledTaxonomies(['object_type' => false], 'object'));

        $perms = [];

        if (('pp_group' == $agent_type) && ($group = $pp->groups()->getGroup($agent_id)))
            $is_wp_role = ('wp_role' == $group->metagroup_type);

        if (empty($group) || !in_array($group->metagroup_id, ['wp_anon', 'wp_all']) || defined('PP_ALL_ANON_ROLES'))
            $perms['roles'] = __('Add Supplemental Roles', 'press-permit-core');

        $perms['exceptions'] = __('Set Specific Permissions', 'press-permit-core');

        if (!isset($perms['roles']))
            $current_tab = 'pp-add-exceptions';
        elseif (!isset($perms['roles']))
            $current_tab = 'pp-add-roles';
        elseif (!$current_tab = get_user_option('pp-permissions-tab'))
            $current_tab = (isset($perms['roles'])) ? 'pp-add-roles' : 'pp-add-exceptions';

        if (($args['agent']->metagroup_type != 'wp_role') || !in_array($args['agent']->metagroup_id, ['wp_anon', 'wp_all'])) {
            $perms['clone'] = __('Clone', 'press-permit-core');
        }

        // --- add permission tabs ---
        echo "<ul id='pp_add_permission_tabs' class='pp-list_horiz'>";
        foreach ($perms as $perm_type => $_caption) {
            $class = ("pp-add-$perm_type" == $current_tab) ? 'agp-selected_agent' : 'agp-unselected_agent';

            echo "<li class='agp-agent pp-add-$perm_type pp-add-permissions $class'><a class='pp-add-{$perm_type}' href='javascript:void(0)'>"
                . $_caption . '</a></li>';
        }
        echo '</ul>';

        // --- divs for add Roles / Exceptions ---
        $arr = array_keys($perms);
        $first_perm_type = reset($arr);
        foreach (array_keys($perms) as $perm_type) {
            $display_style = ("pp-add-$perm_type" == $current_tab) ? '' : ';display:none';
            echo "<div class='pp-agents pp-group-box pp-add-permissions pp-add-$perm_type' style='clear:both{$display_style}'>";
            echo '<div>';

            if ('roles' == $perm_type) {
                // temp workaround for bbPress
                self::selectRolesUI(array_diff_key($post_types, array_fill_keys(['topic', 'reply'], true)), $taxonomies);
            } elseif ('exceptions' == $perm_type) {
                if (!isset($args['external']))
                    $args['external'] = [];

                $post_types = Arr::subset($post_types, $pp->getEnabledPostTypes(['layer' => 'exceptions']));
                self::selectExceptionsUi(array_diff_key($post_types, array_fill_keys(['topic', 'reply'], true)), $taxonomies, $args);
            }
            ?>

        <form id="group-<?php echo $perm_type; ?>-selections" action="<?php echo esc_url($url); ?>" method="post" <?php do_action('presspermit_group_edit_form_tag'); ?>>
            <?php wp_nonce_field("pp-update-{$perm_type}_" . $agent_id, "_pp_nonce_$perm_type") ?>
            <?php
            if ('clone' == $perm_type) {
                self::selectCloneUI($args['agent']);
            }

            if ($wp_http_referer) : ?>
                <input type="hidden" name="wp_http_referer" value="<?php echo esc_url($wp_http_referer); ?>" />
            <?php endif; ?>
            <input type="hidden" name="action" value="pp_update<?php echo $perm_type; ?>" />
            <input type="hidden" name="agent_id" value="<?php echo esc_attr($agent_id); ?>" />
            <input type="hidden" name="agent_type" value="<?php echo esc_attr($agent_type); ?>" />
            <input type="hidden" name="member_csv" value="-1" />
            <input type="hidden" name="group_name" value="-1" />
            <input type="hidden" name="description" value="-1" />

            <?php
            if ('roles' == $perm_type) {
                self::confirmRolesUI();
            } elseif ('exceptions' == $perm_type) {
                self::confirmExceptionsUI();
            }

            // form close tag output by php due to code autoformatting issue
            echo '</form>';

            echo '</div></div>';
        } // end foreach perm_type (roles, exceptions)

        $args['agent_type'] = $agent_type;
        $roles = $pp->getRoles($agent_id, $agent_type, ['post_types' => $post_types, 'taxonomies' => $taxonomies]);

        $args['class'] = ('user' == $agent_type) ? 'pp-user-roles' : 'pp-group-roles';
        $args['agent_type'] = $agent_type;
        self::currentRolesUI($roles, $args);

        $post_types[''] = ''; // also retrieve exceptions for (all) post type

        $_args = [
            'assign_for' => '',
            'extra_cols' => ['i.assign_for', 'i.eitem_id'],
            'agent_type' => $agent_type,
            'agent_id' => $agent_id,
            'post_types' => array_keys($post_types),
            'taxonomies' => array_keys($taxonomies),
            'return_raw_results' => true
        ];

        if (empty($_REQUEST['show_propagated']))
            $_args['inherited_from'] = 0;
        else
            $_args['extra_cols'][] = 'i.inherited_from';

        // @todo: Determine how exception items can become orphaned, eliminate this routine check
        require_once(PRESSPERMIT_CLASSPATH . '/DB/Permissions.php');
        \PublishPress\Permissions\DB\Permissions::expose_orphaned_exception_items();

        $exc = $pp->getExceptions($_args);
        $args['class'] = ('user' == $agent_type) ? 'pp-user-roles' : 'pp-group-roles';

        self::currentExceptionsUI($exc, $args);

        do_action('presspermit_group_roles_ui', $agent_type, $agent_id);
    }

    public static function currentRolesUI($roles, $args = [])
    {
        $defaults = ['read_only' => false, 'caption' => '', 'class' => 'pp-group-roles', 'link' => '', 'agent_type' => ''];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if (!$caption) {
            $caption = ('user' == $agent_type)
                ? sprintf(__('Supplemental Roles %1$s(for user)%2$s', 'press-permit-core'), '<small>', '</small>')
                : __('Supplemental Roles', 'press-permit-core');
        }

        $type_roles = [];

        if (!$roles)
            return;

        // @todo: still necessary?
        $has_roles = false;
        foreach (array_keys($roles) as $key) {
            if (!empty($roles[$key]))
                $has_roles = true;
        }

        if (!$has_roles)
            return;

        $pp = presspermit();
        $pp_admin = $pp->admin();

        echo '<div style="clear:both;"></div>'
            . "<div id='pp_current_roles_header' class='pp-group-box {$class}'>"
            . '<h3>';

        if ($link)
            echo "<a href='$link'>$caption</a>";
        else
            echo $caption;

        echo '</h3>';
        echo '<div>';

        $_class = ($read_only) ? ' class="pp-readonly"' : '';
        echo '<div id="pp_current_roles"' . $_class . '>';

        foreach (array_keys($roles) as $role_name) {
            if (strpos($role_name, ':')) {
                $arr = explode(':', $role_name);
                $source_name = $arr[1];
                $object_type = $arr[2];
            } else {
                $object_type = '';

                $source_name = (0 === strpos($role_name, 'pp_') && strpos($role_name, '_manager')) ? 'term' : 'post';
            }

            $type_roles[$source_name][$object_type][$role_name] = true;
        }

        foreach (array_keys($type_roles) as $source_name) {
            ksort($type_roles[$source_name]);

            foreach (array_keys($type_roles[$source_name]) as $object_type) {
                if ($type_obj = $pp->getTypeObject($source_name, $object_type)) {
                    $type_caption = $type_obj->labels->singular_name;
                } elseif ('term' == $source_name) {
                    // term management roles will not be applied without collaborative editing module, so do not display
                    if (!$pp->moduleActive('collaboration')) {
                        continue;
                    }

                    $type_caption = __('Term Management', 'press-permit-core');
                } else {
                    $_role_name = key($type_roles[$source_name][$object_type]);
                    if (false === strpos($_role_name, ':')) {
                        $type_obj = (object)['labels' => (object)['name' => __('objects', 'press-permit-core'), 'singular_name' => __('objects', 'press-permit-core')]];
                        $type_caption = __('Direct-Assigned', 'press-permit-core');
                    } else {
                        $type_obj = (object)['labels' => (object)['name' => __('objects', 'press-permit-core'), 'singular_name' => __('objects', 'press-permit-core')]];
                        $type_caption = __('Disabled Type', 'press-permit-core');
                    }
                }

                echo "<div class='type-roles-wrapper'>";
                echo '<h4 style="margin-bottom:0.3em">' . sprintf(__('%s Roles', 'press-permit-core'), $type_caption) . '</h4>';
                echo "<div class='pp-current-type-roles'>";

                // site roles
                if (isset($type_roles[$source_name][$object_type])) {
                    echo "<div id='pp_current_{$source_name}_{$object_type}_site_roles' class='pp-current-roles pp-current-site-roles'>";
                    $inputs = [];

                    $_arr = $type_roles[$source_name][$object_type];
                    ksort($_arr);
                    foreach (array_keys($_arr) as $role_name) {
                        $role_title = $pp_admin->getRoleTitle($role_name, ['include_warnings' => true]);

                        if ($read_only) {
                            $inputs[] = "<label>$role_title</label>";
                        } else {
                            $ass_id = $roles[$role_name];
                            $cb_id = 'pp_edit_role_' . str_replace(',', '_', $ass_id);

                            $inputs[] = "<label for='$cb_id'><input id='$cb_id' type='checkbox' name='pp_edit_role[]' value='$ass_id'>&nbsp;"
                                . $role_title . '</label>';
                        }
                    }

                    $sep = ($read_only) ? ',&nbsp; ' : ' ';
                    echo implode($sep, $inputs);

                    echo '</div>';
                }

                echo '</div></div>';
            } // end foreach object_type
        } // end foreach source_name

        echo '<br /><div class="pp-role-bulk-edit" style="display:none">';

        echo "<select><option value=''>" . PWP::__wp('Bulk Actions') . "</option><option value='remove'>"
            . PWP::__wp('Remove') . '</option></select>';
        ?>
        <input type="submit" name="" class="button submit-edit-item-role" value="<?php _e('Apply', 'press-permit-core'); ?>" />
        <?php

        echo '<img class="waiting" style="display:none;" src="' . esc_url(admin_url('images/wpspin_light.gif')) . '" alt="" />';
        echo '</div>';

        echo '</div>';

        echo '</div></div>';

        return true;
    }

    public static function currentExceptionsUI($exc_results, $args = [])
    {
        $defaults = [
            'read_only' => false,
            'class' => 'pp-group-roles',
            'item_links' => false,
            'caption' => '',
            'link' => '',
            'agent_type' => ''
        ];

        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if (!$exc_results)
            return;

        $pp = presspermit();
        $pp_admin = $pp->admin();
        $pp_groups = $pp->groups();

        if (!$caption) {
            $caption = ('user' == $agent_type)
                ? sprintf(__('Specific Permissions %1$s(for user)%2$s', 'press-permit-core'), '<small>', '</small>')
                : __('Specific Permissions', 'press-permit-core');
        }

        require_once(PRESSPERMIT_CLASSPATH_COMMON . '/Ancestry.php');

        $exceptions = array_fill_keys(array_merge(['term', 'post'], $pp_groups->getGroupTypes()), []);

        // support imported include exception with no items included
        $item_paths = array_fill_keys(array_keys($exceptions), [__('(none)', 'press-permit-core')]);

        $post_types = $pp->getEnabledPostTypes([], 'names');
        $taxonomies = $pp->getEnabledTaxonomies(['object_type' => false], 'names');

        // object_type not strictly necessary here, included for consistency with term role array
        foreach ($exc_results as $row) {
            switch ($row->via_item_source) {
                case 'term':
                    if ($row->item_id) {
                        $taxonomy = '';
                        $term_id = (int)PWP::ttidToTermid($row->item_id, $taxonomy);

                        if ($row->item_id)
                            $item_paths['term'][$row->item_id] = \PressShack\Ancestry::getTermPath($term_id, $taxonomy);

                        $via_type = $taxonomy;
                    } else
                        $via_type = $row->via_item_type;

                    break;

                case 'post':
                    if ($row->item_id)
                        $item_paths['post'][$row->item_id] = \PressShack\Ancestry::getPostPath($row->item_id);
                    // no break

                default:
                    if ($pp_groups->groupTypeExists($row->via_item_source)) {
                        static $groups_by_id;

                        if (!isset($groups_by_id)) {
                            $groups_by_id = [];
                        }

                        if (!isset($groups_by_id[$row->via_item_source])) {
                            $groups_by_id[$row->via_item_source] = [];

                            foreach ($pp_groups->getGroups($row->via_item_source, ['skip_meta_types' => 'wp_role']) as $group) {
                                $groups_by_id[$row->via_item_source][$group->ID] = $group->name;
                            }
                        }

                        if (isset($groups_by_id[$row->via_item_source][$row->item_id])) {
                            $item_paths[$row->via_item_source][$row->item_id] = $groups_by_id[$row->via_item_source][$row->item_id];
                        }

                        $via_type = $row->via_item_source;
                    } else
                        $via_type = ($row->via_item_type) ? $row->via_item_type : $row->for_item_type;
            }

			$_assign_for = trim($row->assign_for);
            $exceptions[$row->via_item_source][$via_type][$row->for_item_type][$row->operation][$row->mod_type][$row->for_item_status][$row->item_id][$_assign_for] = $row->eitem_id;

            if (!empty($row->inherited_from)) {
                $exceptions[$row->via_item_source][$via_type][$row->for_item_type][$row->operation][$row->mod_type][$row->for_item_status][$row->item_id]['inherited_from'] = $row->inherited_from;
            }
        }

        echo '<div style="clear:both;"></div>'
            . "<div id='pp_current_exceptions' class='pp-group-box $class'>"
            . '<h3>';

        if ($link)
            echo "<a href='{$link}'>{$caption}</a>";
        else
            echo $caption;

        echo '</h3>';
        echo '<div>';

        echo '<div id="pp_current_exceptions_inner">';

        if (empty($_REQUEST['all_types']) && !empty($exceptions['post'])) {
            $all_types = array_fill_keys(array_merge($post_types, $taxonomies, ['']), true);

            // hide topic, reply assignments even if they are somehow saved/imported without inherited_from value
            $all_types = array_diff_key($all_types, ['topic' => true, 'reply' => true]);

            $exceptions['post'] = array_intersect_key($exceptions['post'], $all_types);

            foreach (array_keys($exceptions['post']) as $key) {
                $exceptions['post'][$key] = array_intersect_key($exceptions['post'][$key], $all_types);
            }
        }

        foreach (array_keys($exceptions) as $via_src) {
            ksort($exceptions[$via_src]);

            foreach (array_keys($exceptions[$via_src]) as $via_type) {
                if ($via_type_obj = $pp->getTypeObject($via_src, $via_type)) {
                    $via_type_caption = $via_type_obj->labels->singular_name;
                } else
                    continue;

                $any_redundant = false;

                echo "<div id='pp_current_{$via_src}_{$via_type}_roles' class='pp-current-exceptions'>";

                ksort($exceptions[$via_src][$via_type]);

                foreach (array_keys($exceptions[$via_src][$via_type]) as $for_type) {
                    if ($pp_groups->groupTypeExists($for_type))
                        $for_src = $for_type;
                    else
                        $for_src = (taxonomy_exists($for_type) || !$for_type) ? 'term' : 'post';

                    if (!$for_type)
                        $for_type_obj = (object)['labels' => (object)['singular_name' => __('(all post types)', 'press-permit-core')]];
                    elseif (!$for_type_obj = $pp->getTypeObject($for_src, $for_type))
                        continue;

                    foreach (array_keys($exceptions[$via_src][$via_type][$for_type]) as $operation) {
                        if (!$operation_obj = $pp_admin->getOperationObject($operation, $for_type))
                            continue;

                        if ('assign' == $operation) {
                            $op_caption = ($for_type)
                                ? sprintf(__('%1$s (%2$s: %3$s)', 'press-permit-core'), $operation_obj->label, $for_type_obj->labels->singular_name, $via_type_caption)
                                : sprintf(__('%1$s %2$s %3$s', 'press-permit-core'), $operation_obj->label, $via_type_caption, $for_type_obj->labels->singular_name);
                        } elseif (in_array($operation, ['manage', 'associate'], true)) {
                            $op_caption = sprintf(__('%1$s %2$s', 'press-permit-core'), $operation_obj->label, $via_type_caption);
                        } else {
                            $op_caption = sprintf(__('%1$s %2$s', 'press-permit-core'), $operation_obj->label, $for_type_obj->labels->singular_name);
                        }

                        echo "<div class='type-roles-wrapper'>";
                        echo '<h4>' . $op_caption . '</h4>';
                        echo "<div class='pp-current-type-roles'>";

                        echo '<div class="pp-current-roles-tbl-wrapper"><table>';

                        // fill table body (item assignments for each role)
                        echo '<tbody>';

                        foreach (array_keys($exceptions[$via_src][$via_type][$for_type][$operation]) as $mod_type) {
                            if (!$mod_type_obj = self::getModificationObject($mod_type))
                                continue;

                            foreach (array_keys($exceptions[$via_src][$via_type][$for_type][$operation][$mod_type]) as $status) {
                                if ($status) {
                                    $_status = explode(':', $status);
                                    if (count($_status) > 1) {
                                        $attrib = $_status[0];
                                        $_status = $_status[1];
                                    } else {
                                        $attrib = 'post_status';
                                        $_status = $status;
                                    }

                                    if ('post_status' == $attrib) {
                                        if ($status_obj = get_post_status_object($_status)) {
                                            $status_label = $status_obj->label;
                                        } elseif ('{unpublished}' == $_status) {  // @todo: API
                                            $status_label = __('unpublished', 'press-permit-core');
                                        } else {
                                            $status_label = $status;
                                        }
                                    } else
                                        $status_label = $status;

                                    $mod_caption = sprintf(__('%1$s (%2$s)', 'press-permit-core'), $mod_type_obj->label, $status_label);
                                } else {
                                    $mod_caption = $mod_type_obj->label;
                                }

                                if (('exclude' == $mod_type) && !empty($exceptions[$via_src][$via_type][$for_type][$operation]['include'])) {
                                    $tr_class = ' class="pp_faded"';
                                    $mod_caption = sprintf(__('* %s', 'press-permit-core'), $mod_caption);
                                    $any_redundant = true;
                                } else
                                    $tr_class = '';

                                echo "<tr{$tr_class}><td class='pp_item_role_caption'>$mod_caption</td>";
                                echo '<td>';

                                echo "<div class='pp-role-terms-wrapper pp-role-terms-{$via_type}'>";

                                if (('term' == $via_src) && !in_array($operation, ['manage', 'associate'], true)) {
                                    if (taxonomy_exists($via_type)) {
                                        // "Categories:"
                                        $tx_obj = get_taxonomy($via_type);
                                        $tx_caption = $tx_obj->labels->name;
                                    } else
                                        $tx_caption = '';

                                    echo '<div class="pp-taxonomy-caption">' . sprintf(__('%s:', 'press-permit-core'), $tx_caption) . '</div>';
                                }

                                echo '<div class="pp-role-terms">';

                                $tx_item_paths = array_intersect_key($item_paths[$via_src], $exceptions[$via_src][$via_type][$for_type][$operation][$mod_type][$status]);

                                uasort($tx_item_paths, 'strnatcasecmp');  // sort by array values, but maintain keys );

                                foreach ($tx_item_paths as $item_id => $item_path) {
                                    if (!$item_id && ('associate' == $operation)) {
                                        $item_path = __('(no parent)', 'press-permit-core');
                                    }

                                    $assignment = $exceptions[$via_src][$via_type][$for_type][$operation][$mod_type][$status][$item_id];

                                    $classes = [];

                                    if (isset($assignment['children'])) {
                                        if (isset($assignment['item'])) {
                                            $ass_id = $assignment['item'] . ',' . $assignment['children'];
                                            $classes[] = 'role_both';
                                            $any_both = true;
                                        } else {
                                            $ass_id = '0,' . $assignment['children'];
                                            $classes[] = 'role_ch';
                                            $any_child_only = true;
                                        }
                                    } else {
                                        $ass_id = $assignment['item'];
                                    }

                                    $class = ($classes) ? "class='" . implode(' ', $classes) . "'" : '';

                                    if ($read_only) {
                                        if ($item_links) {
                                            //$item_edit_url = sprintf($edit_url_base, $item_id);
                                            $item_edit_url = '';
                                            echo "<div><a href='$item_edit_url' $class>$item_path</a></div>";
                                        } else
                                            echo "<div><span $class>$item_path</span></div>";
                                    } else {
                                        $cb_id = 'pp_edit_exception_' . str_replace(',', '_', $ass_id);

                                        if (!empty($assignment['inherited_from'])) {
                                            $classes[] = 'inherited';
                                            $classes[] = "from_{$assignment['inherited_from']}";
                                        }

                                        if ($tr_class) // apply fading for redundantly stored exclusions
                                            $classes[] = $tr_class;

                                        $lbl_class = ($classes) ? "class='" . implode(' ', $classes) . "'" : '';

                                        if ('term' == $via_src) {
                                            $term_id = PWP::ttidToTermid($item_id, $via_type);

                                            $edit_url = ('nav_menu' == $via_type)
                                            ? admin_url("nav-menus.php?action=edit&menu=172")
                                            : admin_url("term.php?taxonomy={$via_type}&tag_ID=$term_id&post_type=$for_type");
                                        } else {
                                            $edit_url = admin_url("post.php?post=$item_id&action=edit");
                                        }
                                        echo "<div><label for='$cb_id' $lbl_class><input id='$cb_id' type='checkbox' name='pp_edit_exception[]' value='$ass_id' $class autocomplete='off'> " . $item_path . '</label><a href="' . $edit_url . '">' . __('edit') . '</a></div>';
                                    }
                                } // end foreach item

                                if ((count($tx_item_paths) > 3) && !$read_only) {
                                    $cb_id = "pp_check_all_{$via_src}_{$via_type}_{$for_type}_{$operation}_{$status}";

                                    echo "<div><label for='$cb_id'><input type='checkbox' id='$cb_id' class='pp_check_all'> "
                                        . __('(all)', 'press-permit-core')  . '</label></div>';
                                }

                                echo '</div></div>';   // pp-role-terms, pp-role-terms-wrapper

                                echo '</td></tr>';
                            } // end foreach status
                        } // end foreach mod_type
                        echo '</tbody>';

                        echo '</table></div>';  // pp-current-roles-tbl-wrapper

                        echo '<div class="pp-exception-bulk-edit" style="display:none">';

                        echo "<select autocomplete='off'><option value='' autocomplete='off'>" . PWP::__wp('Bulk Actions')
                            . "</option><option value='remove'>" . __('Remove', 'press-permit-core') . '</option>';

                        if (('post' == $via_src) && (!$via_type || $via_type_obj->hierarchical)) {
                            echo "<option value='propagate'>"
                                . sprintf(__('Assign for selected and sub-%s', 'press-permit-core'), $via_type_obj->labels->name)
                                . '</option>';

                            echo "<option value='unpropagate'>"
                                . sprintf(__('Assign for selected %s only', 'press-permit-core'), $via_type_obj->labels->singular_name)
                                . '</option>';

                            echo "<option value='children_only'>"
                                . sprintf(__('Assign for sub-%s only', 'press-permit-core'), $via_type_obj->labels->name)
                                . '</option>';
                        } elseif ('term' == $via_src && $via_type_obj->hierarchical) {
                            echo "<option value='propagate'>"
                                . __('Assign for selected and sub-terms', 'press-permit-core')
                                . '</option>';

                            echo "<option value='unpropagate'>"
                                . __('Assign for selected term only', 'press-permit-core')
                                . '</option>';

                            echo "<option value='children_only'>"
                                . __('Assign for sub-terms only', 'press-permit-core')
                                . '</option>';
                        }

                        if ('associate' == $operation) {
                            $_op = ('term' == $via_src) ? 'term_associate' : 'post_associate';
                        } else {
                            $_op = $operation;
                        }

                        if (in_array($via_src, ['post', 'term'])) {
                            switch ($_op) {
                                case 'read':
                                case 'edit':
                                case 'revise':
                                case 'publish':
                                case 'post_associate':
                                case 'assign':
                                    $mirror_ops = ['read', 'edit'];

                                    if (defined('REVISIONARY_VERSION')) {
                                        $mirror_ops []= 'revise';
                                    }

                                    if ($pp->getOption('publish_exceptions')) {
                                        $mirror_ops []= 'publish';
                                    }

                                    if ('term' == $via_src) {
                                        $mirror_ops []= 'assign';
                                    }

                                    if ($for_type_obj->hierarchical) {
                                        $mirror_ops []= 'associate';
                                    }
                                    
                                    break;

                                case 'manage':
                                case 'term_associate':
                                    $mirror_ops = ['manage'];

                                    if ($for_type_obj->hierarchical) {
                                        $mirror_ops []= 'associate';
                                    }

                                    break;
                            }

                            $mirror_ops = array_diff($mirror_ops, [$operation]);

                            foreach($mirror_ops as $op) {
                                $op_obj = $pp_admin->getOperationObject($op);

                                $caption = (('assign' == $op) || !$for_type || ('term' == $for_type)) 
                                ? sprintf(
                                    __('Mirror to %s', 'press-permit-core'),
                                    $op_obj->label
                                )
                                : sprintf(
                                    __('Mirror to %s %s', 'press-permit-core'),
                                    $op_obj->label,
                                    $for_type_obj->labels->singular_name
                                );

                                echo "<option value='mirror_$op'>"
                                    . $caption
                                    . '</option>';
                            }
                        }

                        echo '</select>';
                        ?>

                        <input type="submit" name="" class="button submit-edit-item-exception" value="<?php _e('Apply', 'press-permit-core'); ?>" />
                        <?php
                        echo '<img class="waiting" style="display:none;" src="'
                            . esc_url(admin_url('images/wpspin_light.gif'))
                            . '" alt="" />';

                        ?>
                        <div class="mirror-confirm" style="display:none"></div>
                        <?php

                        echo '</div>';  // pp-exception-bulk-edit

                        echo '</div></div>';  // type-roles-wrapper, pp-current-type-roles
                    } // end foreach operation
                } // end foreach for_type

                if ($any_redundant) {
                    echo '<div class="pp-current-roles-note">'
                        . __('* = Permissions redundant due to a corresponding &quot;only these&quot; entry', 'press-permit-core')
                        . '</div>';
                }

                if (!empty($via_type_obj->hierarchical)) {
                    $_caption = strtolower($via_type_obj->labels->name);

                    if (!empty($any_both) || !empty($any_child_only)) : ?>
                        <div class="pp-current-roles-note">

                            <?php
                            if (!empty($any_both)) {
                                echo '<span class="role_both">'
                                    . sprintf(__('... = assigned for %1$s and sub-%1$s', 'press-permit-core'), $_caption)
                                    . '</span>';
                            }
                            if (!empty($any_child_only))
                                echo '<span>' . sprintf(__('* = assigned for sub-%s only', 'press-permit-core'), $_caption) . '</span>';
                            ?>
                        </div>
                    <?php

                    endif;

                    $show_all_url = esc_url(add_query_arg('show_propagated', '1', $_SERVER['REQUEST_URI']));
                    $show_all_link = "&nbsp;&nbsp;<a href='$show_all_url'>";

                    if (empty($_REQUEST['show_propagated'])) {
                        if ('term' == $via_src) {
                            echo '<div class="pp-current-roles-note">'
                                . sprintf(__('note: Permissions inherited from parent %1$s are not displayed. %2$sshow all%3$s', 'press-permit-core'), $_caption, $show_all_link, '</a>')
                                . '</div>';
                        } else {
                            echo '<div class="pp-current-roles-note">'
                                . sprintf(__('note: Permissions inherited from parent %1$s or terms are not displayed. %2$sshow all%3$s', 'press-permit-core'), $_caption, $show_all_link, '</a>')
                                . '</div>';
                        }
                    }
                }

                echo '</div>';  // pp-current-exceptions

            } // end foreach via_type

        } // end foreach via_src

        echo '</div>';  // pp_current_exceptions_inner

        echo '</div>';  // no class

        echo '</div>';  // pp_current_exceptions
    }

    // Called once each for members checklist, managers checklist in admin UI.
    // In either case, current (checked) members are at the top of the list.
    private static function userSelectionUI($group_id = 0, $agent_type, $user_class = 'member', $all_users = '')
    {
        // This is only needed for checkbox selection
        if (!$all_users)
            $all_users = self::getAllUsers('id_name');

        $current_users = ($group_id)
            ? presspermit()->groups()->getGroupMembers($group_id, $agent_type, 'all', ['member_type' => $user_class, 'status' => 'any'])
            : [];

        $args = [
            'suppress_extra_prefix' => true,
            'ajax_selection' => true,
            'agent_id' => $group_id,
        ];

        echo '<div>';

        echo '<div class="pp-agent-select">';

        $agents = presspermit()->admin()->agents();
        $agents->agentsUI('user', $all_users, $user_class, $current_users, $args);
        echo '</div>';

        echo '</div>';
    }

    private static function getAllUsers($cols = 'id', $args = [])
    {
        require_once(PRESSPERMIT_CLASSPATH . '/DB/Users.php');
        $args['cols'] = $cols;
        return \PublishPress\Permissions\DB\Users::getUsers($args);
    }

    public static function drawMemberChecklists($group_id, $agent_type, $args = [])
    {
        $defaults = ['member_types' => ['member'], 'suppress_caption' => false];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $captions['member'] = apply_filters('presspermit_group_members_caption', __('Group Members', 'press-permit-core'));

        echo '<div class="pp-group-box pp-group_members" style="display:none;">';

        if (!$suppress_caption) {
            echo '<h3>';

            // note: member_type other than 'member' is never invoked as of PP Core 2.1-beta

            $i = 0;
            foreach ($member_types as $member_type) {
                $link_class = ($i) ? 'agp-unselected_agent' : 'agp-selected_agent';

                ?>
                <span class="<?php echo "$link_class pp-member-type pp-$member_type"; ?>">
                    <a href="#" class="<?php echo "pp-$member_type"; ?>"><?php echo $captions[$member_type]; ?></a>
                </span>
                <?php

                $i++;
                if ($i < count($member_types)) {
                    echo '<span> | </span>';
                }
            }

            echo '</h3>';
        }

        $i = 0;
        foreach ($member_types as $member_type) {
            $style = ($i) ? ' style="display:none"' : '';
            ?>
            <div class="<?php echo "pp-member-type pp-$member_type"; ?>" <?php echo $style; ?>>
                <?php
                self::userSelectionUI($group_id, $agent_type, $member_type);
                ?>
            </div>
            <?php
            $i++;
        }

        echo '</div>';

        do_action('presspermit_group_members_ui', $group_id, $agent_type);
    }

    private static function selectCloneUI($agent)
    {
        global $wp_roles;
        _e('Copy Roles and Permissions from:', 'press-permit-core');

        $pp_only = (array) presspermit()->getOption('supplemental_role_defs');
        ?>
        <select name="pp_select_role" autocomplete="off">

            <?php
            foreach ($wp_roles->role_names as $role_name => $role_caption) {
                if (
                    !in_array($role_name, $pp_only, true) && ($role_name != $agent->metagroup_id)
                    && empty($wp_roles->role_objects[$role_name]->capabilities['activate_plugins'])
                    && empty($wp_roles->role_objects[$role_name]->capabilities['pp_administer_content'])
                    && empty($wp_roles->role_objects[$role_name]->capabilities['pp_unfiltered'])
                ) {
                    echo "<option value='$role_name'>$role_caption</option>";
                }
            }
            ?>

        </select>

        <br />
        <div>
            <input id="pp_clone_permissions" class="button button-primary pp-primary-button" type="submit" name="pp_clone_permissions" value="<?php _e('Do Clone'); ?>">
        </div>
    <?php
    }

    public static function fltTermSelectNoPaging($args, $taxonomies)
    {
        $args['number'] = 999;
        return $args;
    }

    public static function actScriptHideTermSelectPaging()
    {
    ?>
        <script type="text/javascript">
            /* <![CDATA[ */
            jQuery(document).ready(function($) {
                //$('.add-menu-item-pagelinks').hide();
            });
            /* ]]> */
        </script><?php
    }

    private static function getModificationObject($mod_type)
    {
        static $mod_types;

        if (!isset($mod_types)) {
            $mod_types = [
                'include' => (object)['label' => __('Limit to:', 'press-permit-core')],
                'exclude' => (object)['label' => __('Blocked:', 'press-permit-core')],
            ];

            if (!defined('PP_NO_ADDITIONAL_ACCESS')) {
                $mod_types['additional'] = (object)['label' => __('Enabled:', 'press-permit-core')];
            }
        }
        return (isset($mod_types[$mod_type])) ? $mod_types[$mod_type] : (object)[];
    }
}
