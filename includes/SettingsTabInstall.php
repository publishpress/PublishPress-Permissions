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
            'modules' => __('Pro Features', 'press-permit-core'),
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

        $section = 'key'; // --- UPDATE KEY SECTION ---
        if (!empty($ui->form_options[$tab][$section]) && !$suppress_updates) : ?>
            <tr>
                <td scope="row" colspan="2">
                    <span style="font-weight:bold;vertical-align:top"><?php echo $ui->section_captions[$tab][$section]; ?></span>

                    <?php
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

                    $descript = sprintf(
                        __('This is the free edition of PressPermit. For more features and priority support, upgrade to <a href="%s" target="_blank">PressPermit Pro</a>.', 'press-permit-core'),
                        'https://publishpress.com/presspermit/'
                    );
                    ?>

                    <div id="presspermit-pro-descript" class="activating"><?php echo $descript; ?></div>

                    <?php
                    $downgrade_note = (is_array($opt_val) && count($opt_val) > 1) || get_option('ppce_version') || get_option('pps_version') || get_option('ppp_version');

                    if ($msg || $downgrade_note || $key_string) :?>
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
                        <li>
                        <?php
                        printf(
                            __('To temporarily restore Pro features before migrating to a publishpress.com account, delete this version and install <span style="white-space:nowrap"><a href="%s" target="_blank">Press Permit Core 2.6.x</a></span> using Plugins > Add New > Upload.', 'press-permit-core'),
                            'https://downloads.wordpress.org/plugin/press-permit-core.' . self::LEGACY_VERSION . '.zip'
                        );
                        ?>
                        </li>
                        <?php endif;?>

                    </ul>
                    <?php endif;?>
                </td>
            </tr>
            <?php
        endif; // any options accessable in this section

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
                        <?php printf(__('PressPermit Version: %1$s %2$s', 'press-permit-core'), PRESSPERMIT_VERSION, $info_link); ?>
                        <br/>
                        <span style="display:none"><?php printf(__("Database Schema Version: %s", 'press-permit-core'), PRESSPERMIT_DB_VERSION); ?><br/></span>

                        <span class="publishpress">
                        <?php

                        printf(
                            __('- part of the %1$sPublishPress%2$s family of professional publishing tools', 'press-permit-core'),
                            '<a href="https://publishpress.com/" target="_blank">',
                            '</a>'
                        );
                        ?>
                    </span>
                    </p>

                    <br/>
                    <?php

                    global $wp_version;
                    printf(__("WordPress Version: %s", 'press-permit-core'), $wp_version);
                    ?>
                    <br/>
                    <?php printf(__("PHP Version: %s", 'press-permit-core'), phpversion()); ?>
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
                    if (!presspermit()->isPro()) : ?>
                        <div>
                            <strong><?php _e('PressPermit Pro modules include:', 'press-permit-core'); ?></strong></div>
                        <ul class="pp-bullet-list">
                            <li><?php printf(__('%1$sContent-specific editing permissions, with PublishPress and Revisionary support%2$s', 'press-permit-core'), '<a href="https://publishpress.com/presspermit/?pp_module=collaboration" target="_blank">', '</a>'); ?></li>
                            <li><?php printf(__('%1$sCustom Post Statuses (for visibility or workflow moderation)%2$s', 'press-permit-core'), '<a href="https://publishpress.com/presspermit/?pp_module=status_control" target="_blank">', '</a>'); ?></li>
                            <li><?php printf(__('%1$sCustomize bbPress forum access%2$s', 'press-permit-core'), '<a href="https://publishpress.com/presspermit/?pp_module=compatibility" target="_blank">', '</a>'); ?></li>
                            <li><?php printf(__('%1$sFile Access control%2$s', 'press-permit-core'), '<a href="https://publishpress.com/presspermit/?pp_module=file-access" target="_blank">', '</a>'); ?></li>
                            <li><?php printf(__('%1$sRole Scoper import script%2$s', 'press-permit-core'), '<a href="https://publishpress.com/presspermit/?pp_module=import" target="_blank">', '</a>'); ?></li>
                            <li><?php printf(__('%1$s...and more%2$s', 'press-permit-core'), '<a href="https://publishpress.com/presspermit/" target="_blank">', '</a>'); ?></li>
                        </ul>

                        <?php
                        if (!$activated || $expired) {
                            require_once(PRESSPERMIT_CLASSPATH . '/UI/HintsPro.php');
                            HintsPro::proPromo();
                        }
                        ?>
                    <?php
                    endif;

                    ?>
                </td>
            </tr>
        <?php
        endif; // any options accessable in this section

        $section = 'help'; // --- HELP SECTION ---
        if (!empty($ui->form_options[$tab][$section])) : ?>
            <tr>
                <th scope="row"><?php echo $ui->section_captions[$tab][$section]; ?></th>
                <td>
                    <?php

                    if (presspermit()->isPro()) {
                        ?>
                        <ul class="pp-support-list">
                            <li><a href='https://publishpress.com/presspermit/'
                                   target='pp_doc'><?php _e('PressPermit Documentation', 'press-permit-core'); ?></a></li>

                            <li class="pp-support-forum">
                                <a href="admin.php?page=presspermit-settings&amp;pp_help_ticket=1"
                                   target="pp_help_ticket">
                                    <?php _e('Submit a Help Ticket', 'press-permit-core'); ?>
                                </a> <strong>*</strong>
                            </li>

                            <li class="upload-config">
                                <a href="admin.php?page=presspermit-settings&amp;pp_upload_config=1">
                                    <?php _e('Upload site configuration to presspermit.com now', 'press-permit-core'); ?>
                                </a> <strong>*</strong>
                                <img id="pp_upload_waiting" class="waiting" style="display:none;position:relative"
                                     src="<?php echo esc_url(admin_url('images/wpspin_light.gif')) ?>" alt=""/>
                            </li>
                        </ul>

                        <div id="pp_config_upload_caption"><strong>
                                <?php printf(__('%s Site configuration data selected below will be uploaded to presspermit.com:', 'press-permit-core'), '<strong>* </strong>'); ?>
                            </strong></div>

                        <div id="pp_config_upload_wrap">
                            <?php
                            $ok = (array)$pp->getOption('support_data');
                            $ok['ver'] = 1;

                            $ui->all_options[] = 'support_data';

                            $avail = [
                                'ver' => __('Version info for server, WP, PressPermit and various other plugins', 'press-permit-core'),
                                'pp_options' => __('PressPermit Settings and related WP Settings', 'press-permit-core'),
                                'theme' => __('Theme name, version and status', 'press-permit-core'),
                                'active_plugins' => __('Activated plugins list', 'press-permit-core'),
                                'installed_plugins' => __('Inactive plugins list', 'press-permit-core'),
                                'wp_roles_types' => __('WordPress Roles, Capabilities, Post Types, Taxonomies and Post Statuses', 'press-permit-core'),
                                'pp_permissions' => __('Role Assignments and Exceptions', 'press-permit-core'),
                                'pp_groups' => __('Group definitions', 'press-permit-core'),
                                'pp_group_members' => __('Group Membership (id only)', 'press-permit-core'),
                                'pp_imports' => __('Role Scoper / Press Permit 1.x Configuration and Import Results', 'press-permit-core'),
                                'post_data' => __('Post id, status, author id, parent id, and term ids and taxonomy name (when support accessed from post or term edit form)', 'press-permit-core'),
                                'error_log' => __('PHP Error Log (recent entries, no absolute paths)', 'press-permit-core'),
                            ];

                            $ok['ver'] = true;
                            $ok['pp_options'] = true;
                            $ok['error_log'] = true;

                            ?>
                            <div class="support_data">
                                <?php
                                foreach ($avail as $key => $caption) :
                                    $id = 'support_data_' . $key;
                                    $disabled = (in_array($key, ['ver', 'pp_options', 'error_log'], true)) ? 'disabled="disabled"' : '';
                                    ?>
                                    <div>
                                        <label for="<?php echo $id; ?>">
                                            <input type="checkbox" id="<?php echo $id; ?>"
                                                   name="support_data[<?php echo $key; ?>]"
                                                   value="1" <?php echo $disabled;
                                            checked('1', !empty($ok[$key]), true); ?> />
                                            <?php echo $caption; ?>
                                        </label>
                                    </div>
                                <?php
                                endforeach;
                                ?>
                            </div>

                            <div>
                                <label for="pp_support_data_all"><input type="checkbox" id="pp_support_data_all"
                                                                        value="1"/> <?php _e('(all)', 'press-permit-core'); ?></label>
                            </div>

                            <div id="pp_config_upload_subtext">
                                <?php _e('<strong>note:</strong> user data, absolute paths, database prefix, post title, post content and post excerpt are <strong>never</strong> uploaded', 'press-permit-core'); ?>
                            </div>

                        </div>
                        <?php
                    } else {
                        ?>
                        <div>
                            <?php _e('Upgrade to PressPermit Pro for access to the following resources:', 'press-permit-core'); ?>
                        </div>

                        <ul class="pp-support-list pp-bullet-list">
                            <?php
                            $key_arg = ($key_string) ? "?presspermit_account=$key_string" : '';
                            ?>
                            <li><?php printf(__('Priority support through our <a href="%s" target="_blank">help ticket</a> system', 'press-permit-core'), "https://publishpress.com/contact/$key_arg"); ?></a></li>
                            <li><?php _e('Optional uploading of your site configuration to assist troubleshooting', 'press-permit-core'); ?></li>
                        </ul>
                        <?php
                    }
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

    private function pluginUpdateUrl($plugin_file, $action = 'upgrade-plugin')
    {
        $_url = "update.php?action=$action&amp;plugin=$plugin_file";
        $url = ($this->useNetworkUpdates()) ? network_admin_url($_url) : admin_url($_url);
        $url = wp_nonce_url($url, "{$action}_$plugin_file");
        return $url;
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
