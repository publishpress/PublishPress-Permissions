<?php

namespace PublishPress\Permissions\UI;

use PublishPress\Permissions\Factory;

class SettingsTabModules
{
    public function __construct()
    {
        add_filter('presspermit_option_tabs', [$this, 'optionTabs'], 0);
        add_filter('presspermit_section_captions', [$this, 'sectionCaptions']);
        add_filter('presspermit_option_captions', [$this, 'optionCaptions']);
        add_filter('presspermit_option_sections', [$this, 'optionSections']);

        add_action('presspermit_modules_options_ui', [$this, 'optionsUI']);
    }

    public function optionTabs($tabs)
    {
        $tabs['modules'] = esc_html__('Modules', 'press-permit-core');
        return $tabs;
    }

    public function sectionCaptions($sections)
    {
        $new = [
            'modules' => '',
            'help' => PWP::__wp('Help'),
        ];

        $key = 'modules';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionCaptions($captions)
    {
        $opt = [
            'help' => esc_html__('settings', 'press-permit-core'),
        ];

        return array_merge($captions, $opt);
    }

    public function optionSections($sections)
    {
        $new = [
            'help' => ['no_option'],
            'modules' => ['no_option'],
        ];

        $key = 'modules';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionsUI()
    {
        $pp = presspermit();

        $ui = SettingsAdmin::instance();
        $tab = 'modules';

        $section = 'modules'; // --- EXTENSIONS SECTION ---
        if (!empty($ui->form_options[$tab][$section])) : ?>
            <tr>
                <td>

                    <?php
                    $inactive = [];

                    $ext_info = $pp->admin()->getModuleInfo();
                    
                    $pp_modules = presspermit()->getActiveModules();
                    $active_module_plugin_slugs = [];

                    if ($pp_modules) : ?>
                        <?php

                        $change_log_caption = esc_html__('<strong>Change Log</strong> (since your current version)', 'press-permit-core');

                        ?>
                        <h4 style="margin:0 0 5px 0"><?php esc_html_e('Active Modules:', 'press-permit-core'); ?></h4>
                        <table class="pp-extensions">
                            <?php foreach ($pp_modules as $slug => $plugin_info) :
                                ?>
                                <tr>
                                    <td>
                                        <?php $id = "module_active_{$slug}";?>

                                        <label for="<?php echo esc_attr($id); ?>">
                                            <input type="checkbox" id="<?php echo esc_attr($id); ?>"
                                                name="presspermit_active_modules[<?php echo esc_attr($plugin_info->plugin_slug);?>]"
                                                value="1" checked="checked" />

                                            <?php echo esc_html__($plugin_info->label);?>
                                        </label>

                                        <?php
                                            echo ' <span class="pp-gray">'
                                                . "</span>"
                                        ?>
                                    </td>

                                    <?php if (!empty($ext_info)) : ?>
                                        <td>
                                            <?php if (isset($ext_info->blurb[$slug])) : ?>
                                                <span class="pp-ext-info"
                                                    title="<?php if (isset($ext_info->descript[$slug])) {
                                                        echo esc_attr($ext_info->descript[$slug]);
                                                    }
                                                    ?>">
                                                <?php echo esc_html($ext_info->blurb[$slug]); ?>
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php 
                                $active_module_plugin_slugs[]= $plugin_info->plugin_slug;
                            endforeach; ?>
                        </table>
                    <?php
                    endif;

                    $modules_csv = implode(',', $active_module_plugin_slugs);

                    echo "<input type='hidden' name='presspermit_reviewed_modules' value='" . esc_attr($modules_csv) . "' />";

                    $inactive = $pp->getDeactivatedModules();

                    ksort($inactive);
                    if ($inactive) : ?>

                        <h4 style="margin:20px 0 5px 0">
                            <?php
                            esc_html_e('Inactive Modules:', 'press-permit-core')
                            ?>
                        </h4>

                        <table class="pp-extensions">
                            <?php foreach ($inactive as $plugin_slug => $module_info) :
                                $slug = str_replace('presspermit-', '', $plugin_slug);
                                ?>
                                <tr>
                                    <td>
                                    
                                    <?php $id = "module_deactivated_{$slug}";?>

                                    <label for="<?php echo esc_attr($id); ?>">
                                        <input type="checkbox" id="<?php echo esc_attr($id); ?>"
                                                name="presspermit_deactivated_modules[<?php echo esc_attr($plugin_slug);?>]"
                                                value="1" />

                                        <?php if (!empty($ext_info->title[$slug])) echo esc_html($ext_info->title[$slug]); else echo esc_html($this->prettySlug($slug));?></td>
                                    </label>

                                    <?php if (!empty($ext_info)) : ?>
                                        <td>
                                            <?php if (isset($ext_info->blurb[$slug])) : ?>
                                                <span class="pp-ext-info"
                                                    title="<?php if (isset($ext_info->descript[$slug])) {
                                                        echo esc_attr($ext_info->descript[$slug]);
                                                    }
                                                    ?>">
                                                <?php echo esc_html($ext_info->blurb[$slug]); ?>
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php
                    endif;

                    do_action('presspermit_modules_ui', $active_module_plugin_slugs, $inactive);
                    ?>
                </td>
            </tr>
        <?php
        endif; // any options accessable in this section
    }

    private function prettySlug($slug)
    {
        $slug = str_replace('presspermit-', '', $slug);
        $slug = str_replace('Pp', 'PP', ucwords(str_replace('-', ' ', $slug)));
        $slug = str_replace('press', 'Press', $slug); // temp workaround
        $slug = str_replace('Wpml', 'WPML', $slug);
        return $slug;
    }
}
