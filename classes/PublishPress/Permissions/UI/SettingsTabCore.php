<?php

namespace PublishPress\Permissions\UI;

class SettingsTabCore
{
    public function __construct()
    {
        add_filter('presspermit_option_tabs', [$this, 'optionTabs'], 2);
        add_filter('presspermit_section_captions', [$this, 'sectionCaptions']);
        add_filter('presspermit_option_captions', [$this, 'optionCaptions']);
        add_filter('presspermit_option_sections', [$this, 'optionSections']);

        add_action('presspermit_core_options_pre_ui', [$this, 'optionsPreUI']);

        add_action('presspermit_core_options_ui', [$this, 'optionsUI']);
    }

    public function optionTabs($tabs)
    {
        $tabs['core'] = __('Core', 'press-permit-core');
        return $tabs;
    }

    public function sectionCaptions($sections)
    {
        $new = [
            'taxonomies' => __('Filtered Taxonomies', 'press-permit-core'),
            'post_types' => __('Filtered Post Types', 'press-permit-core'),
            'permissions' => __('Permissions', 'press-permit-core'),
            'front_end' => __('Front End', 'press-permit-core'),
            'admin' => __('Admin Back End', 'press-permit-core'),
            'user_profile' => __('User Management / Profile', 'press-permit-core'),
            'db_maint' => __('Database Maintenance', 'press-permit-core'),
        ];

        $key = 'core';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;

        return $sections;
    }

    public function optionCaptions($captions)
    {
        $opt = [
            'enabled_taxonomies' => __('Filtered Taxonomies', 'press-permit-core'),
            'enabled_post_types' => __('Filtered Post Types', 'press-permit-core'),
            'define_media_post_caps' => __('Enforce distinct edit, delete capability requirements for Media', 'press-permit-core'),
            'define_create_posts_cap' => __('Use create_posts capability', 'press-permit-core'),
            'strip_private_caption' => __('Suppress "Private:" Caption', 'press-permit-core'),
            'display_user_profile_groups' => __('Permission Groups on User Profile', 'press-permit-core'),
            'display_user_profile_roles' => __('Supplemental Roles on User Profile', 'press-permit-core'),
            'new_user_groups_ui' => __('Select Permission Groups at User creation', 'press-permit-core'),
            'admin_hide_uneditable_posts' => __('Hide non-editable posts', 'press-permit-core'),
            'post_blockage_priority' => __('Post-assigned Exceptions take priority', 'press-permit-core'),
        ];

        return array_merge($captions, $opt);
    }

    public function optionSections($sections)
    {
        $new = [
            'taxonomies' => ['enabled_taxonomies'],
            'post_types' => ['enabled_post_types', 'define_media_post_caps', 'define_create_posts_cap'],
            'permissions' => ['post_blockage_priority'],
            'front_end' => ['strip_private_caption'],
            'admin' => ['admin_hide_uneditable_posts'],
            'user_profile' => ['new_user_groups_ui', 'display_user_profile_groups', 'display_user_profile_roles'],
        ];

        $key = 'core';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionsPreUI()
    {
        if (SettingsAdmin::instance()->getOption('display_hints')) {
            echo '<div class="pp-optionhint">';
            _e("Basic settings for content filtering, management and presentation.", 'press-permit-core');
            do_action('presspermit_options_form_hint');
            echo '</div>';
        }
    }

    public function optionsUI()
    {
        $pp = presspermit();

        $ui = SettingsAdmin::instance();
        $tab = 'core';

        $section = 'permissions'; // --- PERMISSIONS SECTION ---
        if (!empty($ui->form_options[$tab][$section])) :
            ?>
            <tr>
                <th scope="row"><?php echo $ui->section_captions[$tab][$section]; ?></th>
                <td>
                    <?php
                    $hint = __('If disabled, manually "blocked" posts can be unblocked by Category / Term Exceptions.  Enabling this setting will provide more intuitive behavior, but may require configuration review and testing on prior installations.', 'press-permit-core');
                    $ui->optionCheckbox('post_blockage_priority', $tab, $section, $hint);
                    ?>
                </td>
            </tr>
        <?php
        endif; // any options accessable in this section

        // --- FILTERED TAXONOMIES / POST TYPES SECTION ---
        foreach (['object' => 'post_types', 'term' => 'taxonomies'] as $scope => $section) {
            if (empty($ui->form_options[$tab][$section])) {
                continue;
            }

            ?>
            <tr>
                <th scope="row"><?php echo $ui->section_captions[$tab][$section]; ?></th>
                <td>
                    <?php
                    if ('term' == $scope) {
                        $option_name = 'enabled_taxonomies';
                        _e('Modify permissions for these Taxonomies:', 'press-permit-core');
                        echo '<br />';
                        $types = get_taxonomies(['public' => true], 'object');

                        $omit_types = apply_filters('presspermit_unfiltered_taxonomies', ['post_status', 'topic-tag']);

                        if ($omit_types) // avoid confusion with PublishPress administrative taxonomy
                        {
                            $types = array_diff_key($types, array_fill_keys((array)$omit_types, true));
                        }

                        $hidden_types = apply_filters('presspermit_hidden_taxonomies', []);

                        $types = $pp->admin()->orderTypes($types);
                    } else {
                        $option_name = 'enabled_post_types';
                        _e('Modify permissions for these Post Types:', 'press-permit-core');
                        $types = get_post_types(['public' => true, 'show_ui' => true], 'object', 'or');

                        // @todo: review wp_block permissions filtering
                        $omit_types = apply_filters('presspermit_unfiltered_post_types', ['wp_block']);

                        if ($omit_types) {
                            $types = array_diff_key($types, array_fill_keys((array)$omit_types, true));
                        }

                        $hidden_types = apply_filters('presspermit_hidden_post_types', []);

                        $locked_types = apply_filters('presspermit_locked_post_types', []);

                        $types = $pp->admin()->orderTypes($types);
                    }

                    $ui->all_otype_options[] = $option_name;

                    if (isset($pp->default_options[$option_name])) {
                    if (!$enabled = apply_filters('presspermit_enabled_post_types', $ui->getOption($option_name))) {
                        $enabled = [];
                    }

                    foreach ($types as $key => $obj) {
                    if (!$key) {
                        continue;
                    }

                    $id = $option_name . '-' . $key;
                    $name = $option_name . "[$key]";
                    ?>

                    <?php if ('nav_menu' == $key) : ?>
                        <input name="<?php echo($name); ?>" type="hidden" id="<?php echo($id); ?>" value="1"/>
                    <?php else : ?>
                    <?php if (isset($hidden_types[$key])) : ?>
                        <input name="<?php echo($name); ?>" type="hidden" value="<?php echo $hidden_types[$key]; ?>"/>
                    <?php else : 
                        $locked = (!empty($locked_types[$key])) ? 'disabled=disabled' : '';
                        ?>
                    <div class="agp-vtight_input">
                        <input name="<?php echo $name; ?>" type="hidden" value="<?php echo (empty($locked_types[$key])) ? '0' : '1';?>"/>
                        <label for="<?php echo($id); ?>" title="<?php echo($key); ?>">
                            <input name="<?php echo (empty($locked_types[$key])) ? $name : ''; ?>" type="checkbox" id="<?php echo($id); ?>"
                                   value="1" <?php checked('1', !empty($enabled[$key])); echo $locked; ?> />

                            <?php
                            if (isset($obj->labels_pp)) {
                                echo $obj->labels_pp->name;
                            } elseif (isset($obj->labels->name)) {
                                echo $obj->labels->name;
                            } else {
                                echo $key;
                            }

                            echo('</label>');
                            
                            if (!empty($enabled[$key]) && isset($obj->capability_type) && !in_array($obj->capability_type, [$obj->name, 'post', 'page'])) {
                                if ($cap_type_obj = get_post_type_object($obj->capability_type)) {
                                    echo '&nbsp;(' . sprintf(__('%s capabilities'), $cap_type_obj->labels->singular_name) . ')';
                                }
                            }

                            echo('</div>');
                            endif;
                            endif; // displaying checkbox UI

                            } // end foreach src_otype
                            } // endif default option isset

                            if ('object' == $scope) {
                                if ($pp->getOption('display_hints')) {
                                    //if ( $types = get_post_types( [ 'public' => true, '_builtin' => false ] ) ) :
                                    ?>
                                    <div class="pp-subtext">
                                        <?php
                                        printf(
                                            __('<span class="pp-important">Note</span>: Custom post types enabled here require type-specific capabilities for editing ("edit_things" instead of "edit_posts").  You can %1$sassign corresponding supplemental roles%2$s to grant these capabilities. Adding the type-specific capabilities directly to a WordPress role definition also works.'),
                                            "<a href='" . admin_url('?page=presspermit-groups') . "'>",
                                            '</a>'
                                        );
                                        ?>
                                    </div>

                                    <?php if (
                                        in_array('forum', $types, true) && !$pp->moduleActive('compatibility')
                                        && $pp->getOption('display_extension_hints')
                                    ) : ?>
                                        <div class="pp-subtext pp-settings-caption">
                                            <?php
                                            if ($pp->keyActive()) {
                                                _e('To customize bbPress forum permissions, activate the Compatibility Pack module.', 'press-permit-core');
                                            } else {
                                                _e('To customize bbPress forum permissions, activate your Permissions Pro license key.', 'press-permit-core');
                                            }

                                            ?>
                                        </div>
                                    <?php
                                    endif;
                                    //endif;
                                }

                                echo '<br /><div>';

                                if (in_array('attachment', presspermit()->getEnabledPostTypes(), true)) {
                                    if (!presspermit()->isPro()) {
                                        $hint = __("For most installations, leave this disabled. If enabled, corresponding edit and delete capabilities must be added to existing roles.", 'press-permit-core');
                                    } else {
                                        $hint = defined('PRESSPERMIT_COLLAB_VERSION') 
                                        ? __("For most installations, leave this disabled. See Editing tab for specialized Media Library permissions.", 'press-permit-core')
                                        : __("For most installations, leave this disabled. For specialized Media Library permissions, install the Collaborative Publishing module.", 'press-permit-core');
                                    }

                                    $ret = $ui->optionCheckbox('define_media_post_caps', $tab, $section, $hint, '');
                                }

                                $hint = __('If enabled, the create_posts, create_pages, etc. capabilities will be enforced for all Filtered Post Types.  <strong>NOTE: You will also need to use a WordPress role editor</strong> such as PublishPress Capabilities to add these capabilities to desired roles.', 'press-permit-core');
                                $ret = $ui->optionCheckbox('define_create_posts_cap', $tab, $section, $hint, '');
                                echo '</div>';
                            }
                            ?>
                </td>
            </tr>
            <?php
        } // end foreach scope

        $section = 'front_end'; // --- FRONT END SECTION ---
        if (!empty($ui->form_options[$tab][$section])) :
            ?>
            <tr>
                <th scope="row"><?php echo $ui->section_captions[$tab][$section]; ?></th>
                <td>
                    <?php
                    $hint = __('Remove the "Private:" and "Protected" prefix from Post, Page titles', 'press-permit-core');
                    $ui->optionCheckbox('strip_private_caption', $tab, $section, $hint);
                    ?>
                </td>
            </tr>
        <?php
        endif; // any options accessable in this section

        $section = 'admin'; // --- BACK END SECTION ---
        if (!empty($ui->form_options[$tab][$section])) :
            ?>
            <tr>
                <th scope="row"><?php echo $ui->section_captions[$tab][$section]; ?></th>
                <td>
                    <?php
                    $ui->optionCheckbox('display_branding', $tab, $section, '');
                    
                    $listable = defined('PP_ADMIN_READONLY_LISTABLE');

                    $hint = ($pp->moduleExists('collaboration') && !$listable)
                        ? __('Note: To allow listing of uneditable posts in wp-admin, define constant PP_ADMIN_READONLY_LISTABLE', 'press-permit-core')
                        : '';

                    $args = ($pp->moduleActive('collaboration') && !$listable)
                        ? ['val' => 1, 'disabled' => true, 'no_storage' => true]
                        : [];

                    $ui->optionCheckbox('admin_hide_uneditable_posts', $tab, $section, $hint, '', $args);
                    ?>
                </td>
            </tr>
        <?php
        endif; // any options accessable in this section

        $section = 'user_profile'; // --- USER PROFILE SECTION ---
        if (!empty($ui->form_options[$tab][$section])) :
            ?>
            <tr>
                <th scope="row"><?php echo $ui->section_captions[$tab][$section]; ?></th>
                <td>
                    <?php
                    $hint = '';

                    if (!defined('is_multisite()')) {
                        $ui->optionCheckbox('new_user_groups_ui', $tab, $section, $hint, '<br />');
                    }

                    $hint = __('note: Groups and Roles are always displayed in "Edit User"', 'press-permit-core');
                    $ui->optionCheckbox('display_user_profile_groups', $tab, $section);
                    $ui->optionCheckbox('display_user_profile_roles', $tab, $section, $hint);
                    ?>

                </td>
            </tr>
        <?php
        endif; // any options accessable in this section
        
    } // end function optionsUI()
}
