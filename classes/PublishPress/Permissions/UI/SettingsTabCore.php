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
        $tabs['core'] = esc_html__('Core', 'press-permit-core');
        return $tabs;
    }

    public function sectionCaptions($sections)
    {
        $new = [
            'taxonomies' => esc_html__('Filtered Taxonomies', 'press-permit-core'),
            'post_types' => esc_html__('Filtered Post Types', 'press-permit-core'),
            'permissions' => esc_html__('Permissions', 'press-permit-core'),
            'front_end' => esc_html__('Front End', 'press-permit-core'),
            'admin' => esc_html__('Admin Back End', 'press-permit-core'),
            'user_profile' => esc_html__('User Management', 'press-permit-core'),
            'db_maint' => esc_html__('Database Maintenance', 'press-permit-core'),
        ];

        $key = 'core';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;

        return $sections;
    }

    public function optionCaptions($captions)
    {
        $opt = [
            'enabled_taxonomies' => esc_html__('Filtered Taxonomies', 'press-permit-core'),
            'create_tag_require_edit_cap' => esc_html__('Tag creation requires Tag edit capability', 'press-permit-core'),
            'enabled_post_types' => esc_html__('Filtered Post Types', 'press-permit-core'),
            'define_media_post_caps' => esc_html__('Enforce distinct edit, delete capability requirements for Media', 'press-permit-core'),
            'define_create_posts_cap' => esc_html__('Use create_posts capability', 'press-permit-core'),
            'media_search_results' => esc_html__('Search Results include Media', 'press-permit-core'),
            'term_counts_unfiltered' => esc_html__("Performance: Don't filter category / tag counts", 'press-permit-core'),
            'strip_private_caption' => esc_html__('Suppress "Private:" Caption', 'press-permit-core'),
            'force_nav_menu_filter' => esc_html__('Filter Menu Items', 'press-permit-core'),
            'display_user_profile_groups' => esc_html__('Permission Groups on User Profile', 'press-permit-core'),
            'display_user_profile_roles' => esc_html__('Supplemental Roles on User Profile', 'press-permit-core'),
            'new_user_groups_ui' => esc_html__('Select Permission Groups at User creation', 'press-permit-core'),
            'post_blockage_priority' => esc_html__('Post-specific Permissions take priority', 'press-permit-core'),
        ];

        return array_merge($captions, $opt);
    }

    public function optionSections($sections)
    {
        $new = [
            'taxonomies' => ['enabled_taxonomies', 'create_tag_require_edit_cap'],
            'post_types' => ['enabled_post_types', 'define_media_post_caps', 'define_create_posts_cap'],
            'permissions' => ['post_blockage_priority'],
            'front_end' => ['media_search_results', 'term_counts_unfiltered', 'strip_private_caption', 'force_nav_menu_filter'],
            'admin' => ['admin_hide_uneditable_posts'],
            'user_profile' => ['new_user_groups_ui', 'display_user_profile_groups', 'display_user_profile_roles'],
        ];

        $key = 'core';
        $sections[$key] = (isset($sections[$key])) ? array_merge($sections[$key], $new) : $new;
        return $sections;
    }

    public function optionsPreUI()
    {
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
                <th scope="row"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></th>
                <td>
                    <?php
                    $hint = SettingsAdmin::getStr('post_blockage_priority');
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
                <th scope="row"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></th>
                <td>
                    <?php
                    if ('term' == $scope) {
                        $option_name = 'enabled_taxonomies';
                        esc_html_e('Modify permissions for these Taxonomies:', 'press-permit-core');
                        echo '<br />';
                        
                        $_args = (defined('PRESSPERMIT_FILTER_PRIVATE_TAXONOMIES')) ? [] : ['public' => true];
                        $types = get_taxonomies($_args, 'object');

                        if (taxonomy_exists('nav_menu')) {
                            $types['nav_menu'] = get_taxonomy('nav_menu'); 
                        }

                        if ($omit_types = $pp->getUnfilteredTaxonomies()) // avoid confusion with PublishPress administrative taxonomy
                        {
                            if (!defined('PRESSPERMIT_FILTER_PRIVATE_TAXONOMIES')) {
	                            $types = array_diff_key($types, array_fill_keys((array)$omit_types, true));
	                        }
                        }

                        $hidden_types = apply_filters('presspermit_hidden_taxonomies', array_fill_keys(['nav_menu', 'post_status_core_wp_pp', 'pseudo_status_pp', 'post_visibility_pp'], true));

                        // Legacy Nav Menu filtering is currently locked on by a filter in the Collaborative Editing module, so reflect that in option storage
                        $locked_types = apply_filters('presspermit_locked_taxonomies', defined('PRESSPERMIT_COLLAB_VERSION') ? ['nav_menu' => true] : []);

                        if (defined('PRESSPERMIT_FILTER_PRIVATE_TAXONOMIES')) {
                            $hidden_types = [];
                        } else {
                        	$types = $pp->admin()->orderTypes($types);
                        }
                    } else {
                        $option_name = 'enabled_post_types';
                        esc_html_e('Modify permissions for these Post Types:', 'press-permit-core');
                        $types = get_post_types(['public' => true, 'show_ui' => true], 'object', 'or');

                        // todo: review wp_block permissions filtering
                        $omit_types = apply_filters('presspermit_unfiltered_post_types', ['wp_block']);

                        if ($omit_types) {
                            $types = array_diff_key($types, array_fill_keys((array)$omit_types, true));
                        }

                        $hidden_types = apply_filters('presspermit_hidden_post_types', []);

                        $locked_types = apply_filters('presspermit_locked_post_types', ['nav_menu' => 'nav_menu']);

                        $types = $pp->admin()->orderTypes($types);
                    }

                    $ui->all_otype_options[] = $option_name;

                    if (isset($pp->default_options[$option_name])) {
                    	$filter_name = ('term' == $scope) ? 'presspermit_enabled_taxonomies_by_key' : 'presspermit_enabled_post_types';
                    	
                        if (!$enabled = apply_filters($filter_name, $ui->getOption($option_name))) {
                            $enabled = [];
                        }

                        foreach ($types as $key => $obj) {
                            if (!$key) {
                                continue;
                            }

                            $id = $option_name . '-' . $key;
                            $name = $option_name . "[$key]";
                            ?>

                            <?php if (isset($hidden_types[$key])) : ?>
                                <input name="<?php echo esc_attr($name); ?>" type="hidden" value="<?php echo esc_attr($hidden_types[$key]); ?>"/>
                            <?php else : 
                                    $locked = (!empty($locked_types[$key])) ? ' disabled ' : '';
                                ?>
                            <div class="agp-vtight_input">
                                <input name="<?php echo esc_attr($name); ?>" type="hidden" value="<?php echo (empty($locked_types[$key])) ? '0' : '1';?>"/>
                                <label for="<?php echo esc_attr($id); ?>" title="<?php echo esc_attr($key); ?>">
                                    <input name="<?php if (empty($locked_types[$key])) echo esc_attr($name); ?>" type="checkbox" id="<?php echo esc_attr($id); ?>"
                                        value="1" <?php checked('1', !empty($enabled[$key])); echo esc_attr($locked); ?> />

                                    <?php
                                    if (isset($obj->labels_pp)) {
                                        echo esc_html($obj->labels_pp->name);
                                    } elseif ('nav_menu' == $key) {    // @todo: use labels_pp property?
                                        if (in_array(get_locale(), ['en_EN', 'en_US'])) {
                                            esc_html_e('Nav Menus (Legacy)', 'press-permit-core');
                                        } else {
                                            echo esc_html($obj->labels->singular_name) . ' (' . esc_html__('Legacy', 'press-permit-core') . ')';
                                        }
                                    } elseif ('wp_navigation' == $key) {    // @todo: use labels_pp property?
                                        if (in_array(get_locale(), ['en_EN', 'en_US'])) {
                                            esc_html_e('Nav Menus (Block)', 'press-permit-core');
                                        } else {
                                            echo esc_html($obj->labels->singular_name) . ' (' . esc_html__('Block', 'press-permit-core') . ')';
                                        }
                                    } elseif (isset($obj->labels->name)) {
                                        echo esc_html($obj->labels->name);
                                    } else {
                                        echo esc_html($key);
                                    }

                                    echo '</label>';
                                    
                                    if (!empty($enabled[$key]) && isset($obj->capability_type) && !in_array($obj->capability_type, [$obj->name, 'post', 'page'])) {
                                        if ($cap_type_obj = get_post_type_object($obj->capability_type)) {
                                            echo '&nbsp;(' . esc_html(sprintf(__('%s capabilities'), $cap_type_obj->labels->singular_name)) . ')';
                                        }
                                    }

                                    echo '</div>';
                            endif; // displaying checkbox UI

                            if ('nav_menu' == $key) : ?>
                                <!-- <input name="<?php echo esc_attr($name); ?>" type="hidden" id="<?php echo esc_attr($id); ?>" value="1"/> -->
                            <?php endif;
                        } // end foreach src_otype
                    } // endif default option isset  // phpcs:ignore Squiz.PHP.CommentedOutCode.Found

                    if ('object' == $scope) {
                        if ($pp->getOption('display_hints')) {
                            ?>
                            <div class="pp-subtext pp-no-hide">
                                <?php
                                printf(
                                    esc_html__('%1$sNote%2$s: This causes type-specific capabilities to be required for editing ("edit_things" instead of "edit_posts"). You can %3$sassign supplemental roles%4$s for the post type or add the capabilities directly to a WordPress role.', 'press-permit-core'),
                                    '<span class="pp-important">',
                                    '</span>',
                                    "<a href='" . esc_url(admin_url('?page=presspermit-groups')) . "'>",
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
                                        SettingsAdmin::echoStr('bbp_compat_prompt');
                                    } else {
                                        SettingsAdmin::echoStr('bbp_pro_prompt');
                                    }

                                    ?>
                                </div>
                            <?php
                            endif;
                        }

                        echo '<div>';

                        if (in_array('attachment', presspermit()->getEnabledPostTypes(), true)) {
                            if (!presspermit()->isPro()) {
                                $hint = SettingsAdmin::getStr('define_media_post_caps_pro');
                            } else {
                                $hint = defined('PRESSPERMIT_COLLAB_VERSION') 
                                ? SettingsAdmin::getStr('define_media_post_caps')
                                : SettingsAdmin::getStr('define_media_post_caps_collab_prompt');
                            }

                            $ret = $ui->optionCheckbox('define_media_post_caps', $tab, $section, $hint, '');
                        }

                        $ret = $ui->optionCheckbox('define_create_posts_cap', $tab, $section, '', '', ['hint_class' => 'pp-no-hide']);

                        echo '<div class="pp-subtext pp-no-hide">';

                        if (defined('PUBLISHPRESS_CAPS_VERSION')) {
                            $url = admin_url('admin.php?page=pp-capabilities');

                            printf(
                                esc_html__(
                                    '%1$sNote:%2$s If enabled, the create_posts, create_pages, etc. capabilities will be enforced for all Filtered Post Types. You can %3$sadd these capabilities to any role%4$s that needs it.', 
                                    'press-permit-core'
                                ),
                                '<span class="pp-important">',
                                '</span>',
                                '<a href="' . esc_url($url) . '">',
                                '</a>'
                            );
                        } else {
                            $url = Settings::pluginInfoURL('capability-manager-enhanced');

                            printf(
                                esc_html__(
                                    '%1$sNote:%2$s If enabled, the create_posts, create_pages, etc. capabilities will be enforced for all Filtered Post Types. You can use a WordPress role editor like %3$sPublishPress Capabilities%4$s to add these capabilities to any role that needs it.', 
                                    'press-permit-core'
                                ),
                                '<span class="pp-important">',
                                '</span>',
                                '<span class="plugins update-message"><a href="' . esc_url($url) . '" class="thickbox" title=" PublishPress Capabilities">',
                                '</a></span>'
                            );
                        }

                        echo '</div></div>';

                    } else {
                        if (defined('PRESSPERMIT_COLLAB_VERSION')) {
                            echo '<div><br />';

                            $ret = $ui->optionCheckbox('create_tag_require_edit_cap', $tab, $section, '', '', ['hint_class' => 'pp-no-hide']);

                            echo '<div class="pp-subtext pp-no-hide">';

                            if (defined('PUBLISHPRESS_CAPS_VERSION')) {
                                $url = admin_url('admin.php?page=pp-capabilities');

                                printf(
                                    esc_html__(
                                        '%1$sNote:%2$s If enabled, users cannot add previously non-existant tags to a post unless their role includes the Edit capability for its taxonomy. You can %3$sadd these capabilities to Capabilities > Capabilities > Taxonomies%4$s for any role that needs it.', 
                                        'press-permit-core'
                                    ),
                                    '<span class="pp-important">',
                                    '</span>',
                                    '<a href="' . esc_url($url) . '">',
                                    '</a>'
                                );
                            } else {
                                $url = Settings::pluginInfoURL('capability-manager-enhanced');

                                printf(
                                    esc_html__(
                                        '%1$sNote:%2$s If enabled, users cannot add previously non-existent tags to a post unless their role includes the Edit capability for its taxonomy. You can use a WordPress role editor like %3$sPublishPress Capabilities%4$s to add these capabilities to any role that needs it.', 
                                        'press-permit-core'
                                    ),
                                    '<span class="pp-important">',
                                    '</span>',
                                    '<span class="plugins update-message"><a href="' . esc_url($url) . '" class="thickbox" title=" PublishPress Capabilities">',
                                    '</a></span>'
                                );
                            }

                            echo '</div></div>';
                        }
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
                <th scope="row"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></th>
                <td>
                    <?php
                    $ui->optionCheckbox('media_search_results', $tab, $section, '');

                    $ui->optionCheckbox('term_counts_unfiltered', $tab, $section, '');

                    $hint = SettingsAdmin::getStr('strip_private_caption');
                    $ui->optionCheckbox('strip_private_caption', $tab, $section, $hint);

                    if (defined('UBERMENU_VERSION')) {
                        $hint = SettingsAdmin::getStr('force_nav_menu_filter');
                        $ui->optionCheckbox('force_nav_menu_filter', $tab, $section, $hint);
                    }
                    ?>
                </td>
            </tr>
        <?php
        endif; // any options accessable in this section

        $section = 'admin'; // --- BACK END SECTION ---
        if (!empty($ui->form_options[$tab][$section])) :
            ?>
            <tr>
                <th scope="row"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></th>
                <td>
                    <?php
                    $ui->optionCheckbox('display_branding', $tab, $section);
                    
                    if (defined('PP_ADMIN_READONLY_LISTABLE') && (!$pp->getOption('admin_hide_uneditable_posts') || defined('PP_ADMIN_POSTS_NO_FILTER'))) {
                        $hint = SettingsAdmin::getStr('posts_listing_unmodified');
                    } else {
                        $hint = ($pp->moduleActive('collaboration'))
                            ? ''
                            : SettingsAdmin::getStr('posts_listing_editable_only_collab_prompt');
                    }
                    ?>
                    
                    <?php if ($hint):?>
                    <br />
                    <div class="pp-subtext pp-subtext-show">
                    <?php
                    printf(esc_html__('%sPosts / Pages Listing:%s %s', 'press-permit-core'), '<b>', '</b>', esc_html($hint));
                    ?>
                    </div>
                    <?php endif;?>
                </td>
            </tr>
        <?php
        endif; // any options accessable in this section

        $section = 'user_profile'; // --- USER PROFILE SECTION ---
        if (!empty($ui->form_options[$tab][$section])) :
            ?>
            <tr>
                <th scope="row"><?php echo esc_html($ui->section_captions[$tab][$section]); ?></th>
                <td>
                    <?php
                    $hint = '';

                    if (!defined('is_multisite()')) {
                        $ui->optionCheckbox('new_user_groups_ui', $tab, $section, $hint, '<br />');
                    }

                    $hint = SettingsAdmin::getStr('display_user_profile_roles');
                    $ui->optionCheckbox('display_user_profile_groups', $tab, $section);
                    $ui->optionCheckbox('display_user_profile_roles', $tab, $section, $hint);
                    ?>

                </td>
            </tr>
        <?php
        endif; // any options accessable in this section
        
    }
}
