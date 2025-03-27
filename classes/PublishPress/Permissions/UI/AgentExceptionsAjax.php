<?php

namespace PublishPress\Permissions\UI;

class AgentExceptionsAjax
{
    public function __construct() 
    {
        check_ajax_referer('pp-ajax');

        if (!$pp_for_type = PWP::GET_key('pp_for_type')) {
            exit;
        }

        if (!$pp_ajax_agent_exceptions = PWP::GET_key('pp_ajax_agent_exceptions')) {
            exit;
        }

        $pp = presspermit();

        if (!$pp->admin()->bulkRolesEnabled()) {
            exit;
        }

        $agent_type = PWP::GET_key('pp_agent_type');
        $agent_id = PWP::GET_int('pp_agent_id');

        $for_type = isset($_GET['pp_for_type']) ? PWP::sanitizeEntry(sanitize_text_field($_GET['pp_for_type'])) : '';
        $operation = PWP::GET_key('pp_operation');
        $via_type = PWP::GET_key('pp_via_type');
        $mod_type = PWP::GET_key('pp_mod_type');
        $item_id = PWP::GET_int('pp_item_id');

        if ('(all)' == $for_type) {
            $for_source_name = 'post';
            $via_source_name = 'term';
            $for_type = '';
        } else {
            if (!$for_type || post_type_exists($for_type))
                $for_source_name = 'post';
            elseif (taxonomy_exists($for_type))
                $for_source_name = 'term';
            else
                $for_source_name = $for_type;

            if (!$via_type && post_type_exists($for_type)) {
                $via_type = $for_type;
                $via_source_name = 'post';
            } else {
                if (post_type_exists($via_type))
                    $via_source_name = 'post';
                elseif (taxonomy_exists($via_type))
                    $via_source_name = 'term';
                else
                    $via_source_name = $via_type;
            }
        }

        switch ($pp_ajax_agent_exceptions) {

            case 'get_operation_options':
                // todo: deal with login timeout in JS to avoid multiple messages
                if (!is_user_logged_in()) {
                    echo '<span>' . esc_html__('(login timed out)', 'press-permit-core') . '</span>';
                    exit;
                }

                $ops = (('post' == $for_source_name) && ('attachment' != $for_type)) ? ['read' => esc_html__('Read', 'press-permit-core')] : [];
                $ops = apply_filters('presspermit_exception_operations', $ops, $for_source_name, $for_type);

                if ('pp_group' == $agent_type) {
                    $group = $pp->groups()->getGroup($agent_id);
                    if (in_array($group->metagroup_id, ['wp_anon', 'wp_all']) && !defined('PP_ALL_ANON_FULL_EXCEPTIONS')) {
                        $ops = \PressShack\LibArray::subset($ops, ['read']);
                    }
                }

                ?>
                <div>
                <?php foreach ($ops as $val => $title) :?>
                    <label><input type='radio' name='pp_select_x_operation' class='pp-select-x-operation' value='<?php echo esc_attr($val);?>'> <span><?php echo esc_html($title);?></span></label><br />
                <?php endforeach;?>
                </div>

                <?php
                break;

            case 'get_mod_options':
                // todo: deal with login timeout in JS to avoid multiple messages
                if (!is_user_logged_in()) {
                    echo '<span>' . esc_html__('(login timed out)', 'press-permit-core') . '</span>';
                    exit;
                }

                if ($agent_id && ('pp_group' == $agent_type)) {
                    $group = $pp->groups()->getGroup($agent_id);
                    $is_wp_role = ('wp_role' == $group->metagroup_type);
                } else
                    $is_wp_role = false;

                if ((!$is_wp_role
                        || !in_array($group->metagroup_id, ['wp_anon', 'wp_all'])
                        || ($pp->moduleActive('file-access') && 'attachment' == $for_type)
                        || defined('PP_ALL_ANON_FULL_EXCEPTIONS'))
                    && !defined('PP_NO_ADDITIONAL_ACCESS')
                ) {
                    $modes['additional'] = esc_html__('Enable:', 'press-permit-core');
                }

                if (('user' == $agent_type) || $is_wp_role || ('assign' == $operation) || defined('PP_GROUP_RESTRICTIONS')) {
                    // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
                    $modes['exclude'] = esc_html__('Block:', 'press-permit-core');
                }

                $modes['include'] = esc_html__('Limit to:', 'press-permit-core');

                $modes = apply_filters('presspermit_exception_modes', $modes, $for_source_name, $for_type, $operation);

                ?>
                <div>
                <?php foreach ($modes as $val => $title) :?>
                    <label><input type='radio' name='pp_select_x_mod_type' class='pp-select-x-mod-type' value='<?php echo esc_attr($val);?>'> <span>
                        <?php 
                        $tooltip_text = '';
                        if($title === 'Enable:') {
                            $tooltip_text = 'Expand access to allow specified items regardless of role capabilities or restrictions.';
                        } elseif($title === 'Block:') {
                            $tooltip_text = 'Restrict access by blocking specified items unless an "Enabled" exception is also stored.';
                        } elseif($title === 'Limit to:') {
                            $tooltip_text = 'Restrict access by limiting Role Capabilities to apply only for specified items. Users still need capabilities in their main role or supplemental roles.';
                        }
                        if(!empty($tooltip_text)) {
                            $this->generateTooltip($tooltip_text, $title, 'top', true);
                        }
                        ?>
                    </span></label><br />
                <?php endforeach;?>
                </div>

                <?php
                break;

            case 'get_via_type_options':
                // todo: deal with login timeout in JS to avoid multiple messages
                if (!is_user_logged_in()) {
                    echo '<option>' . esc_html__('(login timed out)', 'press-permit-core') . '</option>';
                    exit;
                }

                $types = [];

                if ('post' == $for_source_name) {
                    if ('associate' != $operation) {
                        if ('assign' != $operation) {  // 'assign' op only pertains to terms
                            if ($type_obj = get_post_type_object($for_type)) {
                                $types = ['' => esc_html__('selected:', 'press-permit-core')];
                            }
                        }

                        $type_arg = ($for_type) ? ['object_type' => $for_type] : [];
                        $taxonomies = $pp->getEnabledTaxonomies($type_arg, 'object');

                        if ($taxonomies) {
                            $tax_types = [];
                            foreach ($taxonomies as $_taxonomy => $tx) {
                                if ('nav_menu' == $_taxonomy) {
                                    if (in_array(get_locale(), ['en_EN', 'en_US'])) {
                                        $tx->labels->name = __('Nav Menus (Legacy)', 'press-permit-core');
                                    } else {
                                        $tx->labels->name .= ' (' . __('Legacy', 'press-permit-core') . ')';
                                    }
                                }

                                $tax_types[$_taxonomy] = sprintf(esc_html__('%s:', 'press-permit-core'), $tx->labels->name);
                            }

                            uasort($tax_types, 'strnatcasecmp');  // sort by values without resetting keys

                            $types = array_merge($types, $tax_types);
                        }
                    } else {
                        // 'associate' exceptions regulate parent assignment. This does not pertain to taxonomies, but may apply to other post types as specified by the filter.
                        $aff_types = (array)apply_filters('presspermit_parent_types', [$for_type], $for_type);

                        foreach ($aff_types as $_type) {
                            if ($type_obj = get_post_type_object($_type)) {
                                $types[$_type] =  sprintf(esc_html__('%s:', 'press-permit-core'), $type_obj->labels->name);
                            }
                        }
                    }
                } elseif (in_array($for_source_name, ['pp_group', 'pp_net_group'], true)) {
                    if ($group_type_obj = $pp->groups()->getGroupTypeObject($for_source_name)) {
                        $types[$for_source_name] = sprintf(esc_html__('%s:', 'press-permit-core'), $group_type_obj->labels->name);
                    }
                }

                $types = apply_filters('presspermit_exception_via_types', $types, $for_source_name, $for_type, $operation, $mod_type);

                foreach ($types as $val => $title) {
                    $class = ($for_type == $val) ? 'pp-post-object' : '';
                    echo "<option value='" . esc_attr($val) . "' class='" . esc_attr($class) . "'>". esc_html($title) . "</option>";
                }

                break;

            case 'get_assign_for_ui':
                if (!is_user_logged_in()) {
                    echo '<p>' . esc_html__('(login timed out)', 'press-permit-core') . '</p><div class="pp-checkbox">'
                        . '<input type="checkbox" name="pp_select_for_item" style="display:none">'
                        . '<input type="checkbox" name="pp_select_for_item" style="display:none"></div>';

                    exit;
                }

                if ($via_type) {
                    $type_obj = $pp->getTypeObject($via_source_name, $via_type);

                    echo '<div class="pp-checkbox">'
                        . '<input type="checkbox" id="pp_select_x_item_assign" name="pp_select_x_for_item" checked="checked" value="1" />'
                        . '<label id="pp_x_item_assign_label" for="pp_select_x_item_assign"> '
                        . sprintf(esc_html__('selected %s:', 'press-permit-core'), esc_html($type_obj->labels->name)) . '</label></div>';

                    if (
                        $type_obj && $type_obj->hierarchical
                        && apply_filters('presspermit_do_assign_for_children_ui', true, $for_type, compact('operation', 'mod_type'))
                    ) {
                        if (!$caption = apply_filters('presspermit_assign_for_children_caption', '', $for_type)) {
                            $caption = sprintf(esc_html__('sub-%s:', 'press-permit-core'), $type_obj->labels->name);
                        }

                        $checked = (apply_filters('presspermit_assign_for_children_checked', false, $for_type, compact('operation', 'mod_type')))
                            ? ' checked ' : '';

                        $disabled = (apply_filters('presspermit_assign_for_children_locked', false, $for_type, compact('operation', 'mod_type')))
                            ? ' disabled ' : '';

                        echo '<div class="pp-checkbox">'
                            . '<input type="checkbox" id="pp_select_x_child_assign" name="pp_select_x_for_children" value="1"'
                            . esc_attr($checked) . esc_attr($disabled) . ' /><label id="pp_x_child_assign_label" for="pp_select_x_child_assign"> '
                            . esc_html($caption) . '</label></div>';
                    }

                    do_action('presspermit_assign_for_ui_done', $for_source_name, $for_type, $operation, $mod_type);
                }

                break;

            case 'get_status_ui':
                if (!is_user_logged_in()) {
                    echo '<p>' . esc_html__('(login timed out)', 'press-permit-core') . '</p><div class="pp-checkbox">'
                        . '<input type="checkbox" name="pp_select_for_item" style="display:none">'
                        . '<input type="checkbox" name="pp_select_for_item" style="display:none"></div>';

                    exit;
                }

                $checked = ' checked ';

                echo '<p class="pp-checkbox">'
                    . '<input type="checkbox" id="pp_select_x_cond_post_status_" name="pp_select_x_cond[]" value=""' . esc_attr($checked) . ' /> '
                    . '<label for="pp_select_x_cond_post_status_">' . esc_html__('(all)', 'press-permit-core') . '</label>'
                    . '</p>';

                if (('post' != $for_source_name) || ($mod_type != 'additional')) {
                    break;
                }

                if ('term' == $via_source_name) {
                    if ('forum' != $for_type) {
                        $pvt_obj = get_post_status_object('private');

                        echo '<p class="pp-checkbox pp_select_private_status">'
                            . '<input type="checkbox" id="pp_select_x_cond_post_status_private" name="pp_select_x_cond[]" value="post_status:private" />'
                            . '<label for="pp_select_x_cond_post_status_private"> ' . sprintf(esc_html__('%s Visibility', 'press-permit-core'), esc_html($pvt_obj->label)) . '</label>'
                            . '</p>';
                    }
                }

                $type_obj = get_post_type_object($for_type);
                $var = "{$operation}_{$for_type}";
                $type_caps = isset($type_obj->cap->$var) ? (array)$type_obj->cap->$var : [];

                do_action('presspermit_permissions_status_ui_done', $for_type, $type_caps);

                do_action('presspermit_exceptions_status_ui_done', $for_type, compact('via_source_name', 'operation', 'type_caps'));

                break;

            case 'get_item_path':
                require_once(PRESSPERMIT_CLASSPATH_COMMON . '/Ancestry.php');

                if ('term' == $via_source_name) {
                    echo esc_html($item_id . chr(13) . \PressShack\Ancestry::getTermPath($item_id, $via_type));
                } elseif ('post' == $via_source_name) {
                    echo esc_html($item_id . chr(13) . \PressShack\Ancestry::getPostPath($item_id));
                }

                break;
        } // end switch
    }

    function generateTooltip($tooltip, $text = '', $position = 'top', $useIcon = true)
    {
        ?>
        <span data-toggle="tooltip" data-placement="<?php esc_attr_e($position); ?>">
        <?php esc_html_e($text);?>
        <span class="tooltip-text"><span><?php esc_html_e($tooltip);?></span><i></i></span>
        <?php 
        if ($useIcon) : ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 50 50" style="margin-left: 4px; vertical-align: text-bottom;">
                <path d="M 25 2 C 12.264481 2 2 12.264481 2 25 C 2 37.735519 12.264481 48 25 48 C 37.735519 48 48 37.735519 48 25 C 48 12.264481 37.735519 2 25 2 z M 25 4 C 36.664481 4 46 13.335519 46 25 C 46 36.664481 36.664481 46 25 46 C 13.335519 46 4 36.664481 4 25 C 4 13.335519 13.335519 4 25 4 z M 25 11 A 3 3 0 0 0 25 17 A 3 3 0 0 0 25 11 z M 21 21 L 21 23 L 23 23 L 23 36 L 21 36 L 21 38 L 29 38 L 29 36 L 27 36 L 27 21 L 21 21 z"></path>
            </svg>
        <?php
        endif; ?>
        </span>
        <?php
    }
}
