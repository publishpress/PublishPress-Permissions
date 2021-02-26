<?php

namespace PublishPress\Permissions\UI\Dashboard;

class ItemExceptionsUI
{
    private $render;
    var $data;

    public function __construct()
    {
        require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/ItemExceptionsData.php');
        $this->data = new ItemExceptionsData();

        require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/ItemExceptionsRenderUI.php');
        $this->render = new ItemExceptionsRenderUI();
    }

    public function drawExceptionsUI($box, $args)
    {
        if (!isset($box['args'])) {
            return;
        }

        $pp = presspermit();
        $pp_admin = $pp->admin();
        $pp_groups = $pp->groups();

        $item_id = (isset($args['item_id'])) ? $args['item_id'] : 0;
        $for_item_type = (isset($args['for_item_type'])) ? $args['for_item_type'] : '';
        $via_item_source = (isset($args['via_item_source'])) ? $args['via_item_source'] : '';
        $via_item_type = (isset($args['via_item_type'])) ? $args['via_item_type'] : '';
        $op = (isset($box['args']['op'])) ? $box['args']['op'] : '';

        global $wp_roles;

        $is_draft_post = false;
        if ('post' == $via_item_source) {
            if ('read' == $op) {
                global $post;
                $status_obj = get_post_status_object($post->post_status);
                if (!$status_obj || (!$status_obj->public && !$status_obj->private)) {
                    $is_draft_post = true;
                }
            }

            $hierarchical = is_post_type_hierarchical($via_item_type);
        } else {
            $hierarchical = is_taxonomy_hierarchical($via_item_type);
        }

        if ($hierarchical = apply_filters('presspermit_do_assign_for_children_ui', $hierarchical, $via_item_type, $args)) {
            $type_obj = ('post' == $via_item_source) ? get_post_type_object($via_item_type) : get_taxonomy($via_item_type);
        }

        $agent_types['wp_role'] = (object)['labels' => (object)['name' => __('Roles'), 'singular_name' => __('Role')]];

        $agent_types = apply_filters('presspermit_list_group_types', array_merge($agent_types, $pp->groups()->getGroupTypes([], 'object')));

        $agent_types['user'] = (object)['labels' => (object)['name' => __('Users'), 'singular_name' => __('User', 'press-permit-core')]];

        static $drew_itemroles_marker;
        if (empty($drew_itemroles_marker)) {
            echo "<input type='hidden' name='pp_post_exceptions' value='true' />";
            $drew_itemroles_marker = true;
        }

        $current_exceptions = (isset($this->data->current_exceptions[$for_item_type]))
            ? $this->data->current_exceptions[$for_item_type]
            : [];

        // ========== OBJECT / TERM EXCEPTION DROPDOWNS ============
        $toggle_agents = count($agent_types) > 1;
        if ($toggle_agents) {
            global $is_ID;
            $class_selected = 'agp-selected_agent agp-agent';
            $class_unselected = 'agp-unselected_agent agp-agent';
            $bottom_margin = (!empty($is_IE)) ? '-0.7em' : 0;

            $default_agent_type = 'wp_role';

            echo "<div class='hide-if-not-js' style='margin:0 0 $bottom_margin 0'>"
                . "<ul class='pp-list_horiz' style='margin-bottom:-0.1em'>";

            foreach ($agent_types as $agent_type => $gtype_obj) {
                $label = (!empty($current_exceptions[$op][$agent_type]))
                    ? sprintf(__('%1$s (%2$s)', 'press-permit-core'), $gtype_obj->labels->name, count($current_exceptions[$op][$agent_type]))
                    : $gtype_obj->labels->name;

                $class = ($default_agent_type == $agent_type) ? "class='$class_selected'" : "class='$class_unselected'";
                echo "<li $class><a href='javascript:void(0)' class='{$op}-{$for_item_type}-{$agent_type}'>" . $label . '</a></li>';
            }

            echo '</ul></div>';
        }

        $class = "class='pp-agents pp-exceptions'";

        //need effective line break here if not IE
        echo "<div style='clear:both;' $class>";

        foreach (array_keys($agent_types) as $agent_type) {
            $hide_class = ($toggle_agents && ($agent_type != $default_agent_type)) ? ' class="hide-if-js"' : '';

            echo "\r\n<div id='{$op}-{$for_item_type}-{$agent_type}' $hide_class style='overflow-x:auto'>";

            $this->render->setOptions($agent_type);

            // list all WP roles
            if ('wp_role' == $agent_type) {
                if (!isset($current_exceptions[$op][$agent_type]))
                    $current_exceptions[$op][$agent_type] = [];

                foreach ($this->data->agent_info['wp_role'] as $agent_id => $role) {
                    if (
                        in_array($role->metagroup_id, ['wp_anon', 'wp_all'], true)
                        && (!$pp->moduleActive('file-access') || 'attachment' != $for_item_type)
                        && !defined('PP_ALL_ANON_FULL_EXCEPTIONS')
                        && (('read' != $op) || $pp->getOption('anonymous_unfiltered'))
                    ) {
                        continue;
                    }

                    if (!isset($current_exceptions[$op][$agent_type][$agent_id])) {
                        $current_exceptions[$op][$agent_type][$agent_id] = [];
                    }
                }

                if (
                    !$is_draft_post && ('post' == $via_item_source) && ('attachment' != $via_item_type)
                    && in_array($op, ['read', 'edit', 'delete'], true)
                ) {
                    $reqd_caps = map_meta_cap("{$op}_post", 0, $item_id);
                } else {
                    $reqd_caps = false;
                }
            }

            global $wp_roles;
            ?>

            <table class="pp-item-exceptions-ui pp-exc-<?php echo $agent_type; ?>" style="width:100%">
                <tr>
                    <?php if ('wp_role' != $agent_type) : ?>
                        <td class="pp-select-exception-agents">
                            <?php
                            // Select Groups / Users UI

                            echo '<div>';
                            echo '<div class="pp-agent-select">';

                            $args = array_merge($args, [
                                'suppress_extra_prefix' => true,
                                'ajax_selection' => true,
                                'display_stored_selections' => false,
                                'create_dropdowns' => true,
                                'op' => $op,
                                'via_item_type' => $via_item_type,
                            ]);

                            $pp_admin->agents()->agentsUI($agent_type, [], "{$op}:{$for_item_type}:{$agent_type}", [], $args);
                            echo '</div>';
                            echo '</div>';

                            $colspan = 'colspan="2"';
                            ?>
                        </td>
                    <?php else :
                        $colspan = '';  // for html5 compliance
                    endif;
                    ?>

                    <?php
                    $any_stored = empty($current_exceptions[$op][$agent_type])
                        ? 0
                        : count($current_exceptions[$op][$agent_type]);
                    ?>
                    <td class="pp-current-item-exceptions" style="width:100%">
                        <div class="pp-exc-wrap" style="overflow:auto;">
                            <table <?php if (!$any_stored) echo 'style="display:none"'; ?>>
                                <?php if ($hierarchical) : ?>
                                    <thead>
                                    <tr>
                                        <th></th>
                                        <th><?php printf(__('This %s', 'press-permit-core'), $type_obj->labels->singular_name); ?></th>
                                        <th><?php
                                            if ($caption = apply_filters('presspermit_item_assign_for_children_caption', '', $via_item_type))
                                                printf($caption);
                                            else
                                                printf(__('Sub-%s', 'press-permit-core'), $type_obj->labels->name);
                                            ?></th>
                                    </tr>
                                    </thead>
                                <?php endif; ?>
                                <tbody>
                                <?php // @todo: why is agent_id=0 in current_exceptions array?
                                if ($any_stored) {
                                    if ('wp_role' == $agent_type) {
                                        foreach ($current_exceptions[$op][$agent_type] as $agent_id => $agent_exceptions) {
                                            if ($agent_id && isset($this->data->agent_info[$agent_type][$agent_id])) {
                                                if ((false === strpos($this->data->agent_info[$agent_type][$agent_id]->name, '[WP ')) || defined('PRESSPERMIT_DELETED_ROLE_EXCEPTIONS_UI')) {
                                                    $this->render->drawRow(
                                                        $agent_type,
                                                        $agent_id,
                                                        $current_exceptions[$op][$agent_type][$agent_id],
                                                        $this->data->inclusions_active,
                                                        $this->data->agent_info[$agent_type][$agent_id],
                                                        compact('for_item_type', 'op', 'reqd_caps', 'hierarchical')
                                                    );
                                                }
                                            }
                                        }
                                    } else {
                                        foreach (array_keys($this->data->agent_info[$agent_type]) as $agent_id) {  // order by agent name
                                            if ($agent_id && isset($current_exceptions[$op][$agent_type][$agent_id])) {
                                                $this->render->drawRow(
                                                    $agent_type,
                                                    $agent_id,
                                                    $current_exceptions[$op][$agent_type][$agent_id],
                                                    $this->data->inclusions_active,
                                                    $this->data->agent_info[$agent_type][$agent_id],
                                                    compact('for_item_type', 'op', 'reqd_caps', 'hierarchical')
                                                );
                                            }
                                        }
                                    }
                                }
                                ?>
                                </tbody>

                                <tfoot<?php if ($any_stored < 2) echo ' style="display:none;"'; ?>>
                                <?php
                                $link_caption = ('wp_role' == $agent_type) ? __('default all', 'press-permit-core') : __('clear all', 'press-permit-core');
                                ?>
                                <tr>
                                    <td></td>
                                    <td style="text-align:center"><a
                                                href="#clear-item-exc"><?php echo $link_caption; ?></a></td>
                                    <?php if ($hierarchical) : ?>
                                        <td style="text-align:center"><a
                                                    href="#clear-sub-exc"><?php echo $link_caption; ?></a></td>
                                    <?php endif; ?>
                                </tr>
                                </tfoot>

                            </table>

                        </div>

                        <?php if (!$any_stored) : 
                            $op_obj = presspermit()->admin()->getOperationObject($op, $for_item_type);
                            $op_label = (!empty($op_obj->noun_label)) ? $op_obj->noun_label : $op_obj->label;
                            $agent_label = (!empty($agent_types[$agent_type]->labels)) ? $agent_types[$agent_type]->labels->singular_name : $agent_types[$agent_type]->label;
                            $caption = sprintf(__('%s permissions have not been added or blocked for any %s.', 'press-permit-core'), $op_label, $agent_label);
                        ?>
                            <div class="pp-no-exceptions"><?php echo $caption; ?></div>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <td class="pp-exception-actions" <?php echo $colspan; ?>>
                        <?php if ('wp_role' != $agent_type) : ?>
                            <a class="pp-select-exception-agents" href="#">
                                <?php ('user' == $agent_type) ? _e('select users', 'press-permit-core') : _e('select groups', 'press-permit-core'); ?>
                            </a>

                            <a class="pp-close-select-exception-agents" href="#"
                               style="display:none;"><?php _e('close', 'press-permit-core'); ?></a>
                        <?php
                        endif;
                        if ($pp_groups->groupTypeEditable($agent_type) && $pp_groups->userCan('pp_create_groups', 0, $agent_type)) :
                            ?>
                            &nbsp;&bull;&nbsp;
                            <a class="pp-create-exception-agent" href="admin.php?page=presspermit-group-new"
                               target="_blank">
                                <?php _e('create group', 'press-permit-core'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            </div>
            <?php
        } // end foreach group type caption

        echo '</div>'; // class pp-agents

        if (('read' == $op) && $pp->getOption('display_extension_hints')
            && (
                (('attachment' == $for_item_type) && !$pp->moduleActive('file-access'))
                || ! $pp->moduleActive('collaboration'))
        ) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/HintsItemExceptions.php');
            \PublishPress\Permissions\UI\HintsItemExceptions::itemHints($for_item_type);
        }

        if (('term' == $via_item_source) && in_array($op, ['read', 'edit'], true)) {
            $msg = __('To customize <strong>for a specific post status</strong>, edit the desired role / group / user permissions directly (Permissions > Groups or Users)', 'press-permit-core');
            echo "<div class='pp-exc-notes'>$msg</div>";
        }
    }
}
