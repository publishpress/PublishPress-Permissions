<?php

namespace PublishPress\Permissions\UI;

use PublishPress\Permissions\Factory;

class SettingsTabInstall
{
    const LEGACY_VERSION = '2.6.3';

    public function __construct()
    {
        add_filter('presspermit_option_tabs', [$this, 'optionTabs'], 0);
        add_filter('presspermit_section_captions', [$this, 'sectionCaptions']);
        add_filter('presspermit_option_captions', [$this, 'optionCaptions']);
        add_filter('presspermit_option_sections', [$this, 'optionSections']);

        add_action('presspermit_install_options_pre_ui', [$this, 'optionsPreUI']);
        add_action('presspermit_install_options_ui', [$this, 'optionsUI']);

        add_filter("presspermit_unavailable_modules", 
            function($modules){
                return array_merge(
                    $modules, 
                    [
                        'presspermit-circles', 
                        'presspermit-compatibility', 
                        'presspermit-file-access', 
                        'presspermit-membership', 
                        'presspermit-sync', 
                        'presspermit-status-control', 
                        'presspermit-teaser'
                    ]
                );
            }
        );
    }

    public function optionTabs($tabs)
    {
        $tabs['install'] = __('Installation', 'press-permit-core');
        return $tabs;
    }

    public function sectionCaptions($sections)
    {
        $new = [
            'key' => __('Account', 'press-permit-core'),
            'version' => __('Version', 'press-permit-core'),
            'modules' => __('Modules', 'press-permit-core'),
            'help' => PWP::__wp('Help'),
        ];

        $key = 'install';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionCaptions($captions)
    {
        $opt = [
            'key' => __('settings', 'press-permit-core'),
            'help' => __('settings', 'press-permit-core'),
        ];

        return array_merge($captions, $opt);
    }

    public function optionSections($sections)
    {
        $new = [
            'key' => ['edd_key'],
            'help' => ['no_option'],
            'modules' => ['no_option'],
        ];

        $key = 'install';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionsPreUI()
    {
        if (isset($_REQUEST['pp_config_uploaded']) && empty($_POST)) : ?>
            <div id="message" class="updated">
                <p>
                    <strong><?php _e('Configuration data was uploaded.', 'press-permit-core'); ?>&nbsp;</strong>
                </p>
            </div>
        <?php elseif (isset($_REQUEST['pp_config_no_change']) && empty($_POST)) : ?>
            <div id="message" class="updated error">
                <p>
                    <strong><?php _e('Configuration data is unchanged since last upload.', 'press-permit-core'); ?>&nbsp;</strong>
                </p>
            </div>
        <?php elseif (isset($_REQUEST['pp_config_failed']) && empty($_POST)) : ?>
            <div id="message" class="error">
                <p>
                    <strong><?php _e('Configuration data could not be uploaded.', 'press-permit-core'); ?>&nbsp;</strong>
                </p>
            </div>
        <?php endif;

        if (isset($_REQUEST['pp_refresh_done']) && empty($_POST)) : ?>
            <div id="message" class="updated">
                <p>
                    <strong><?php _e('Version info was refreshed.', 'press-permit-core'); ?>&nbsp;</strong>
                </p>
            </div>
        <?php endif;
    }

    public function optionsUI()
    {
        $pp = presspermit();

        $ui = SettingsAdmin::instance();
        $tab = 'install';

        $use_network_admin = $this->useNetworkUpdates();
        $suppress_updates = $use_network_admin && !is_super_admin();

        global $activated;

        $opt_val = is_multisite() ? get_site_option('pp_support_key') : get_option('pp_support_key');

        if (!is_array($opt_val) || count($opt_val) < 2) {
            $activated = false;
            $expired = false;
            $key = '';
        } else {
            $activated = (1 == $opt_val[0]);
            $expired = (-1 == $opt_val[0]);
            $key = $opt_val[1];
        }

        if (isset($opt_val['expire_date_gmt'])) {
            $expire_days = intval((strtotime($opt_val['expire_date_gmt']) - time()) / 86400);
            if ($expire_days < 0) {
                $expired = true;
            }
        }

        $msg = '';
        $url = 'https://publishpress.com/pricing/';

        $key_string = (is_array($opt_val) && count($opt_val) > 1) ? $opt_val[1] : ''; 
        $expire_date = (is_array($opt_val) && isset($opt_val['expire_date_gmt'])) ? $opt_val['expire_date_gmt'] : ''; 

        if ($expired) {
            if ($expire_days < 365) {
                $msg = sprintf( 
                    __('Your presspermit.com key has expired, but a <a href="%s">PublishPress renewal</a> discount may be available.', 'press-permit-core'),
                    'admin.php?page=presspermit-settings&amp;pp_renewal=1'
                );
            }
        } elseif ($activated) {
            $url = "https://publishpress.com/contact/?pp_topic=presspermit-migration&presspermit_account=$key_string";
            
            $msg = sprintf(
                __('A presspermit.com key appears to be active. <a href="%s" target="_blank">Contact us</a> for assistance in migrating your account to publishpress.com.', 'press-permit-core'),
                $url
            );
        }

        $downgrade_note = (is_array($opt_val) && count($opt_val) > 1) || get_option('ppce_version') || get_option('pps_version') || get_option('ppp_version');

        if ($msg || $downgrade_note || $key_string) :
            $section = 'key'; // --- UPDATE KEY SECTION ---
            if (!empty($ui->form_options[$tab][$section]) && !$suppress_updates) : ?>
                <tr>
                    <th scope="row">
                        <span style="font-weight:bold;vertical-align:top"><?php echo $ui->section_captions[$tab][$section]; ?></span>
                    </th>

                    <td>
                        <h4><?php _e('Further details for your installation:', 'press-permit-core');?></h4>
                        <ul id="presspermit-pro-install-details" class="pp-bullet-list">

	                        <?php if ($msg):?>
	                        <li>
	                        <?php echo $msg ?>
	                        </li>
	                        <?php endif;?>
	
	                        <?php if ($key_string):?>
	                        <li>
	                        <?php 
	                        if ($expire_date)
	                            printf(__("Original presspermit.com support key hash: <strong>%s</strong> (expires %s)"), $key_string, $expire_date);
	                        else
	                            printf(__("Original presspermit.com support key hash: <strong>%s</strong>"), $key_string, $expire_date);
	                        ?>
	                        </li>
	                        <?php endif;?>
	
	                        <?php if ($downgrade_note):?>
	                        <li class='pp-pro-extensions-migration-note'>
	                        <?php
	                        printf(
	                            __('To temporarily restore Pro features before migrating to a publishpress.com account, delete this version and install <span style="white-space:nowrap"><a href="%s" target="_blank">Press Permit Core 2.6.x</a></span> using Plugins > Add New > Upload.', 'press-permit-core'),
	                            'https://downloads.wordpress.org/plugin/press-permit-core.' . self::LEGACY_VERSION . '.zip'
	                        );
	                        ?>
	                        </li>
	                        <?php endif;?>
	                    </ul>
                	</td>
            	</tr>
            	<?php
        	endif; // any options accessable in this section
        endif; // any status messages to display

        $section = 'version'; // --- VERSION SECTION ---

            ?>
            <tr>
                <th scope="row"><?php echo $ui->section_captions[$tab][$section]; ?></th>
                <td>

                    <?php
                    $update_info = [];

                    $info_link = '';

                    if (!$suppress_updates) {
                        $wp_plugin_updates = get_site_transient('update_plugins');
                        if (
                            $wp_plugin_updates && isset($wp_plugin_updates->response[plugin_basename(PRESSPERMIT_FILE)])
                            && !empty($wp_plugin_updates->response[plugin_basename(PRESSPERMIT_FILE)]->new_version)
                            && version_compare($wp_plugin_updates->response[plugin_basename(PRESSPERMIT_FILE)]->new_version, PRESSPERMIT_VERSION, '>')
                        ) {
                            $slug = 'press-permit-core';

                            $_url = "plugin-install.php?tab=plugin-information&plugin=$slug&section=changelog&TB_iframe=true&width=600&height=800";
                            $info_url = ($use_network_admin) ? network_admin_url($_url) : admin_url($_url);

                            $info_link = "<span class='update-message'> &bull; <a href='$info_url' class='thickbox'>"
                                . sprintf(__('%s&nbsp;details', 'press-permit-core'), $wp_plugin_updates->response[plugin_basename(PRESSPERMIT_FILE)]->new_version)
                                . '</a></span>';
                        }
                    }

                    ?>
                    <p>
                        <?php printf(__('PublishPress Permissions Version: %1$s %2$s', 'press-permit-core'), PRESSPERMIT_VERSION, $info_link); ?>
                        <br/>
                        <span style="display:none"><?php printf(__("Database Schema Version: %s", 'press-permit-core'), PRESSPERMIT_DB_VERSION); ?><br/></span>
                    </p>

                    <p>
                    <?php

                    global $wp_version;
                    printf(__("WordPress Version: %s", 'press-permit-core'), $wp_version);
                    ?>
                    </p>
                    <p>
                    <?php printf(__("PHP Version: %s", 'press-permit-core'), phpversion()); ?>
                    </p>
                </td>
            </tr>
        <?php

        $section = 'modules'; // --- EXTENSIONS SECTION ---
        if (!empty($ui->form_options[$tab][$section])) : ?>
            <tr>
                <th scope="row">
                    <?php

                    echo $ui->section_captions[$tab][$section];
                    ?>
                </th>
                <td>
                    <?php
                    $inactive = [];

                    $ext_info = $pp->admin()->getModuleInfo();
                    
                    $pp_modules = presspermit()->getActiveModules();
                    $active_module_plugin_slugs = [];

                    if ($pp_modules) : ?>
                        <?php

                        $change_log_caption = __('<strong>Change Log</strong> (since your current version)', 'press-permit-core');

                        ?>
                        <h4 style="margin-top:0"><?php _e('Active Modules:', 'press-permit-core'); ?></h4>
                        <table class="pp-extensions">
                            <?php foreach ($pp_modules as $slug => $plugin_info) :
                                $info_link = '';
                                $update_link = '';
                                $alert = '';
                                ?>
                                <tr>
                                    <td <?php if ($alert) {
                                        echo 'colspan="2"';
                        }
                                    ?>>
                                        <?php $id = "module_active_{$slug}";?>

                                        <label for="<?php echo $id; ?>">
                                            <input type="checkbox" id="<?php echo $id; ?>"
                                                name="presspermit_active_modules[<?php echo $plugin_info->plugin_slug;?>]"
                                                value="1" checked="checked" />

                                            <?php echo __($plugin_info->label);?>
                                        </label>

                                        <?php
                                            echo ' <span class="pp-gray">'
                                                . "</span> $info_link $update_link $alert"
                        ?>
                                    </td>

                                    <?php if (!empty($ext_info) && !$alert) : ?>
                                        <td>
                                            <?php if (isset($ext_info->blurb[$slug])) : ?>
                                                <span class="pp-ext-info"
                                                    title="<?php if (isset($ext_info->descript[$slug])) {
                                                        echo esc_attr($ext_info->descript[$slug]);
                                                    }
                                                    ?>">
                                                <?php echo $ext_info->blurb[$slug]; ?>
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

                    echo "<input type='hidden' name='presspermit_reviewed_modules' value='" . implode(',', $active_module_plugin_slugs) . "' />";

                    $inactive = $pp->getDeactivatedModules();

                    ksort($inactive);
                    if ($inactive) : ?>

                        <h4 style="margin-top:5px">
                            <?php
                            _e('Inactive Modules:', 'press-permit-core')
                    ?>
                        </h4>

                        <table class="pp-extensions">
                            <?php foreach ($inactive as $plugin_slug => $module_info) :
                                $slug = str_replace('presspermit-', '', $plugin_slug);
                                ?>
            <tr>
                <td>

                                    <?php $id = "module_deactivated_{$slug}";?>

                                        <label for="<?php echo $id; ?>">
                                            <input type="checkbox" id="<?php echo $id; ?>"
                                                name="presspermit_deactivated_modules[<?php echo $plugin_slug;?>]"
                                                value="1" />

                                        <?php echo (!empty($ext_info->title[$slug])) ? $ext_info->title[$slug] : $this->prettySlug($slug);?></td>
                                        </label>

                                    <?php if (!empty($ext_info)) : ?>
                                        <td>
                                            <?php if (isset($ext_info->blurb[$slug])) : ?>
                                                <span class="pp-ext-info"
                                                    title="<?php if (isset($ext_info->descript[$slug])) {
                                                        echo esc_attr($ext_info->descript[$slug]);
                                                    }
                                                    ?>">
                                                <?php echo $ext_info->blurb[$slug]; ?>
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                                <?php
                    endif;

                    $pro_modules = array_diff(presspermit()->getAvailableModules(), $active_module_plugin_slugs, array_keys($inactive));

                    sort($pro_modules);
                    if ($pro_modules) :
                                ?>
                        <h4><?php _e('Pro Modules:', 'press-permit-core'); ?></h4>
                        <table class="pp-extensions">
                            <?php foreach ($pro_modules as $plugin_slug) :
                                $slug = str_replace('presspermit-', '', $plugin_slug);
                                ?>
                                <tr>
                                    <td>

                                    <?php $id = "module_deactivated_{$slug}";?>

                                    <label for="<?php echo $id; ?>">
                                        <input type="checkbox" id="<?php echo $id; ?>" disabled="disabled"
                                                name="presspermit_deactivated_modules[<?php echo $plugin_slug;?>]"
                                                value="1" />

                                        <?php echo $this->prettySlug($slug);?></td>
                                    </label>

                                    <?php if (!empty($ext_info)) : ?>
                                        <td>
                                            <?php if (isset($ext_info->blurb[$slug])) : ?>
                                                <span class="pp-ext-info"
                                                    title="<?php if (isset($ext_info->descript[$slug])) {
                                                        echo esc_attr($ext_info->descript[$slug]);
                                                    }
                                                    ?>">
                                                <?php echo $ext_info->blurb[$slug]; ?>
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                        <p style="padding-left:15px;">
                        <?php

                            ?>
                        </p>
                        <?php
                    endif;
                    ?>
                </td>
            </tr>
        <?php

        endif; // any options accessable in this section
    } // end function optionsUI()

    private function useNetworkUpdates()
    {
        return (is_multisite() && (is_network_admin() || PWP::isNetworkActivated() || PWP::isMuPlugin()));
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
