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

        $agent_types['wp_role'] = (object)['labels' => (object)['name' => esc_html__('Roles', 'press-permit-core'), 'singular_name' => esc_html__('Role')]];

        $agent_types = apply_filters('presspermit_list_group_types', array_merge($agent_types, $pp->groups()->getGroupTypes([], 'object')));

        $agent_types['user'] = (object)['labels' => (object)['name' => esc_html__('Users'), 'singular_name' => esc_html__('User', 'press-permit-core')]];

        static $drew_itemroles_marker;
        if (empty($drew_itemroles_marker)) {
            echo "<input type='hidden' name='pp_post_exceptions' value='true' />";
            $drew_itemroles_marker = true;
        }

        $current_exceptions_saved = (isset($this->data->current_exceptions[$for_item_type]))
            ? $this->data->current_exceptions[$for_item_type]
            : [];
        $current_exceptions = [];

        // Check for blockage of Everyone, Logged In metagroups
        $metagroup_exclude = [];
        $is_auth_metagroup = [];

        if ($current_exceptions && !empty($current_exceptions[$op]) && !empty($current_exceptions[$op]['wp_role'])) {
            foreach ($this->data->agent_info['wp_role'] as $agent_id => $role) {
                if (!empty($role->metagroup_id) && in_array($role->metagroup_id, ['wp_auth', 'wp_all', 'wp_anon'])) {
                    $is_auth_metagroup[$agent_id] = true;

                    if (in_array($role->metagroup_id, ['wp_auth', 'wp_all'])
                        && !empty($current_exceptions[$op]['wp_role'][$agent_id]) 
                        && !empty($current_exceptions[$op]['wp_role'][$agent_id]['item'])
                        && !empty($current_exceptions[$op]['wp_role'][$agent_id]['item']['exclude'])
                    ) {
                        $metagroup_exclude[$role->metagroup_id] = true;
                    }
                }
            }
        }

        // ========== OBJECT / TERM EXCEPTION DROPDOWNS ============
        $toggle_agents = count($agent_types) > 1;
        if ($toggle_agents) {
            global $is_ID;
            $class_selected = 'agp-selected_agent agp-agent';
            $class_unselected = 'agp-unselected_agent agp-agent';
            $bottom_margin = (!empty($is_IE)) ? '-0.7em' : 0;

            $default_agent_type = 'wp_role';

            echo "<div class='hide-if-not-js' style='margin:0 0 " . esc_attr($bottom_margin) . " 0'>"
                . "<ul class='pp-list_horiz' style='margin-bottom:-0.1em'>";

            foreach ($agent_types as $agent_type => $gtype_obj) {
                $label = (!empty($current_exceptions[$op][$agent_type]))
                    ? sprintf(esc_html__('%1$s (%2$s)', 'press-permit-core'), $gtype_obj->labels->name, count($current_exceptions[$op][$agent_type]))
                    : $gtype_obj->labels->name;

                $class = ($default_agent_type == $agent_type) ? $class_selected : $class_unselected;
                echo "<li class='" . esc_attr($class) . "'><a href='javascript:void(0)' class='" . esc_attr("{$op}-{$for_item_type}-{$agent_type}") . "'>" . esc_html($label) . '</a></li>';
            }

            echo '</ul></div>';
        }

        $class = "pp-agents pp-exceptions";

        //need effective line break here if not IE
        echo "<div style='clear:both;' class='" . esc_attr($class) . "'>";

        foreach (array_keys($agent_types) as $agent_type) {
            $hide_class = ($toggle_agents && ($agent_type != $default_agent_type)) ? 'hide-if-js' : '';

            echo "\r\n<div id='" . esc_attr("{$op}-{$for_item_type}-{$agent_type}") . "' class='" . esc_attr($hide_class) . "' style='overflow-x:auto'>";

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

                    // Use saved exceptions if available, otherwise initialize as empty array
                    $current_exceptions[$op][$agent_type][$agent_id] = $current_exceptions_saved[$op][$agent_type][$agent_id] ?? [];
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
            $any_stored = empty($current_exceptions[$op][$agent_type]) ? 0 : count($current_exceptions[$op][$agent_type]);
            ?>

            <table class="pp-item-exceptions-ui pp-exc-<?php echo esc_attr($agent_type); ?>" style="width:100%">
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

                            $colspan = '2';
                            ?>
                            
                        </td>
                    <?php else :
                        $colspan = '';
                    endif; ?>
                    <td class="pp-current-item-exceptions" style="width:100%">
                        <div class="pp-exc-wrap" style="overflow:auto;">
                            <table <?php if (!$any_stored) echo 'style="display:none"'; ?>>
                                <?php if ($hierarchical) : ?>
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th><?php printf(esc_html__('This %s', 'press-permit-core'), esc_html($type_obj->labels->singular_name)); ?></th>
                                            <th><?php
                                                if ($caption = apply_filters('presspermit_item_assign_for_children_caption', '', $via_item_type))
                                                    printf(esc_html($caption));
                                                else
                                                    printf(esc_html__('Sub-%s', 'press-permit-core'), esc_html($type_obj->labels->name));
                                                ?></th>
                                        </tr>
                                    </thead>
                                <?php endif; ?>
                                <tbody>
                                    <?php // todo: why is agent_id=0 in current_exceptions array?
                                    if ($any_stored) {
                                        if ('wp_role' == $agent_type) {

                                            // Buffer original reqd_caps value
                                            $_reqd_caps = (is_array($reqd_caps)) ? array_values($reqd_caps) : $reqd_caps;

                                            // error_log(print_r($current_exceptions, true));
                                            foreach ($current_exceptions[$op][$agent_type] as $agent_id => $agent_exceptions) {
                                                if ($agent_id && isset($this->data->agent_info[$agent_type][$agent_id])) {
                                                    if ((false === strpos($this->data->agent_info[$agent_type][$agent_id]->name, '[WP ')) || defined('PRESSPERMIT_DELETED_ROLE_EXCEPTIONS_UI')) {
                                                        
                                                        // If Everyone / Logged In metagroup is blocked, indicate effect on other roles
                                                        if ((!empty($metagroup_exclude['wp_all']) || !empty($metagroup_exclude['wp_auth'])) && empty($is_auth_metagroup[$agent_id])) {
                                                            if (is_array($_reqd_caps)) {
                                                                $reqd_caps = array_merge($_reqd_caps, ['pp_administer_content']);
                                                            } else {
                                                                $reqd_caps = ['pp_administer_content'];
                                                            }
                                                        } else {
                                                            $reqd_caps = $_reqd_caps;
                                                        }

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

                                            // Restore original reqd_caps value
                                            $reqd_caps = $_reqd_caps;

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

                                <tfoot <?php if ($any_stored < 2) echo 'style="display:none;"'; ?>>
                                    <?php
                                    $link_caption = ('wp_role' == $agent_type) ? esc_html__('Default All', 'press-permit-core') : '';
                                    if(!empty($link_caption)) :
                                    ?>
                                    <tr>
                                        <td></td>
                                        <td style="text-align:center"><a
                                                href="#clear-item-exc"><?php echo esc_html($link_caption); ?></a></td>
                                        <?php if ($hierarchical) : ?>
                                            <td style="text-align:center"><a
                                                    href="#clear-sub-exc"><?php echo esc_html($link_caption); ?></a></td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endif; ?>
                                    </tfoot>

                            </table>

                        </div>
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
    }
}
