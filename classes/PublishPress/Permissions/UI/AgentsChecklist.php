<?php

namespace PublishPress\Permissions\UI;

//use \PublishPress\Permissions\DB as DB;

/**
 * AgentsChecklist class
 *
 * @package PressPermit
 * @author Kevin Behrens <kevin@agapetry.net>
 * @copyright Copyright (c) 2019, PublishPress
 *
 */
class AgentsChecklist
{
    // for group selection only
    public static function display($agents_subset, $agent_type, $all_agents, $name_attrib, $item_assignments, $args)
    {
        $defaults = ['eligible_ids' => [], 'locked_ids' => [], 'show_subset_caption' => true, 'hide_checkboxes' => false];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $caption_length_limit = (defined('PP_AGENTS_CAPTION_LIMIT')) ? PP_AGENTS_CAPTION_LIMIT : 20;
        $emsize_threshold = (defined('PP_AGENTS_EMSIZE_THRESHOLD')) ? PP_AGENTS_EMSIZE_THRESHOLD : 4;

        static $exec_count = 0;
        $exec_count++;  // support abbreviated checkbox id for label association

        if ('eligible' == $agents_subset) {
            $caption = __('eligible (%d):', 'press-permit-core');

            $item_assignments = array_intersect_key($item_assignments, $all_agents);

            if (!$agent_count = count($all_agents) - count($item_assignments))
                return;
        } else {
            $caption = __('current (%d):', 'press-permit-core');
            $agent_count = count($item_assignments);
        }

        echo "<div>";
        echo "<ul class='pp-list_horiz'><li>";
        if ($show_subset_caption)
            printf("<div class='pp-agents_caption'><strong>$caption</strong></div>", $agent_count);
        echo '</li>';

        echo '</ul>';

        // -------- construct captions and determine required list item width -----------
        $captions = $full_captions = $draw_agents = [];

        global $wp_locale;
        $rtl = (isset($wp_locale) && ('rtl' == $wp_locale->text_direction));

        $longest_caption_length = 10;

        foreach ($all_agents as $agent) {
            $id = $agent->ID;
            $skip = false;

            switch ($agents_subset) {
                case 'current':
                    if (!isset($item_assignments[$id])) $skip = true;
                    break;
                default: //'eligible'
                    if (isset($item_assignments[$id])) $skip = true;
                    if ($eligible_ids && !in_array($id, $eligible_ids)) $skip = true;
            }

            if ($skip) {
                unset($all_agents[$id]);
                continue;
            }

            if (('pp_group' == $agent_type) && $agent->metagroup_id)
                $caption = \PublishPress\Permissions\DB\Groups::getMetagroupName($agent->metagroup_type, $agent->metagroup_id, $agent->name);
            else
                $caption = $agent->name;

            if (strlen($caption) > $caption_length_limit) {
                $full_captions[$id] = $caption;

                if ($rtl)
                    $caption = '...' . substr($caption, strlen($caption) - $caption_length_limit);
                else
                    $caption = substr($caption, 0, $caption_length_limit) . '...';
            }

            if (strlen($caption) > $longest_caption_length) {
                $longest_caption_length = (strlen($caption) >= $caption_length_limit) ? $caption_length_limit + 2 : strlen($caption);
            }

            $captions[$id] = $caption;
        } //-------- end caption construction --------------

        if ($agent_count > $emsize_threshold) {
            $ems_per_character = (defined('PP_UI_EMS_PER_CHARACTER')) ? PP_UI_EMS_PER_CHARACTER : 0.85;
            $list_width_ems = $ems_per_character * $longest_caption_length;

            $ul_class = 'pp-agents-list_' . intval($list_width_ems);

            echo "<div id='div_{$agents_subset}_{$name_attrib}' class='pp-{$agent_type} pp-{$agents_subset}'>"
                . "<div class='pp-agents_emsized'>"
                . "<ul class='pp-agents-list $ul_class' id='list_{$agents_subset}_{$name_attrib}'>";
        } else {
            $ul_class = "pp-agents-list_auto";
            echo "<div class='pp-{$agent_type}'>"
                . "<ul class='pp-agents-list $ul_class' id='list_{$agents_subset}_{$name_attrib}'>";
        }

        if (presspermit()->groups()->groupTypeEditable($agent_type)) {
            $edit_link_base = apply_filters('presspermit_groups_base_url', 'admin.php')
                . "?page=presspermit-edit-permissions&amp;action=edit&amp;agent_type=$agent_type&amp;agent_id=";

            $edit_title_text = __('view / edit group', 'press-permit-core');
            $edit_caption = __('edit', 'press-permit-core');
        } else
            $edit_link_base = '';

        foreach ($all_agents as $agent) {
            $id = $agent->ID;

            if (!empty($agent->metagroup_id)) {
                $display_name = (isset($agent->display_name)) ? $agent->display_name : '';

                $li_title = "title='"
                    . \PublishPress\Permissions\DB\Groups::getMetagroupDescript($agent->metagroup_type, $agent->metagroup_id, $display_name)
                    . "'";
            } elseif (isset($full_captions[$id]))
                $li_title = "title='{$full_captions[$id]}'";
            else
                $li_title = "title='{$captions[$id]}'";

            $checked = (isset($item_assignments[$id])) ? ' checked="checked"' : '';
            $disabled = ($locked_ids && in_array($id, $locked_ids)) ? " disabled='disabled'" : '';

            echo "<li $li_title>";

            if ($hide_checkboxes)
                echo '&bull; ';
            else
                echo "<input type='checkbox' name='{$name_attrib}[]'{$disabled}{$checked} value='$id' id='r{$exec_count}_{$id}' />";

            echo "<label for='r{$exec_count}_{$id}'>";
            echo ' ' . $captions[$id];
            echo '</label>';

            if ($edit_link_base && presspermit()->groups()->userCan('pp_edit_groups', $id, $agent_type))
                echo ' <a href=" ' . $edit_link_base . $id . '" style="display:none" target="_blank" title="' . $edit_title_text . '">'
                    . $edit_caption . '</a>';

            echo '</li>';
        } //foreach agent

        echo "<li></li></ul>"; // prevent invalid markup if no other li's

        if ($agent_count > $emsize_threshold)
            echo '</div>';

        echo '</div></div>';
    }
}
