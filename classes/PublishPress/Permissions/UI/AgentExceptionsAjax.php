<?php

namespace PublishPress\Permissions\UI;

class AgentExceptionsAjax
{
    public function __construct() 
    {

        if (empty($_GET['pp_for_type'])) {
            exit;
        }

        $pp = presspermit();

        if (!$pp->admin()->bulkRolesEnabled()) {
            exit;
        }

        $agent_type = sanitize_key($_GET['pp_agent_type']);
        $agent_id = (int)$_GET['pp_agent_id'];

        $for_type = PWP::sanitizeCSV($_GET['pp_for_type']);
        $operation = (isset($_GET['pp_operation'])) ? sanitize_key($_GET['pp_operation']) : '';
        $via_type = (isset($_GET['pp_via_type'])) ? sanitize_key($_GET['pp_via_type']) : '';
        $mod_type = (isset($_GET['pp_mod_type'])) ? sanitize_key($_GET['pp_mod_type']) : '';
        $item_id = (isset($_GET['pp_item_id'])) ? (int)$_GET['pp_item_id'] : 0;

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

        $html = '';

        switch ($_GET['pp_ajax_agent_exceptions']) {

            case 'get_operation_options':
                // @todo: deal with login timeout in JS to avoid multiple messages
                if (!is_user_logged_in()) {
                    echo '<span>' . __('(login timed out)', 'press-permit-core') . '</span>';
                    exit;
                }

                $ops = (('post' == $for_source_name) && ('attachment' != $for_type)) ? ['read' => __('Read', 'press-permit-core')] : [];
                $ops = apply_filters('presspermit_exception_operations', $ops, $for_source_name, $for_type);

                if ('pp_group' == $agent_type) {
                    $group = $pp->groups()->getGroup($agent_id);
                    if (in_array($group->metagroup_id, ['wp_anon', 'wp_all']) && !defined('PP_ALL_ANON_FULL_EXCEPTIONS')) {
                        $ops = \PressShack\LibArray::subset($ops, ['read']);
                    }
                }

                $html = '<div>';
                foreach ($ops as $val => $title) {
                    $html .= "<label><input type='radio' name='pp_select_x_operation' class='pp-select-x-operation' value='$val'> <span>$title</span></label><br />";
                }
                $html .= '</div>';

                break;

            case 'get_mod_options':
                // @todo: deal with login timeout in JS to avoid multiple messages
                if (!is_user_logged_in()) {
                    echo '<span>' . __('(login timed out)', 'press-permit-core') . '</span>';
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
                    $modes['additional'] = __('Enable:', 'press-permit-core');
                }

                if (('user' == $agent_type) || $is_wp_role || ('assign' == $operation) || defined('PP_GROUP_RESTRICTIONS')) {
                    $modes['exclude'] = __('Block:', 'press-permit-core');
                }

                $modes['include'] = __('Limit to:', 'press-permit-core');

                $modes = apply_filters('presspermit_exception_modes', $modes, $for_source_name, $for_type, $operation);

                $html = '<div>';
                foreach ($modes as $val => $title) {
                    $html .= "<label><input type='radio' name='pp_select_x_mod_type' class='pp-select-x-mod-type' value='$val'> <span>$title</span></label><br />";
                }
                $html .= '</div>';
                break;

            case 'get_via_type_options':
                // @todo: deal with login timeout in JS to avoid multiple messages
                if (!is_user_logged_in()) {
                    echo '<option>' . __('(login timed out)', 'press-permit-core') . '</option>';
                    exit;
                }

                $types = [];

                if ('post' == $for_source_name) {
                    if ('associate' != $operation) {
                        if ('assign' != $operation) {  // 'assign' op only pertains to terms
                            if ($type_obj = get_post_type_object($for_type)) {
                                $types = ['' => __('selected:', 'press-permit-core')];
                            }
                        }

                        $type_arg = ($for_type) ? ['object_type' => $for_type] : [];
                        $taxonomies = $pp->getEnabledTaxonomies($type_arg, 'object');

                        if ($taxonomies) {
                            $tax_types = [];
                            foreach ($taxonomies as $_taxonomy => $tx) {
                                $tax_types[$_taxonomy] = sprintf(__('%s:', 'press-permit-core'), $tx->labels->name);
                            }

                            uasort($tax_types, 'strnatcasecmp');  // sort by values without resetting keys

                            $types = array_merge($types, $tax_types);
                        }
                    } else {
                        // 'associate' exceptions regulate parent assignment. This does not pertain to taxonomies, but may apply to other post types as specified by the filter.
                        $aff_types = (array)apply_filters('presspermit_parent_types', [$for_type], $for_type);

                        foreach ($aff_types as $_type) {
                            if ($type_obj = get_post_type_object($_type)) {
                                $types[$_type] =  sprintf(__('%s:', 'press-permit-core'), $type_obj->labels->name);
                            }
                        }
                    }
                } elseif (in_array($for_source_name, ['pp_group', 'pp_net_group'], true)) {
                    if ($group_type_obj = $pp->groups()->getGroupTypeObject($for_source_name)) {
                        $types[$for_source_name] = sprintf(__('%s:', 'press-permit-core'), $group_type_obj->labels->name);
                    }
                }

                $types = apply_filters('presspermit_exception_via_types', $types, $for_source_name, $for_type, $operation, $mod_type);

                foreach ($types as $val => $title) {
                    $class = ($for_type == $val) ? ' class="pp-post-object"' : '';
                    $html .= "<option value='$val'$class>$title</option>";
                }

                break;

            case 'get_assign_for_ui':
                if (!is_user_logged_in()) {
                    echo '<p>' . __('(login timed out)', 'press-permit-core') . '</p><div class="pp-checkbox">'
                        . '<input type="checkbox" name="pp_select_for_item" style="display:none">'
                        . '<input type="checkbox" name="pp_select_for_item" style="display:none"></div>';

                    exit;
                }

                if ($via_type) {
                    $type_obj = $pp->getTypeObject($via_source_name, $via_type);

                    $html = '<div class="pp-checkbox">'
                        . '<input type="checkbox" id="pp_select_x_item_assign" name="pp_select_x_for_item" checked="checked" value="1" />'
                        . '<label id="pp_x_item_assign_label" for="pp_select_x_item_assign"> '
                        . sprintf(__('selected %s:', 'press-permit-core'), $type_obj->labels->name) . '</label></div>';

                    if (
                        $type_obj && $type_obj->hierarchical
                        && apply_filters('presspermit_do_assign_for_children_ui', true, $for_type, compact('operation', 'mod_type'))
                    ) {
                        if (!$caption = apply_filters('presspermit_assign_for_children_caption', '', $for_type)) {
                            $caption = sprintf(__('sub-%s:', 'press-permit-core'), $type_obj->labels->name);
                        }

                        $checked = (apply_filters('presspermit_assign_for_children_checked', false, $for_type, compact('operation', 'mod_type')))
                            ? ' checked="checked" ' : '';

                        $disabled = (apply_filters('presspermit_assign_for_children_locked', false, $for_type, compact('operation', 'mod_type')))
                            ? ' disabled="disabled" ' : '';

                        $html .= '<div class="pp-checkbox">'
                            . '<input type="checkbox" id="pp_select_x_child_assign" name="pp_select_x_for_children" value="1"'
                            . $checked . $disabled . ' /><label id="pp_x_child_assign_label" for="pp_select_x_child_assign"> '
                            . $caption . '</label></div>';
                    }

                    $html = apply_filters('presspermit_assign_for_ui', $html, $for_source_name, $for_type, $operation, $mod_type);
                }

                break;

            case 'get_status_ui':
                if (!is_user_logged_in()) {
                    echo '<p>' . __('(login timed out)', 'press-permit-core') . '</p><div class="pp-checkbox">'
                        . '<input type="checkbox" name="pp_select_for_item" style="display:none">'
                        . '<input type="checkbox" name="pp_select_for_item" style="display:none"></div>';

                    exit;
                }

                $checked = ' checked="checked"';

                $html = '<p class="pp-checkbox">'
                    . '<input type="checkbox" id="pp_select_x_cond_post_status_" name="pp_select_x_cond[]" value=""' . $checked . ' /> '
                    . '<label for="pp_select_x_cond_post_status_">' . __('(all)', 'press-permit-core') . '</label>'
                    . '</p>';

                if (('post' != $for_source_name) || ($mod_type != 'additional')) {
                    break;
                }

                if ('term' == $via_source_name) {
                    if ('forum' != $for_type) {
                        $pvt_obj = get_post_status_object('private');

                        $html .= '<p class="pp-checkbox pp_select_private_status">'
                            . '<input type="checkbox" id="pp_select_x_cond_post_status_private" name="pp_select_x_cond[]" value="post_status:private" />'
                            . '<label for="pp_select_x_cond_post_status_private"> ' . sprintf(__('%s Visibility', 'press-permit-core'), $pvt_obj->label) . '</label>'
                            . '</p>';
                    }
                }

                $type_obj = get_post_type_object($for_type);
                $var = "{$operation}_{$for_type}";
                $type_caps = isset($type_obj->cap->$var) ? (array)$type_obj->cap->$var : [];

                $html = apply_filters('presspermit_permission_status_ui', $html, $for_type, $type_caps);

                $html = apply_filters('presspermit_exceptions_status_ui', $html, $for_type, compact('via_source_name', 'operation', 'type_caps'));

                break;

            case 'get_item_path':
                require_once(PRESSPERMIT_CLASSPATH_COMMON . '/Ancestry.php');

                if ('term' == $via_source_name) {
                    $html = $item_id . chr(13) . \PressShack\Ancestry::getTermPath($item_id, $via_type);
                } elseif ('post' == $via_source_name) {
                    $html = $item_id . chr(13) . \PressShack\Ancestry::getPostPath($item_id);
                }

                break;
        } // end switch

        if ($html) {
            echo $html;
        }
    }
}
