<?php

namespace PublishPress\Permissions\UI\Dashboard;

class ItemAjax
{
    public function __construct() {
        if (!$via_item_source = presspermit_GET_key('via_item_source')) {
            exit;
		}

        $html = '';

        switch ($_GET['pp_ajax_item']) {

            case 'get_agent_exception_ui':
                if (!is_user_logged_in()) {
                    echo '<option>' . esc_html__('(login timed out)', 'press-permit-core') . '</option>';
                    exit;
                }

                if (!$arr_sfx = explode(':', PWP::sanitizeCSV($_GET['id_sfx']))) {
                    return '';
                }

                $op = $arr_sfx[0];
                $for_item_type = $arr_sfx[1];
                $agent_type = $arr_sfx[2];
                $item_id = (int) $_GET['item_id'];
                $for_item_source = (taxonomy_exists($for_item_type)) ? 'term' : 'post';
                $agent_ids = explode(',', PWP::sanitizeCSV($_GET['agent_ids']));

                echo "<!--ppSfx-->" . esc_html("$op|$for_item_type|$agent_type") . "<--ppSfx-->"
                    . "<!--ppResponse-->";

                require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/ItemExceptionsData.php');
                $exc_data = new ItemExceptionsData();

                $args = ['post_types' => (array)$for_item_type, 'agent_type' => $agent_type, 'operations' => $op, 'agent_id' => $agent_ids];

                $exc_data->loadExceptions(
                    pp_permissions_sanitize_key($_GET['via_item_source']),
                    $for_item_source,
                    pp_permissions_sanitize_key($_GET['via_item_type']),
                    $item_id,
                    $args
                );

                require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/ItemExceptionsRenderUI.php');
                $exc_render = new ItemExceptionsRenderUI();

                $echo = false;
                $reqd_caps = false;
                $hierarchical = (presspermit_is_GET('via_item_source', 'term'))
                    ? is_taxonomy_hierarchical($via_item_type)
                    : is_post_type_hierarchical($via_item_type);

                $hierarchical = apply_filters('presspermit_do_assign_for_children_ui', $hierarchical, $_GET['via_item_type'], $args);
                $default_select = true;

                $exc_render->setOptions($agent_type);

                foreach ($agent_ids as $agent_id) {
                    if (!$agent_id)
                        continue;

                    $exc_render->drawRow(
                        $agent_type,
                        $agent_id,
                        [],
                        $exc_data->inclusions_active,
                        $exc_data->agent_info[$agent_type][$agent_id],
                        compact('echo', 'default_select', 'for_item_type', 'op', 'reqd_caps', 'hierarchical')
                    );
                }

                echo "<--ppResponse-->";

                break;
        } // end switch
    }
}
