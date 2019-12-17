<?php

namespace PublishPress\Permissions\UI;

class SettingsAdmin
{
    private static $instance;

    var $form_options;
    var $tab_captions;
    var $section_captions;
    var $option_captions;
    var $all_options;
    var $all_otype_options;
    var $display_hints;

    public static function instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new SettingsAdmin();
        }

        return self::$instance;
    }

    private function __construct()
    {
        
    }

    public function getOption($option_basename)
    {
        return presspermit()->getOption($option_basename);
    }

    public function getOptionArray($option_basename)
    {
        $val = presspermit()->getOption($option_basename);

        if (!$val || !is_array($val)) {
            $val = [];
        }

        return $val;
    }

    public function optionCheckbox($option_name, $tab_name, $section_name, $hint_text = '', $trailing_html = '', $args = [])
    {
        $return = ['in_scope' => false, 'no_storage' => false, 'disabled' => false, 'title' => '', 'style' => '', 'div_style' => ''];

        if (in_array($option_name, $this->form_options[$tab_name][$section_name], true)) {
            if (empty($args['no_storage']))
                $this->all_options[] = $option_name;

            if (isset($args['val']))
                $return['val'] = $args['val'];
            else
                $return['val'] = (!empty($args['no_storage'])) ? 0 : presspermit()->getOption($option_name);

            $disabled_clause = (!empty($args['disabled']) || $this->hideNetworkOption($option_name)) ? "disabled='disabled'" : '';
            $style = (!empty($args['style'])) ? $args['style'] : '';
            $div_style = (!empty($args['div_style'])) ? $args['div_style'] : '';

            $title = (!empty($args['title'])) ? " title='" . esc_attr($args['title']) . "'" : '';

            echo "<div class='agp-opt-checkbox $option_name' $div_style>"
                . "<label for='$option_name'{$title}>"
                . "<input name='$option_name' type='checkbox' $disabled_clause $style id='$option_name' value='1' " . checked('1', $return['val'], false) . " /> "
                . $this->option_captions[$option_name]
                . "</label>";

            if ($hint_text && $this->display_hints) {
                echo "<div class='pp-subtext'>" . $hint_text . "</div>";
            }

            echo "</div>";

            if ($trailing_html)
                echo $trailing_html;

            $return['in_scope'] = true;
        }

        return $return;
    }

    private function hideNetworkOption($option_name)
    {
        if (is_multisite()) {
            return (in_array($option_name, presspermit()->netwide_options, true)
                && !is_network_admin() && (1 != get_current_blog_id()));
        } else
            return false;
    }

    public function filterNetworkOptions()
    {
        if (is_multisite() && !is_network_admin() && (1 != get_current_blog_id())) {
            $pp = presspermit();
            $this->all_options = array_diff($this->all_options, $pp->netwide_options);
            $this->all_otype_options = array_diff($this->all_otype_options, $pp->netwide_options);
        }
    }
}
