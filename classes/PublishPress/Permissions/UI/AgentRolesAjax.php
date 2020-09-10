<?php

namespace PublishPress\Permissions\UI;

//use \PressShack\LibWP as PWP;
//use \PressShack\LibArray as Arr;

class AgentRolesAjax
{
    public function __construct() 
    {
        if (empty($_GET['pp_source_name']) || empty($_GET['pp_object_type'])) {
            exit;
        }

        $pp = presspermit();
        $pp_admin = $pp->admin();

        if (!$pp->admin()->bulkRolesEnabled()) {
            exit;
        }

        $for_item_source = sanitize_key($_GET['pp_source_name']);
        $for_item_type = sanitize_key($_GET['pp_object_type']);
        $role_name = (isset($_GET['pp_role_name'])) ? PWP::sanitizeCSV($_GET['pp_role_name']) : '';

        $filterable_vars = ['for_item_source', 'for_item_type', 'role_name'];
        if ($force_vars = apply_filters('presspermit_ajax_role_ui_vars', [], compact($filterable_vars))) {
            $_vars = Arr::subset($force_vars, $filterable_vars);
            foreach (array_keys($_vars) as $var) {
                $$var = $_vars[$var];
            }
        }

        $html = '';

        switch ($_GET['pp_ajax_agent_roles']) {

            case 'get_role_options':
                if (!is_user_logged_in()) {
                    echo '<option>' . __('(login timed out)', 'press-permit-core') . '</option>';
                    exit;
                }

                global $wp_roles;

                require_once(PRESSPERMIT_CLASSPATH.'/RoleAdmin.php');

                if ($roles = \PublishPress\Permissions\RoleAdmin::getTypeRoles($for_item_source, $for_item_type)) {
                    foreach ($roles as $_role_name => $role_title) {
                        if ($pp_admin->userCanAdminRole($_role_name, $for_item_type)) {
                            $selected = ($_role_name == $role_name) ? "selected='selected'" : '';
                            $html .= "<option value='$_role_name' $selected>$role_title</option>";
                        }
                    }
                } else {
                    $caption = __('(invalid role definition)', 'press-permit-core');
                    $html .= "<option value='' $selected>$caption</option>";
                }
                break;

            case 'get_conditions_ui':
                if (!is_user_logged_in()) {
                    echo '<p>' . __('(login timed out)', 'press-permit-core') . '</p><div class="pp-checkbox">'
                        . '<input type="checkbox" name="pp_select_for_item" style="display:none">'
                        . '<input type="checkbox" name="pp_select_for_item" style="display:none"></div>';

                    exit;
                }

                $checked = (!empty($pp->role_defs->direct_roles[$role_name])) ? ' checked="checked"' : '';

                $standard_stati_ui = '<p class="pp-checkbox">'
                    . '<input type="checkbox" id="pp_select_cond_" name="pp_select_cond[]" value=""' . $checked . ' /> '
                    . '<label id="lbl_pp_select_cond_" for="pp_select_cond_">' . __('Standard statuses', 'press-permit-core') . '</label>'
                    . '</p>';

                if (('post' != $for_item_source) || ('attachment' == $for_item_type)) {
                    $html = $standard_stati_ui;
                } elseif ($role_name) {
                    $type_obj = $pp->getTypeObject($for_item_source, $for_item_type);
                    $type_caps = $pp->getRoleCaps($role_name);

                    $direct_assignment = (false === strpos($role_name, ':'));

                    if (!empty($type_caps['edit_posts']) || $direct_assignment) {
                        $html = $standard_stati_ui;
                    } else {
                        $html = '';

                        if (empty($type_caps)) {
                            $arr_role_name = explode(':', $role_name);
                            if (in_array($arr_role_name[0], ['contributor', 'author', 'editor', 'revisor'], true)) {
                                $html = $standard_stati_ui;
                            }
                        }
                    }

                    // edit_private, delete_private caps are normally cast from pattern role
                    if (isset($type_caps['read']) && (empty($type_caps['edit_posts']) || $direct_assignment)) {
                        $pvt_obj = get_post_status_object('private');

                        $html .= '<p class="pp-checkbox pp_select_private_status">'
                            . '<input type="checkbox" id="pp_select_cond_post_status_private" name="pp_select_cond[]" value="post_status:private" />'
                            . '<label for="pp_select_cond_post_status_private"> ' . sprintf(__('%s Visibility', 'press-permit-core'), $pvt_obj->label) . '</label>'
                            . '</p>';
                    }

                    if ($direct_assignment) {
                        break;
                    }

                    $html = apply_filters('presspermit_permission_status_ui', $html, $for_item_type, $type_caps, $role_name);
                }

                break;
        } // end switch

        if ($html) {
            echo $html;
        }
    }
}
