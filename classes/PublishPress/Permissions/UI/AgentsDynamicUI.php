<?php

namespace PublishPress\Permissions\UI;

//use \PressShack\LibWP as PWP;

/**
 * AgentsDynamicUI class
 *
 * @package PressPermit
 * @author Kevin Behrens <kevin@agapetry.net>
 * @copyright Copyright (c) 2019, PublishPress
 *
 */
class AgentsDynamicUI
{
    private $agents_js_queue = false;

    // output seach textbox & button, results list, "Add All" button
    public function display($agent_type, $id_suffix, $current_selections = [], $args = [])
    {
        $defaults = [
            'agent_id' => 0,
            'context' => '',
            'label_select' => _x('Select &gt;', 'user', 'press-permit-core'),
            'label_unselect' => _x('&lt; Unselect', 'user', 'press-permit-core'),
            'label_selections' => __('Current Selections:', 'press-permit-core'),
            'display_stored_selections' => true,
            'create_dropdowns' => false,
            'width' => '',
            'width_current' => '',
            'label_headline' => true,
            'multi_select' => true,
            'use_selection_js' => true,
        ];

        $args = apply_filters('presspermit_agents_selection_ui_args', array_merge($defaults, $args), $agent_type, $id_suffix);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        $pp = presspermit();
        
        $width = ($width) ? "width:{$width}px;" : '';

        $this->registerAjaxScripts($agent_type, $id_suffix, $context, $agent_id, $args);

        if ('user' == $agent_type) {
            if (defined('PP_USER_LASTNAME_SEARCH') && !defined('PP_USER_SEARCH_FIELD')) {
                $default_search_field = 'last_name';
            } elseif (defined('PP_USER_SEARCH_FIELD')) {
                $default_search_field = PP_USER_SEARCH_FIELD;
            } else {
                $default_search_field = '';
            }
        }

        if (true === $label_headline) {
            if ('user' == $agent_type) {
                if ($default_search_field) {
                    $search_caption = __(ucwords(str_replace('_', ' ', $default_search_field)), 'press-permit-core');
                    $label_headline = sprintf(__('Find Users by %s', 'press-permit-core'), $search_caption);
                } else {
                    $label_headline = __('Search Users', 'press-permit-core');
                }
            } else {
                $label_headline = __('Search Custom Groups', 'press-permit-core');
            }
        }
        ?>
        <div>

        </div>
        <table id="pp-agent-selection_<?php echo $id_suffix; ?>-wrapper" class="pp-agents-selection">
            <tr>
                <td id="pp-agent-selection_<?php echo $id_suffix; ?> " style="vertical-align:top">
                    <div class="pp-search-box-with-icon-wrapper">
                        <input id="agent_search_text_<?php echo $id_suffix; ?>" placeholder="<?php echo $label_headline; ?>" type="text" size="18"/>
                        <i class="dashicons dashicons-search"></i>
                    </div>

                    <?php if (('user' == $agent_type)) : ?>
                        <br/>
                        <?php
                        $title = (!defined('PP_USER_SEARCH_META_FIELDS') && $pp->isUserAdministrator()
                            && $pp->getOption('advanced_options') && $pp->getOption('display_hints'))
                            ? __('For additional fields, define constant PP_USER_SEARCH_META_FIELDS', 'press-permit-core') : '';

                        $fields = ['first_name' => __('First Name', 'press-permit-core'), 'last_name' => __('Last Name', 'press-permit-core'), 'nickname' => __('Nickname', 'press-permit-core')];

                        if (defined('PP_USER_SEARCH_META_FIELDS')) {
                            $custom_fields = str_replace(' ', '', PP_USER_SEARCH_META_FIELDS);
                            $custom_fields = explode(',', $custom_fields);

                            foreach ($custom_fields as $cfield) {
                                $fields[$cfield] = __(ucwords(str_replace('_', ' ', $cfield)), 'press-permit-core');
                            }
                        }

                        if (isset($fields[$default_search_field])) {
                            unset($fields[$default_search_field]);
                        }

                        $ilim = (defined('PP_USER_SEARCH_META_FIELDS')) ? 6 : 3;

                        for ($i = 0; $i < $ilim; $i++) :
                            ?>
                            <div class="pp-user-meta-search" <?php
                            if ($i > 0 && empty($_GET["pp_search_user_meta_key_{$i}_{$id_suffix}"])) {
                                echo ' style="display:none;"';
                            }
                            ?>>
                                <select id="pp_search_user_meta_key_<?php echo $i; ?>_<?php echo $id_suffix; ?>" autocomplete="off">
                                    <option value=""><?php _e('(user field)', 'press-permit-core'); ?></option>

                                    <?php foreach ($fields as $field => $lbl) : ?>
                                        <option value="<?php echo $field; ?>"><?php echo $lbl; ?></option>
                                    <?php endforeach; ?>
                                </select>

                                <input id="pp_search_user_meta_val_<?php echo $i; ?>_<?php echo $id_suffix; ?>" class="pp-user-meta-field" 
                                       type="text" <?php
                                if (empty($_GET["pp_search_user_meta_key_{$i}_{$id_suffix}"])) {
                                    echo 'style="display:none"';
                                }
                                ?> title="<?php echo $title; ?>" size="8"/>

                                <?php if ($i < $ilim - 1) : ?>
                                    &nbsp;<span class="pp-usermeta-field-more" <?php
                                    if (empty($_GET["pp_search_user_meta_key_{$i}_{$id_suffix}"])) {
                                        echo 'style="display:none"';
                                    }
                                    ?>>+</span>

                                <?php endif; ?>

                            </div>
                        <?php endfor; ?>

                    <?php endif; ?>

                    <?php if (('user' == $agent_type) && $pp->getOption('user_search_by_role')) : ?>
                        <select id="pp_search_role_<?php echo $id_suffix; ?>" class="pp-search-role" autocomplete="off">
                            <option value=""><?php _e('(any WP role)', 'press-permit-core'); ?></option>
                            <?php wp_dropdown_roles(); ?>
                        </select>
                    <?php endif; ?>
                </td>

                <?php if ($display_stored_selections) : ?>
                    <td style="vertical-align:top" class="pp-members-current">
                    </td>
                <?php endif; ?>

            </tr>

            <tr>
                <td>
                    <h4>
                        <?php _e('Search Results:', 'press-permit-core'); ?>
                        <img class="waiting" style="display:none;float:right"
                             src="<?php echo esc_url(admin_url('images/wpspin_light.gif')) ?>" alt=""/>
                    </h4>

                    <select id="agent_results_<?php echo $id_suffix; ?>" class="pp_agent_results" <?php
                    if ($multi_select) : ?>multiple="multiple" style="height:160px;<?php else : ?>style="
                            display:none;<?php endif; ?>" autocomplete="off">
                    </select>

                    <span id="agent_msg_<?php echo $id_suffix; ?>"></span>
                </td>

                <?php
                if ($display_stored_selections) : ?>
                    <?php if ($width_current) {
                        $width = "width:{$width_current}px;";
                    }

                    ?>
                    <td class="pp-members-current">
                        <h4><?php echo $label_selections; ?></h4>

                        <select id='<?php echo $id_suffix; ?>' name='<?php echo $id_suffix; ?>[]' multiple='multiple'
                                style='height:160px;<?php echo $width; ?>'>

                            <?php
                            if ('user' == $agent_type) {
                                $display_property = (defined('PP_USER_RESULTS_DISPLAY_NAME')) ? 'display_name' : 'user_login';
                            } else {
                                $display_property = 'display_name';
                            }

                            foreach ($current_selections as $agent) : ?>
                                <?php
                                $attribs = (isset($agent->display_name) && ($agent->user_login != $agent->display_name))
                                    ? 'title="' . esc_attr($agent->display_name) . '"'
                                    : '';

                                $data = apply_filters(
                                    'presspermit_agents_selection_ui_attribs',
                                    ['attribs' => $attribs, 'user_caption' => $agent->$display_property],
                                    $agent_type,
                                    $id_suffix,
                                    $agent
                                );
                                ?>

                                <option value="<?php echo $agent->ID; ?>" <?php echo $data['attribs']; ?>><?php echo $data['user_caption']; ?></option>
                            <?php endforeach; ?>

                        </select><br/>
                    </td>

                <?php endif; ?>
            </tr>

            <?php do_action('_presspermit_agents_selection_ui_select_pre', $id_suffix); ?>
            <tr>
                <?php do_action('presspermit_agents_selection_ui_select_pre', $id_suffix); ?>

                <td>
                    <button type="button" id="select_agents_<?php echo $id_suffix; ?>" class="pp_add button pp-default-button"
                            style="<?php if (!$multi_select) : ?>;display:none;<?php endif; ?>">

                        <?php echo $label_select; ?>
                    </button>
                </td>

                <?php if ($display_stored_selections) : ?>
                    <td class="pp-members-current">
                        <button type="button" id="unselect_agents_<?php echo $id_suffix; ?>" class="pp_remove button pp-default-button"
                            style="margin-left: 24px;">
                            <?php echo $label_unselect; ?></button>
                    </td>
                <?php endif; ?>

            </tr>
        </table>
        <?php
        $csv = ($current_selections) ? implode(',', array_keys($current_selections)) : '';
        $csv = apply_filters('presspermit_agents_selection_ui_csv', $csv, $id_suffix, $current_selections);
        ?>
        <input type="hidden" id="<?php echo $id_suffix; ?>_csv" name="<?php echo $id_suffix; ?>_csv"
               value="<?php echo $csv; ?>"/>
        <?php
    } // end function ajax_selection_ui

    private function registerAjaxScripts($agent_type, $id_sfx, $context = '', $agent_id = 0, $args = [])
    {
        global $wp_scripts;

        // note: this is also done in AdminFiltersItemUI() constructor
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
        wp_enqueue_script('presspermit-listbox', PRESSPERMIT_URLPATH . "/common/js/listbox{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_VERSION, true);
        $wp_scripts->in_footer[] = 'presspermit-listbox'; // otherwise it will not be printed in footer

        if (!empty($args['create_dropdowns'])) {
            wp_localize_script('presspermit-listbox', 'ppListbox', ['omit_admins' => '1', 'metagroups' => 1]);

            wp_enqueue_script('presspermit-agent-select', PRESSPERMIT_URLPATH . "/common/js/agent-exception-select{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_VERSION, true);

            $arr = array_merge($args, ['agent_type' => $agent_type, 'ajaxurl' => admin_url('')]);
            wp_localize_script('presspermit-agent-select', 'ppException', $arr);
        } else {
            wp_localize_script('presspermit-listbox', 'ppListbox', ['omit_admins' => '0', 'metagroups' => 0]);

            if (!apply_filters('presspermit_override_agent_select_js', false)) {
                wp_enqueue_script('presspermit-agent-select', PRESSPERMIT_URLPATH . "/common/js/agent-select{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_VERSION, true);
            }
        }

        $wp_scripts->in_footer[] = 'presspermit-agent-select'; // otherwise it will not be printed in footer, as of WP 3.2.1

        $ajaxhandler = (!empty($args['create_dropdowns'])) ? 'got_ajax_dropdowns' : 'got_ajax_listbox';
        wp_localize_script('presspermit-agent-select', 'PPAgentSelect', ['adminurl' => admin_url(''), 'ajaxhandler' => $ajaxhandler]);

        if (!$this->agents_js_queue) {
            $this->agents_js_queue = [];
            add_action('admin_print_footer_scripts', [$this, 'actAjaxSelectionScripts'], 30);
        }

        $suppress_selection_js = !empty($args['suppress_selection_js']);
        $this->agents_js_queue[] = compact('agent_type', 'id_sfx', 'context', 'agent_id', 'suppress_selection_js');
    }

    public function actAjaxSelectionScripts()
    {
        // $args: agent_id supports contextual filtering of search results (e.g. group search omits groups which specified user is already a member of)

        // todo: clean up js loading logic
        if ($this->agents_js_queue) {
            $author_selection_only = false;

            if (!apply_filters('presspermit_override_agent_select_js', false) && !wp_script_is('pp_agent_select', 'done')) {
                global $wp_scripts;
                $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
                wp_enqueue_script('presspermit-agent-select', PRESSPERMIT_URLPATH . "/common/js/agent-select{$suffix}.js", ['jquery', 'jquery-form'], PRESSPERMIT_VERSION, true);
                $wp_scripts->do_item('presspermit-agent-select');
                $author_selection_only = true;
            }

            ?>
            <script type="text/javascript">
                /* <![CDATA[ */
                <?php foreach ($this->agents_js_queue as $args) : ?>
                presspermitLoadAgentsJS('<?php echo($args['id_sfx']); ?>', '<?php echo($args['agent_type']); ?>', '<?php echo($args['context']); ?>', '<?php echo($args['agent_id']); ?>', '<?php echo($args['suppress_selection_js']); ?>', <?php echo ($author_selection_only) ? 'true' : 'false'; ?>);
                <?php endforeach; ?>
                /* ]]> */
            </script>
            <?php
        }
    }
}
