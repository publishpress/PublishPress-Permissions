<?php

namespace PublishPress\Permissions\UI;

class SettingsTabAdvanced
{
    private $enabled;

    public function __construct()
    {
        // if disabled, will show only available option will be "enable"
        $this->enabled = presspermit()->getOption('advanced_options');

        add_filter('presspermit_option_tabs', [$this, 'optionTabs'], 6);
        add_filter('presspermit_section_captions', [$this, 'sectionCaptions']);
        add_filter('presspermit_option_captions', [$this, 'optionCaptions']);
        add_filter('presspermit_option_sections', [$this, 'optionSections']);

        add_action('presspermit_advanced_options_pre_ui', [$this, 'optionsPreUI']);
        add_action('presspermit_advanced_options_ui', [$this, 'optionsUI']);
    }

    public function optionTabs($tabs)
    {
        $tabs['advanced'] = __('Advanced', 'press-permit-core');
        return $tabs;
    }

    public function sectionCaptions($sections)
    {
        $new = [
            'enable' => __('Enable Advanced', 'press-permit-core'),
            'file_filtering' => __('File Filtering', 'press-permit-core'),
            'network' => __('Network-Wide Settings', 'press-permit-core'),
        ];

        if ($this->enabled) {
            $new = array_merge($new, [
                'anonymous' => __('Content Filtering', 'press-permit-core'),
                'permissions_admin' => __('Permissions Admin', 'press-permit-core'),
                'user_permissions' => __('User Permissions', 'press-permit-core'),
                'capabilities' => __('Permissions Capabilities', 'press-permit-core'),
                'role_integration' => __('Role Integration', 'press-permit-core'),
                'constants' => __('Constants', 'press-permit-core'),
                'misc' => __('Miscellaneous', 'press-permit-core'),
            ]);
        }

        $key = 'advanced';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionCaptions($captions)
    {
        $opt = ['advanced_options' => __('Enable advanced settings', 'press-permit-core')];

        if ($this->enabled) {
            $opt = array_merge($opt, [
                'anonymous_unfiltered' => sprintf(__('%1$sDisable%2$s all filtering for anonymous users', 'press-permit-core'), '<strong>', '</strong>'),
                'suppress_administrator_metagroups' => sprintf(__('%1$sDo not apply%2$s metagroup permissions for Administrators', 'press-permit-core'), '<strong>', '</strong>'),
                'user_search_by_role' => __('User Search: Filter by WP role', 'press-permit-core'),
                'display_hints' => __('Display Administrative Hints', 'press-permit-core'),
                'display_extension_hints' => __('Display Module Hints', 'press-permit-core'),
                'dynamic_wp_roles' => __('Detect Dynamically Mapped WP Roles', 'press-permit-core'),
                'non_admins_set_read_exceptions' => __('Non-Administrators can set Reading Permissions for their editable posts', 'press-permit-core'),
                'users_bulk_groups' => __('Bulk Add / Remove Groups on Users Screen', 'press-permit-core'),
            ]);
        }

        return array_merge($captions, $opt);
    }

    public function optionSections($sections)
    {
        $new = ['enable' => ['advanced_options']];

        if ($this->enabled) {
            $new = array_merge($new, [
                'anonymous' => ['anonymous_unfiltered', 'suppress_administrator_metagroups'],
                'permissions_admin' => ['non_admins_set_read_exceptions'],
                'user_permissions' => ['user_permissions'],
                'role_integration' => ['dynamic_wp_roles'],
                'misc' => ['users_bulk_groups', 'user_search_by_role', 'display_hints', 'display_extension_hints'],
            ]);
        }

        $key = 'advanced';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionsPreUI()
    {
        if (SettingsAdmin::instance()->display_hints) {
            echo '<div class="pp-hint pp-optionhint">';

            if (presspermit()->getOption('advanced_options')) {
                if (presspermit()->moduleActive('collaboration')) {
                    echo SettingsAdmin::getStr('advanced_options_enabled');
                }
            } else {
                echo SettingsAdmin::getStr('advanced_options_disabled');
            }
            echo '</div>';
        }
    }

    public function optionsUI()
    {
        $pp = presspermit();

        $ui = SettingsAdmin::instance();
        $tab = 'advanced';

        $section = 'enable'; // --- ENABLE SECTION ---
        if (!empty($ui->form_options[$tab][$section])) : ?>
            <tr>
                <th scope="row"><?php echo $ui->section_captions[$tab][$section]; ?></th>
                <td>
                    <?php
                    $hint = '';
                    $ui->optionCheckbox('advanced_options', $tab, $section, $hint);
                    ?>
                </td>
            </tr>
        <?php endif; // any options accessable in this section

        $section = 'file_filtering';

        if ($this->enabled) {
            $section = 'anonymous'; // --- ANONYMOUS USERS SECTION ---
            if (!empty($ui->form_options[$tab][$section])) : ?>
                <tr>
                    <th scope="row"><?php echo $ui->section_captions[$tab][$section]; ?></th>
                    <td>
                        <?php
                        $ui->optionCheckbox('anonymous_unfiltered', $tab, $section, true);

                        $ui->optionCheckbox('suppress_administrator_metagroups', $tab, $section, true);

                        do_action('presspermit_options_ui_insertion', $tab, $section);
                        ?>
                    </td>
                </tr>
            <?php endif; // any options accessable in this section

            $section = 'custom_statuses'; // --- CUSTOM POST STATUSES SECTION ---
            if (!empty($ui->form_options[$tab][$section])) : ?>
                <tr>
                    <th scope="row"><?php echo $ui->section_captions[$tab][$section]; ?></th>
                    <td>
                        <?php
                        do_action('presspermit_options_ui_insertion', $tab, $section);
                        ?>
                    </td>
                </tr>
            <?php endif; // any options accessable in this section

            $section = 'permissions_admin'; // --- PERMISSIONS ADMIN SECTION ---
            if (!empty($ui->form_options[$tab][$section])) : ?>
                <tr>
                    <th scope="row"><?php echo $ui->section_captions[$tab][$section]; ?></th>
                    <td>
                        <?php
                        $ui->optionCheckbox('non_admins_set_read_exceptions', $tab, $section, true);

                        do_action('presspermit_options_ui_insertion', $tab, $section);
                        ?>
                    </td>
                </tr>
            <?php endif; // any options accessable in this section


            $section = 'user_permissions'; // --- PERMISSIONS ADMIN SECTION ---
            ?>
                <tr>
                    <th scope="row"><?php echo $ui->section_captions[$tab][$section]; ?></th>
                    <td>

                        <div class="pp-user-permissions-help">
                            <p>
                            <?php
                            $url = "users.php";
                            printf(
                                __('For user-specific Supplemental Roles and Permissions, click a "Roles" cell on the %1$sUsers%2$s screen.', 'press-permit-core'),
                                "<strong><a href='$url'>",
                                '</a></strong>'
                            );
                            ?>
                            </p>
                        </div>

                        <div class="pp-hint pp-user-permissions-help">
                            <p>
                            <?php
                            _e('To filter the Users list by Permissions, follow a link below:', 'press-permit-core');
                            ?>
                            </p>

                            <ul class="pp-notes">
                                <li><?php printf(__('%1$sUsers who have no custom Permission Group membership%2$s', 'press-permit-core'), "<a href='$url?pp_no_group=1'>", '</a>'); ?></li>
                            </ul>
                            <br/>
                            <ul class="pp-notes">
                                <li><?php printf(__('%1$sUsers who have Supplemental Roles assigned directly%2$s', 'press-permit-core'), "<a href='$url?pp_user_roles=1'>", '</a>'); ?></li>
                                <li><?php printf(__('%1$sUsers who have Specific Permissions assigned directly%2$s', 'press-permit-core'), "<a href='$url?pp_user_exceptions=1'>", '</a>'); ?></li>
                                <li><?php printf(__('%1$sUsers who have Supplemental Roles or Specific Permissions directly%2$s', 'press-permit-core'), "<a href='$url?pp_user_perms=1'>", '</a>'); ?></li>
                            </ul>
                            <br/>
                            <ul class="pp-notes">
                                <li><?php printf(__('%1$sUsers who have Supplemental Roles (directly or via group)%2$s', 'press-permit-core'), "<a href='$url?pp_has_roles=1'>", '</a>'); ?></li>
                                <li><?php printf(__('%1$sUsers who have Specific Permissions (directly or via group)%2$s', 'press-permit-core'), "<a href='$url?pp_has_exceptions=1'>", '</a>'); ?></li>
                                <li><?php printf(__('%1$sUsers who have Supplemental Roles or Specific Permissions (directly or via group)%2$s', 'press-permit-core'), "<a href='$url?pp_has_perms=1'>", '</a>'); ?></li>
                            </ul>
                        </div>

                        <?php if (presspermit()->getOption('display_hints')) : ?>
                            <span class="pp-subtext">
                                <?php
                                printf(
                                    __('%1$snote%2$s: If you don&apos;t see the Roles column on the Users screen, make sure it is enabled in Screen Options. ', 'press-permit-core'),
                                    '<strong>',
                                    '</strong>'
                                );
                                ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php


            $section = 'misc'; // --- MISC SECTION ---
            if (!empty($ui->form_options[$tab][$section])) : ?>
                <tr>
                    <th scope="row"><?php echo $ui->section_captions[$tab][$section]; ?></th>
                    <td>
                        <?php
                        $ui->optionCheckbox('users_bulk_groups', $tab, $section, '');

                        $ui->optionCheckbox('user_search_by_role', $tab, $section, true);

                        $ui->optionCheckbox('display_hints', $tab, $section, true);

                        if (presspermit()->isPro()) {
                            $ui->optionCheckbox('display_extension_hints', $tab, $section, true);
                        }
                        ?>
                    </td>
                </tr>
            <?php endif; // any options accessable in this section

            $section = 'role_integration'; // --- ROLE INTEGRATION SECTION ---
            if (!empty($ui->form_options[$tab][$section])) : ?>
                <tr>
                    <th scope="row"><?php echo $ui->section_captions[$tab][$section]; ?></th>
                    <td>
                        <div>
                        <?php printf(
                            __('To control the makeup of Supplemental Roles, see %1$sRole Usage%2$s.', 'press-permit-core'),
                            '<strong><a href="' . admin_url('admin.php?page=presspermit-role-usage') . '">',
                            '</a></strong>'
                        );
                        ?>
                        </div>
                        <br />

                        <div>
                        <?php
                        $args = (defined('PP_FORCE_DYNAMIC_ROLES')) ? ['val' => 1, 'no_storage' => true, 'disabled' => true] : [];
                        $ui->optionCheckbox('dynamic_wp_roles', $tab, $section, true, '', $args);
                        ?>
                        </div>
                    </td>
                </tr>
            <?php endif; // any options accessable in this section

            $section = 'capabilities'; // --- PP CAPABILITIES SECTION ---
            ?>
            <tr>
                <td scope="row" colspan="2"><span
                            style="font-weight:bold"><?php echo $ui->section_captions[$tab][$section]; ?></span>
                    <span class="pp-capabilities-caption">
                        <span class="pp-subtext pp-no-hide">
                            <?php
                            if (defined('PUBLISHPRESS_CAPS_VERSION')) {
                                $url = admin_url('admin.php?page=capsman');
                                printf(
                                    SettingsAdmin::getStr('pp_capabilities'),
                                    '<a href="' . $url . '">',
                                    '</a>'
                                );
                            } else {
                                printf(
                                    SettingsAdmin::getStr('pp_capabilities_install_prompt'),
                                    '<span class="plugins update-message"><a href="' . Settings::pluginInfoURL('capability-manager-enhanced')
                                    . '" class="thickbox" title=" PublishPress Capabilities">PublishPress&nbsp;Capabilities</a></span>'
                                );
                            }
                            ?>
                        </span>
                </span>

                <?php
                if ($pp->getOption('display_hints')) :
                ?>
                    <table id="pp_cap_descripts" class="pp_cap_descripts pp-hint">
                        <thead>
                        <tr>
                            <th class="cap-name"><?php _e('Capability Name', 'press-permit-core'); ?></th>
                            <th><?php echo __('Description', 'press-permit-core'); ?></th>
                        </tr>
                        </thead>
                        <tbody>

                        <?php
                        $pp_caps = apply_filters('presspermit_cap_descriptions', []);

                        foreach ($pp_caps as $cap_name => $descript) :
                            ?>
                            <tr>
                                <td class="cap-name"><?php echo $cap_name; ?></td>
                                <td><?php echo $descript; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                <?php endif;?>
                </td>
            </tr>
            <?php

            $section = 'constants'; // --- CONSTANTS SECTION ---

            // don't display the section unless constants are defined or WP_DEBUG set
            require_once(PRESSPERMIT_CLASSPATH . '/Constants.php');
            $ppc = new \PublishPress\Permissions\Constants();

            $defined_constant_types = [];

            foreach ($ppc->constants_by_type as $const_type => $constants) {
                foreach ($constants as $const_name) {
                    if (defined($const_name)) {
                        $defined_constant_types[$const_type] = true;
                        break;
                    }
                }
            }

            // Unless debugging, only list defined constants and available constants in the same section
            if ($defined_constant_types || (defined('PRESSPERMIT_DEBUG') && PRESSPERMIT_DEBUG)) :
                ?>
                <tr>
                    <td scope="row" colspan="2">
                        <span style="font-weight:bold"><?php echo $ui->section_captions[$tab][$section]; ?></span>

                        <table id="pp_defined_constants" class="pp_cap_descripts">
                            <thead>
                            <tr>
                                <th class="cap-name"><?php _e('Defined Constant', 'press-permit-core'); ?></th>
                                <th class="const-value"><?php echo __('Setting', 'press-permit-core'); ?></th>
                                <th><?php echo __('Description', 'press-permit-core'); ?></th>
                            </tr>
                            </thead>
                            <tbody>

                            <?php
                            foreach (array_keys($defined_constant_types) as $const_type) :
                                ?>
                                <tr class="const-section">
                                    <td>--- <?php echo $ppc->constant_types[$const_type]; ?> ---</td>
                                    <td></td>
                                    <td></td>
                                </tr>

                                <?php
                                foreach ($ppc->constants_by_type[$const_type] as $const_name) :
                                    if (
                                        !defined($const_name) || !isset($ppc->constants[$const_name])
                                        || !empty($ppc->constants[$const_name]->suppress_display)
                                    ) {
                                        continue;
                                    }
                                    ?>
                                    <tr>
                                        <td class="cap-name"><?php echo $const_name; ?></td>
                                        <td><?php echo strval(constant($const_name)); ?></td>
                                        <td><?php echo $ppc->constants[$const_name]->descript; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>

                        <br/>
                        <table id="pp_available_constants" class="pp_cap_descripts pp-hint">
                            <thead>
                            <tr>
                                <th class="cap-name"><?php _e('Available Constant', 'press-permit-core'); ?></th>
                                <th class="const-value"><?php echo __('Setting', 'press-permit-core'); ?></th>
                                <th><?php echo __('Description', 'press-permit-core'); ?></th>
                            </tr>
                            </thead>
                            <tbody>

                            <?php
                            foreach ($ppc->constants_by_type as $const_type => $constants) :
                                // Unless debugging, only list constants in sections which already have a constant defined
                                if (!isset($defined_constant_types[$const_type]) && (!defined('PRESSPERMIT_DEBUG') || !PRESSPERMIT_DEBUG)) {
                                    continue;
                                }
                                ?>
                                <?php if (isset($ppc->constant_types[$const_type])) : ?>
                                <tr class="const-section">
                                    <td>--- <?php echo $ppc->constant_types[$const_type]; ?> ---</td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            <?php endif; ?>

                                <?php
                                foreach ($constants as $const_name) :
                                    if (!isset($ppc->constants[$const_name]) || !empty($ppc->constants[$const_name]->suppress_display)) {
                                        continue;
                                    }
                                    $class = (defined($const_name)) ? ' defined' : '';
                                    ?>
                                    <tr>
                                        <td class="cap-name<?php echo $class; ?>"><?php echo $const_name; ?></td>
                                        <td class="<?php echo $class; ?>"><?php echo (defined($const_name)) ? strval(constant($const_name)) : ''; ?></td>
                                        <td class="<?php echo $class; ?>"><?php echo $ppc->constants[$const_name]->descript; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>

                    </td>
                </tr>
            <?php endif; // display constants section

        } // endif advanced options enabled

        if (is_multisite()) {
            $section = 'network';
            ?>
            <tr>
                <th scope="row"><?php echo $ui->section_captions[$tab][$section]; ?></th>
                <td>

                    <div id="pp_modify_default_settings" class="pp-settings-code">
                        <?php
                        _e('To modify one or more default settings network-wide, <strong>copy</strong> the following code into your theme&apos;s <strong>functions.php</strong> file (or some other file which is always executed and not auto-updated) and modify as desired:', 'press-permit-core');
                        ?>
                        <textarea rows='10' cols='150' readonly='readonly'>
    // Use this filter if you want to change the default, but still allow manual setting
    add_filter( 'presspermit_default_options', 'my_presspermit_default_options', 99 );

    public function my_presspermit_default_options( $def_options ) {
        // Array key corresponds to name attributes of checkboxes, dropdowns and input boxes. Modify for desired default settings.

        $def_options['new_user_groups_ui'] = 0;

        return $def_options;
    }
                                                            </textarea>
                    </div>
                    <br/>

                    <div id="pp_force_settings" class="pp-settings-code">
                        <?php
                        _e('To force the value of one or more settings network-wide, <strong>copy</strong> the following code into your theme&apos;s <strong>functions.php</strong> file (or some other file which is always executed and not auto-updated) and modify as desired:', 'press-permit-core');
                        ?>
                        <textarea rows='13' cols='150' readonly='readonly'>
    // Use this filter if you want to force an option, blocking/disregarding manual setting
    add_filter( 'presspermit_options', 'my_presspermit_options', 99 );

    // Use this filter if you also want to hide an option from the PP settings screen (works for most options)
    add_filter( 'presspermit_hide_options', 'my_pp_options', 99 );

    public function my_presspermit_options( $options ) {
        // Array key corresponds to pp_prefixed name attributes of checkboxes, dropdowns and input boxes. 
        // Modify for desired settings.

        // note: advanced options can be forced here even if advanced settings are disabled
        $options['presspermit_new_user_groups_ui'] = 1;
        $options['presspermit_display_hints'] = 0;

        return $options;
    }
                                                            </textarea>
                    </div>

                </td>
            </tr>
            <?php
        } // endif multisite
    } // end function optionsUI()
}
