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

    public static function echoStr($string_id) {
        if ($custom_echo = apply_filters('presspermit_admin_echo_string', false, $string_id)) {
            return;
        } else {
            echo esc_html(self::getStr($string_id));
        }
    }

    public static function getStr($string_id) {
        switch ($string_id) {

        // Core
        case 'post_blockage_priority':
        return __('If disabled, manually "blocked" posts can be unblocked by specific Category / Term Permissions.', 'press-permit-core-hints');

        case 'define_media_post_caps_pro' :
        return __("For most installations, leave this disabled. If enabled, corresponding edit and delete capabilities must be added to existing roles.", 'press-permit-core-hints');

        case 'define_media_post_caps' :
        return __("For most installations, leave this disabled. See Editing tab for specialized Media Library permissions.", 'press-permit-core-hints');

        case 'define_media_post_caps_collab_prompt' :
        return  __("For most installations, leave this disabled. For specialized Media Library permissions, install the Collaborative Publishing module.", 'press-permit-core-hints');

        case 'bbp_compat_prompt' :
        return __('To customize bbPress forum permissions, activate the Compatibility Pack module.', 'press-permit-core-hints');

        case 'bbp_pro_prompt' :
        return __('To customize bbPress forum permissions, activate your Permissions Pro license key.', 'press-permit-core-hints');

        case 'strip_private_caption' :
        return __('Remove the "Private:" and "Protected" prefix from Post, Page titles', 'press-permit-core-hints');

        case 'force_nav_menu_filter' :
        return __('Remove unreadable Menu Items. If menu rendering problems occur with a third party plugin, disable this setting.', 'press-permit-core-hints');

        case 'posts_listing_unmodified' :
        return __('Unmodified from WordPress default behavior. To enable filtering, remove constant definition PP_ADMIN_READONLY_LISTABLE.', 'press-permit-core-hints');

        case 'posts_listing_editable_only_collab_prompt' :
        return __('To customize editing permissions, enable the Collaborative Publishing module.', 'press-permit-core-hints');

        case 'display_user_profile_roles' :
        return __('Note: Groups and Roles are always displayed in "Edit User"', 'press-permit-core-hints');


        // Advanced
        case 'advanced_options_enabled' :
        return __("Note: if you disable these settings, the stored values (including Role Usage adjustments) are retained but ignored.", 'press-permit-core-hints');

        case 'advanced_options_disabled' :
        return __("Most sites don't need advanced settings. But enable them if you need to work with custom WP Roles or apply performance tweaks.", 'press-permit-core-hints');

        case 'anonymous_unfiltered' :
        return __('Disable Permissions filtering for users who are not logged in.', 'press-permit-core-hints');

        case 'limit_front_end_term_filtering' :
        return __('Legacy compatibility: front end term filters are not applied on some sites', 'press-permit-core-hints');

        case 'suppress_administrator_metagroups' :
        return __('If checked, pages blocked from the "All" or "Logged In" groups will still be listed to Administrators.', 'press-permit-core-hints');

        case 'suppress_administrator_metagroups' :
        return __('If enabled, users with the pp_set_read_exceptions capability in the WP role can set reading permissions for their editable posts.', 'press-permit-core-hints');

        case 'user_search_by_role' :
        return __('Display a role dropdown alongside the user search input box to narrow results.', 'press-permit-core-hints');

        case 'display_hints' :
        return __('Display additional descriptions in role assignment and options UI.', 'press-permit-core-hints');

        case 'display_extension_hints' :
        return  __('Display descriptive captions for additional functionality provided by missing or deactivated modules (Permissions Pro package).', 'press-permit-core-hints');

        case 'pattern_roles_include_generic_rolecaps':
        return __('Supplemental roles assigned for a specific post type will always apply "_posts" capabilities in the Pattern Role (Author, Editor, etc.) for the selected post type. This setting pertains to other capabilities in the Pattern Role. For the most consistent permissions model, capabilities unrelated to a specific type should not normally be granted by a type-specific role, but some installations may require it. Enable this setting to restore previous plugin behavior; leave it disabled for more narrowly targeted Supplemental Roles.', 'press-permit-core-hints');

        case 'dynamic_wp_roles' :
        return __('Detect user roles which are appended dynamically but not stored to the WP database. May be useful for sites that sync with Active Directory or other external user registration systems.', 'press-permit-core-hints');

        case 'pp_capabilities' :
        return  __('You can also %1$s add Permissions administration capabilities to a WordPress role%2$s:', 'press-permit-core-hints');

        case 'pp_capabilities_install_prompt' :
        return __('You can add Permissions administration capabilities to a WordPress role using %1$s:', 'press-permit-core-hints');


        // Editing
        case 'collaborative-publishing' :
        return sprintf(__('Settings related to content editing permissions, provided by the %s module.', 'press-permit-core-hints'), __('Collaborative Publishing', 'press-permit-core-hints'));

        case 'list_others_uneditable_posts' :
        return __('If this setting is disabled, a specific role can be given capabilities: list_others_posts, list_others_pages, etc.', 'press-permit-core-hints');

        case 'force_taxonomy_cols' :
        return __('Display a custom column on Edit Posts screen for all related taxonomies which are enabled for Permissions filtering.', 'press-permit-core-hints');

        case 'add_author_pages' :
        return __('Allows creation of a new post (of any type) for each selected user, using an existing post as the pattern.', 'press-permit-core-hints');

        case 'lock_top_pages' :
        return __('Users who do not meet this site-wide role requirement will not be able to publish new top-level pages (Parent = "Main Page").  They will also be unable to move a currently published page from "Main Page" to a different Page Parent.', 'press-permit-core-hints');

        case 'limited_editing_elements' :
        return __('Remove Edit Form elements with these (comma-separated) html IDs from users who do not have full editing capabilities for the post/page.', 'press-permit-core-hints');

        case 'media_lib_unfiltered' :
        return __('The following settings are currently overridden by the constant PP_MEDIA_LIB_UNFILTERED (defined in wp-config.php or some other file you maintain). Media Library access will not be altered by Permissions.', 'press-permit-core-hints');

        case 'admin_others_attached_to_readable' :
        return __("To allow a role to view all media regardless of this setting, give it the pp_list_all_files capability.", 'press-permit-core-hints');

        case 'admin_others_attached_files' :
        return '';

        case 'edit_others_attached_files' :
        return __("To enable a specific role instead, give it the list_others_unattached_files capability. Note that Media Editors can always view and edit these files.", 'press-permit-core-hints');

        case 'admin_others_unattached_files' :
        return '';

        case 'own_attachments_always_editable' :
        return __("If disabled, access may be blocked based on the attachment page. In that case, a role can be given the edit_own_attachments capability, or Permissions for a specific file.", 'press-permit-core-hints');

        case 'admin_nav_menu_partial_editing' :
        return __('Allow non-Administrators to rename menu items they cannot fully edit. Menu items will be locked into current positions.', 'press-permit-core-hints');

        case 'admin_nav_menu_lock_custom' :
        return __('Prevent creation or editing of custom items for non-Administrators who lack edit_theme_options capability.', 'press-permit-core-hints');

        case 'limit_user_edit_by_level' :
        return __('Prevent non-Administrators with user editing permissions from editing a higher-level user or assigning a role higher than their own.', 'press-permit-core-hints');

        case 'fork_published_only' :
        return __('Fork published posts only.', 'press-permit-core-hints');

        case 'fork_require_edit_others' :
        return __("If a user lacks the edit_others_posts capability for the post type, they cannot fork other's posts either.", 'press-permit-core-hints');

        case 'non_admins_set_edit_exceptions' :
        return __('If enabled, the capabilities pp_set_edit_exceptions, pp_set_associate_exceptions, etc. will be honored. See list of capabilities below.', 'press-permit-core-hints');


        // Import
        case 'pp-import-disable' :
        return sprintf(__('Once your import task is complete, you can eliminate this tab by disabling the %s module.', 'press-permit-core-hints'), __('Import', 'press-permit-core-hints'));

        default:
        }

        return apply_filters('presspermit_admin_get_string', '', $string_id);
    }

    public static function getConstantStr($constant) {
        switch ($constant) {

        case 'PP_NON_EDITORS_SET_EDIT_EXCEPTIONS' :
		return esc_html__("Enable post contributors or authors with pp_set_edit_exceptions capability to set editing Permissions on posts authored by others", 'press-permit-core-hints');

        // 'editing'
        case 'PP_DISABLE_FORKING_SUPPORT' :
		return esc_html__("Don't try to integrate with the Post Forking plugin", 'press-permit-core-hints');

        case 'PP_LOCK_OPTION_PAGES_ONLY' :
		return esc_html__("Permissions setting 'Pages can be set or removed from Top Level by' applies to 'page' type only", 'press-permit-core-hints');

        case 'PPCE_LIMITED_EDITORS_TOP_LEVEL_PUBLISH' :
		return esc_html__("If user cannot generally save pages to top level but a page they are editing is already there, allow it to stay at top level even if not yet published ", 'press-permit-core-hints');

        case 'PPC_ASSOCIATION_NOFILTER' :
		return esc_html__("Circle membership does not limit page association (page parent setting) ability", 'press-permit-core-hints');

        case 'PP_AUTO_DEFAULT_TERM' :
		return esc_html__("Determines the default value of Permissions > Settings > Editing > Auto-assign default term", 'press-permit-core-hints');

        case 'PP_AUTO_DEFAULT_CATEGORY' :
		return esc_html__("When saving a post, if default category is not in user's subset of assignable categories, substitute first available", 'press-permit-core-hints');

        case 'PP_AUTO_DEFAULT_POST_TAG' :
		return esc_html__("When saving a post, if default tag is not in user's subset of assignable tags, substitute first available", 'press-permit-core-hints');

        case 'PP_AUTO_DEFAULT_CUSTOM_TAXOMY_NAME_HERE' :
		return esc_html__("When saving a post, if default term (of specified taxonomy) is not in user's subset of assignable tags, substitute first available", 'press-permit-core-hints');

        case 'PP_NO_AUTO_DEFAULT_TERM' :
		return esc_html__("Determines the default setting of Permissions > Settings > Editing > Auto-assign default terms", 'press-permit-core-hints');

        case 'PP_AUTO_DEFAULT_CATEGORY' :
		return esc_html__("When saving a post, never auto-assign a category, even if it is the user's only assignable category", 'press-permit-core-hints');

        case 'PP_NO_AUTO_DEFAULT_POST_TAG' :
		return esc_html__("When saving a post, never auto-assign a tag, even if it is the user's only assignable tag", 'press-permit-core-hints');

        case 'PP_NO_AUTO_DEFAULT_CUSTOM_TAXOMY_NAME_HERE' :
		return esc_html__("When saving a post, never auto-assign a term (of specified taxonomy), even if it is the user's only assignable term", 'press-permit-core-hints');

        case 'PPCE_DISABLE_CATEGORY_RETENTION' :
		return esc_html__("When a limited user updates a post, strip out currently stored categories they don't have permission to assign", 'press-permit-core-hints');

        case 'PPCE_DISABLE_POST_TAG_RETENTION' :
		return esc_html__("When a limited user updates a post, strip out currently stored tags they don't have permission to assign", 'press-permit-core-hints');

        case 'PPCE_DISABLE_CUSTOM_TAXOMY_NAME_HERE_RETENTION' :
		return esc_html__("When a limited user updates a post, strip out currently stored terms (of specified taxonomy) they don't have permission to assign", 'press-permit-core-hints');

        case 'PP_NO_MODERATION' :
		return esc_html__("Don't define an 'Approved' status, even if Status Control module is active", 'press-permit-core-hints');


        // 'nav-menu-manage'
        case 'PP_SUPPRESS_APPEARANCE_LINK' :
		return esc_html__("If user has Nav Menu management capabilities but can't 'edit_theme_options', strip link out of wp-admin Appearance Menu instead of linking it to nav-menus", 'press-permit-core-hints');

        case 'PP_STRICT_MENU_CAPS' :
		return esc_html__("Don't credit implicit 'manage_nav_menus' capability to users who have 'edit_theme_options' or 'switch_themes' capability", 'press-permit-core-hints');

        case 'PPCE_RESTRICT_MENU_TOP_LEVEL' :
		return esc_html__("Prevent non-Administrators from adding new Nav Menu items to top level (add below existing editable items instead)", 'press-permit-core-hints');

        case 'PP_NAV_MENU_DEFAULT_TO_SUBITEM' :
		return esc_html__("For non-Administrators, new Nav Menu items default to being a child of first editable item ", 'press-permit-core-hints');

        case 'PP_LEGACY_MENU_SETTINGS_ACCESS' :
		return esc_html__("Don't require any additional capabilities for management of Nav Menu settings (normally require 'manage_menu_settings', 'edit_others_pages' or 'publish_pages') ", 'press-permit-core-hints');

        case 'PPCE_DISABLE_NAV_MENU_UPDATE_FILTERS' :
		return esc_html__("Eliminate extra filtering queries on Nav Menu update, even for non-Administrators", 'press-permit-core-hints');


        // 'media'
        case 'PP_BLOCK_UNATTACHED_UPLOADS' :
		return esc_html__("Don't allow non-Administrators to see others' unattached uploads, regardless of Permissions settings.  Their own unattached uploads are still accessible unless option 'own_attachments_always_editable' is set false", 'press-permit-core-hints');


        // 'admin'
        case 'PPCE_CAN_ASSIGN_OWN_ROLE' :
		return esc_html__("Limited User Editors can assign their own role", 'press-permit-core-hints');

        case 'PP_AUTHOR_POST_META' :
		return esc_html__("Post Meta fields to copy when using 'Add Author Page' dropdown on Users screen", 'press-permit-core-hints');

        default:
        }

        return apply_filters('presspermit_get_constant_descript', '', $constant);
    }

    static function setCapabilityDescriptions($pp_caps) {
        $pp_caps['pp_manage_settings'] = esc_html__('Modify these Permissions settings', 'press-permit-core-hints');
        $pp_caps['pp_unfiltered'] = esc_html__('PublishPress Permissions does not apply any Supplemental Roles or Specific Permissions to limit or expand viewing or editing access', 'press-permit-core-hints');
        $pp_caps['pp_administer_content'] = esc_html__('PublishPress Permissions implicitly grants capabilities for all post types and statuses, but does not apply Specific Permissions', 'press-permit-core-hints');
        $pp_caps['pp_create_groups'] = esc_html__('Can create Permission Groups', 'press-permit-core-hints');
        $pp_caps['pp_edit_groups'] = esc_html__('Can edit all Permission Groups (barring Specific Permissions)', 'press-permit-core-hints');
        $pp_caps['pp_delete_groups'] = esc_html__('Can delete Permission Groups', 'press-permit-core-hints');
        $pp_caps['pp_manage_members'] = esc_html__('If group editing is allowed, can also modify group membership', 'press-permit-core-hints');
        $pp_caps['pp_assign_roles'] = esc_html__('Assign Supplemental Roles or Specific Permissions. Other capabilities may also be required.', 'press-permit-core-hints');
        $pp_caps['pp_set_read_exceptions'] = esc_html__('Set Read Permissions for specific posts on Edit Post/Term screen (for non-Administrators lacking edit_users capability; may be disabled by Permissions Settings)', 'press-permit-core-hints');

        if (class_exists('Fork', false)) {
            $pp_caps['pp_set_fork_exceptions'] = esc_html__('Set Forking Permissions on Edit Post/Term screen (where applicable)', 'press-permit-core-hints');
        }

        if (defined('PUBLISHPRESS_REVISIONS_VERSION') || defined('REVISIONARY_VERSION')) {
            $pp_caps['pp_set_revise_exceptions'] = esc_html__('Set Revision Permissions on Edit Post/Term screen (where applicable)', 'press-permit-core-hints');
        }

        $pp_caps['pp_set_edit_exceptions'] =            esc_html__('Set Editing Permissions on Edit Post/Term screen (where applicable)', 'press-permit-core-hints');
        $pp_caps['pp_set_associate_exceptions'] =       esc_html__('Set Association (Parent) Permissions on Edit Post screen (where applicable)', 'press-permit-core-hints');
        $pp_caps['pp_set_term_assign_exceptions'] =     esc_html__('Set Term Assignment Permissions on Edit Term screen (in relation to an editable post type)', 'press-permit-core-hints');
        $pp_caps['pp_set_term_manage_exceptions'] =     esc_html__('Set Term Management Permissions on Edit Term screen', 'press-permit-core-hints');
        $pp_caps['pp_set_term_associate_exceptions'] =  esc_html__('Set Term Association (Parent) Permissions on Edit Term screen', 'press-permit-core-hints');

        $pp_caps['edit_own_attachments'] =          esc_html__('Edit own file uploads, even if they become attached to an uneditable post', 'press-permit-core-hints');
        $pp_caps['list_others_unattached_files'] =  esc_html__("See other user's unattached file uploads in Media Library", 'press-permit-core-hints');
        $pp_caps['pp_associate_any_page'] =         esc_html__('Disregard association permissions (for all hierarchical post types)', 'press-permit-core-hints');

        $pp_caps['pp_list_all_files'] =     esc_html__('Do not alter the Media Library listing provided by WordPress', 'press-permit-core-hints');
        $pp_caps['list_posts'] =            esc_html__('On the Posts screen, satisfy a missing edit_posts capability by listing uneditable drafts', 'press-permit-core-hints');
        $pp_caps['list_others_posts'] =     esc_html__("On the Posts screen, satisfy a missing edit_others_posts capability by listing other user's uneditable posts", 'press-permit-core-hints');
        $pp_caps['list_private_pages'] =    esc_html__('On the Pages screen, satisfy a missing edit_private_pages capability by listing uneditable private pages', 'press-permit-core-hints');
        $pp_caps['pp_force_quick_edit'] =   esc_html__('Make Quick Edit and Bulk Edit available to non-Administrators even though some inappropriate selections may be possible', 'press-permit-core-hints');

        if (!defined('PRESSPERMIT_PRO_VERSION') && !presspermit()->moduleActive('status-control') && !presspermit()->keyActive()) {
            $pp_caps = array_merge(
                $pp_caps,
                [
                    'pp_define_post_status' => esc_html__('(Permissions Pro capability)', 'press-permit-core-hints'),
                    'pp_define_moderation' => esc_html__('(Permissions Pro capability)', 'press-permit-core-hints'),
                    'pp_define_privacy' => esc_html__('(Permissions Pro capability)', 'press-permit-core-hints'),
                    'set_posts_status' => esc_html__('(Permissions Pro capability)', 'press-permit-core-hints'),
                    'pp_moderate_any' => esc_html__('(Permissions Pro capability)', 'press-permit-core-hints'),
                ]
            );
        }

        return $pp_caps;
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

    public function optionCheckbox($option_name, $tab_name, $section_name, $hint_text = '', $trailing_break = '', $args = [])
    {
        $return = ['in_scope' => false, 'no_storage' => false, 'disabled' => false, 'title' => '', 'style' => '', 'div_style' => ''];

        if (in_array($option_name, $this->form_options[$tab_name][$section_name], true)) {
            $display_label = (!isset($args['display_label'])) ? true : $args['display_label'];
            
            if (empty($args['no_storage']))
                $this->all_options[] = $option_name;

            if (isset($args['val']))
                $return['val'] = $args['val'];
            else
                $return['val'] = (!empty($args['no_storage'])) ? 0 : presspermit()->getOption($option_name);

            $disabled = (!empty($args['disabled']) || $this->hideNetworkOption($option_name)) ? " disabled " : '';
            $style = (!empty($args['style'])) ? $args['style'] : '';
            $div_style = (!empty($args['div_style'])) ? $args['div_style'] : '';

            $title = (!empty($args['title'])) ? $args['title'] : '';

            echo "<div class='agp-opt-checkbox " . esc_attr($option_name) . "' style='" . esc_attr($div_style) . "'>"
                . "<label for='" . esc_attr($option_name) . "' title='" . esc_attr($title) . "'>"
                . "<input name='" . esc_attr($option_name) . "' type='checkbox' " . esc_attr($disabled) . " style='" . esc_attr($style) . "' id='" . esc_attr($option_name) . "' value='1' " . esc_attr(checked('1', $return['val'], false)) . " autocomplete='off' /> ";
                
            if ($display_label) {
                esc_html_e($this->option_captions[$option_name]);
            }
            
            echo "</label>";

            if ($hint_text && $this->display_hints) {
                $hint_class = 'pp-subtext';
                $hint_class .= (!empty($args['hint_class'])) ? ' ' . $args['hint_class'] : '';

                if (true === $hint_text) :?>
                    <?php if (self::getStr($option_name)):?>
                        <div class='<?php echo esc_attr($hint_class); ?>'><?php self::echoStr($option_name);?></div>
                    <?php endif;?>
                <?php else :?>
                    <div class='<?php echo esc_attr($hint_class); ?>'><?php echo(esc_html($hint_text));?></div>
                <?php endif;
            }

            echo "</div>";

            if ($trailing_break)
                echo '<br />';

            $return['in_scope'] = true;
        }

        return $return;
    }

    private function hideNetworkOption($option_name)
    {
                                                                        // Precautionary exception: don't change storage location for license key
    	$is_main_site = (defined('PRESSPERMIT_LEGACY_MAIN_SITE_CHECK') || (('edd_key' == $option_name) && !defined('PRESSPERMIT_EDD_KEY_SITEMETA_STORAGE'))) ? (1 == get_current_blog_id()) : is_main_site();
    	
        if (is_multisite()) {
            return (in_array($option_name, presspermit()->netwide_options, true) && PWP::isNetworkActivated()
                && !is_network_admin() && !$is_main_site);
        } else
            return false;
    }

    public function filterNetworkOptions()
    {
    	$is_main_site = (defined('PRESSPERMIT_LEGACY_MAIN_SITE_CHECK')) ? (1 == get_current_blog_id()) : is_main_site();
    	
        if (is_multisite() && !is_network_admin() && !$is_main_site) {
            $pp = presspermit();
            $this->all_options = array_diff($this->all_options, $pp->netwide_options);
            $this->all_otype_options = array_diff($this->all_otype_options, $pp->netwide_options);
        }
    }
}
