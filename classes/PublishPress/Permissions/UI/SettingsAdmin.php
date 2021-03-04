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

        case 'posts_listing_editable_only' :
        return __('Uneditable posts are hidden from wp-admin listings. To expose them, use a role editor to add desired capabilities: list_posts, list_other_pages etc.', 'press-permit-core-hints');

        case 'posts_listing_editable_only_collab_prompt' :
        return __('To customize editing permissions, enable the Collaborative Publishing module.', 'press-permit-core-hints');

        case 'display_user_profile_roles' :
        return __('note: Groups and Roles are always displayed in "Edit User"', 'press-permit-core-hints');

        
        // Advanced
        case 'advanced_options_enabled' :
        return __("<strong>Note:</strong> if you disable these settings, the stored values (including Role Usage adjustments) are retained but ignored.", 'press-permit-core-hints');

        case 'advanced_options_disabled' :
        return __('Most sites don\'t need advanced settings. But enable them if you need to work with custom WP Roles or apply performance tweaks.', 'press-permit-core-hints');

        case 'anonymous_unfiltered' :
        return sprintf(__('Disable Permissions filtering for users who are not logged in.', 'press-permit-core-hints'), '<span class="pp-warning"><strong>', '</strong></span>');

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
    
        case 'dynamic_wp_roles' :
        return __('Detect user roles which are appended dynamically but not stored to the WP database. May be useful for sites that sync with Active Directory or other external user registration systems.', 'press-permit-core-hints');
    
        case 'pp_capabilities' :
        return  __('You can also %1$s add Permissions administration capabilities to a WordPress role%2$s:', 'press-permit-core-hints');

        case 'pp_capabilities_install_prompt' :
        return __('You can add Permissions administration capabilities to a WordPress role using %1$s:', 'press-permit-core-hints');


        // Editing
        case 'collaborative-publishing' :
        return sprintf(__('Settings related to content editing permissions, provided by the %s module.', 'press-permit-core-hints'), __('Collaborative Publishing', 'press-permit-core-hints'));
    
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
        return __("For non-Administrators, determines visibility of files uploaded by another user and now attached to a post which the logged user can edit. To force a user to view all media regardless of this setting, add the pp_list_all_files capability to their role.", 'press-permit-core-hints');
    
        case 'admin_others_attached_files' :
        return __("For non-Administrators, determines editing access to files uploaded by another user and now attached to a post which the logged user can edit.", 'press-permit-core-hints');

        case 'edit_others_attached_files' :
        return __("If enabled, all users who have Media Library access will be implicitly granted the list_others_unattached_files capability. Media Editors can view and edit regardless of this setting.", 'press-permit-core-hints');
        
        case 'admin_others_unattached_files' :
        return __("For non-Administrators, determines editing access to files uploaded by another user and now attached to a post which the logged user can edit.", 'press-permit-core-hints');

        case 'own_attachments_always_editable' :
        return __("Ensures users can always edit attachments they have uploaded, even if they are later attached to a post which the user cannot edit. If disabled, you can grant individual users the edit_own_attachments capability or assign Media editing Permissions for individual files.", 'press-permit-core-hints');
                
        case 'admin_nav_menu_partial_editing' :
        return __('Allow non-Administrators to rename menu items they cannot fully edit. Menu items will be locked into current positions.', 'press-permit-core-hints');
    
        case 'admin_nav_menu_lock_custom' :
        return __('Prevent creation or editing of custom items for non-Administrators who lack edit_theme_options capability.', 'press-permit-core-hints');

        case 'limit_user_edit_by_level' :
        return __('Prevent non-Administrators with user editing permissions from editing a higher-level user or assigning a role higher than their own.', 'press-permit-core-hints');
    
        case 'fork_published_only' :
        return __('Fork published posts only.', 'press-permit-core-hints');

        case 'fork_require_edit_others' :
        return __('If a user lacks the edit_others_posts capability for the post type, they cannot fork other&apos;s posts either.', 'press-permit-core-hints');

        case 'non_admins_set_edit_exceptions' :
        return __('If enabled, presence of the pp_set_edit_exceptions, pp_set_associate_exceptions, etc. capabilities in the WP role will be honored. See list of capabilities below.', 'press-permit-core-hints');
    

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
		return __("Enable post contributors or authors with pp_set_edit_exceptions capability to set editing Permissions on posts authored by others", 'press-permit-core-hints');

        // 'editing'
        case 'PP_DISABLE_FORKING_SUPPORT' :
		return __("Don't try to integrate with the Post Forking plugin", 'press-permit-core-hints');

        case 'PP_LOCK_OPTION_PAGES_ONLY' :
		return __("Permissions setting 'Pages can be set or removed from Top Level by' applies to 'page' type only", 'press-permit-core-hints');

        case 'PPCE_LIMITED_EDITORS_TOP_LEVEL_PUBLISH' :
		return __("If user cannot generally save pages to top level but a page they are editing is already there, allow it to stay at top level even if not yet published ", 'press-permit-core-hints');

        case 'PPC_ASSOCIATION_NOFILTER' :
		return __("Circle membership does not limit page association (page parent setting) ability", 'press-permit-core-hints');

        case 'PP_AUTO_DEFAULT_TERM' :
		return __("When saving a post, if default term (of any taxonomy) is not in user's subset of assignable terms, substitute first available", 'press-permit-core-hints');

        case 'PP_AUTO_DEFAULT_CATEGORY' :
		return __("When saving a post, if default category is not in user's subset of assignable categories, substitute first available", 'press-permit-core-hints');

        case 'PP_AUTO_DEFAULT_POST_TAG' :
		return __("When saving a post, if default tag is not in user's subset of assignable tags, substitute first available", 'press-permit-core-hints');

        case 'PP_AUTO_DEFAULT_CUSTOM_TAXOMY_NAME_HERE' :
		return __("When saving a post, if default term (of specified taxonomy) is not in user's subset of assignable tags, substitute first available", 'press-permit-core-hints');

        case 'PP_NO_AUTO_DEFAULT_TERM' :
		return __("When saving a post, never auto-assign a term (of any taxonomy), even if it is the user's only assignable term", 'press-permit-core-hints');

        case 'PP_AUTO_DEFAULT_CATEGORY' :
		return __("When saving a post, never auto-assign a category, even if it is the user's only assignable category", 'press-permit-core-hints');

        case 'PP_NO_AUTO_DEFAULT_POST_TAG' :
		return __("When saving a post, never auto-assign a tag, even if it is the user's only assignable tag", 'press-permit-core-hints');

        case 'PP_NO_AUTO_DEFAULT_CUSTOM_TAXOMY_NAME_HERE' :
		return __("When saving a post, never auto-assign a term (of specified taxonomy), even if it is the user's only assignable term", 'press-permit-core-hints');

        case 'PPCE_DISABLE_CATEGORY_RETENTION' :
		return __("When a limited user updates a post, strip out currently stored categories they don't have permission to assign", 'press-permit-core-hints');

        case 'PPCE_DISABLE_POST_TAG_RETENTION' :
		return __("When a limited user updates a post, strip out currently stored tags they don't have permission to assign", 'press-permit-core-hints');

        case 'PPCE_DISABLE_CUSTOM_TAXOMY_NAME_HERE_RETENTION' :
		return __("When a limited user updates a post, strip out currently stored terms (of specified taxonomy) they don't have permission to assign", 'press-permit-core-hints');

        case 'PP_NO_MODERATION' :
		return __("Don't define an 'Approved' status, even if Status Control module is active", 'press-permit-core-hints');

    
        // 'nav-menu-manage'
        case 'PP_SUPPRESS_APPEARANCE_LINK' :
		return __("If user has Nav Menu management capabilities but can't 'edit_theme_options', strip link out of wp-admin Appearance Menu instead of linking it to nav-menus", 'press-permit-core-hints');

        case 'PP_STRICT_MENU_CAPS' :
		return __("Don't credit implicit 'manage_nav_menus' capability to users who have 'edit_theme_options' or 'switch_themes' capability", 'press-permit-core-hints');

        case 'PPCE_RESTRICT_MENU_TOP_LEVEL' :
		return __("Prevent non-Administrators from adding new Nav Menu items to top level (add below existing editable items instead)", 'press-permit-core-hints');

        case 'PP_NAV_MENU_DEFAULT_TO_SUBITEM' :
		return __("For non-Administrators, new Nav Menu items default to being a child of first editable item ", 'press-permit-core-hints');

        case 'PP_LEGACY_MENU_SETTINGS_ACCESS' :
		return __("Don't require any additional capabilities for management of Nav Menu settings (normally require 'manage_menu_settings', 'edit_others_pages' or 'publish_pages') ", 'press-permit-core-hints');

        case 'PPCE_DISABLE_NAV_MENU_UPDATE_FILTERS' :
		return __("Eliminate extra filtering queries on Nav Menu update, even for non-Administrators", 'press-permit-core-hints');


        // 'media'
        case 'PP_BLOCK_UNATTACHED_UPLOADS' :
		return __("Don't allow non-Administrators to see others' unattached uploads, regardless of Permissions settings.  Their own unattached uploads are still accessible unless option 'own_attachments_always_editable' is set false", 'press-permit-core-hints');


        // 'admin'
        case 'PPCE_CAN_ASSIGN_OWN_ROLE' :
		return __("Limited User Editors can assign their own role", 'press-permit-core-hints');

        case 'PP_AUTHOR_POST_META' :
		return __("Post Meta fields to copy when using 'Add Author Page' dropdown on Users screen", 'press-permit-core-hints');

        default:
        }

        return apply_filters('presspermit_get_constant_descript', '', $constant);
    }

    static function setCapabilityDescriptions($pp_caps) {
        $pp_caps['pp_manage_settings'] = __('Modify these Permissions settings', 'press-permit-core-hints');
        $pp_caps['pp_unfiltered'] = __('PublishPress Permissions does not apply any Supplemental Roles or Specific Permissions to limit or expand viewing or editing access', 'press-permit-core-hints');
        $pp_caps['pp_administer_content'] = __('PublishPress Permissions implicitly grants capabilities for all post types and statuses, but does not apply Specific Permissions', 'press-permit-core-hints');
        $pp_caps['pp_create_groups'] = __('Can create Permission Groups', 'press-permit-core-hints');
        $pp_caps['pp_edit_groups'] = __('Can edit all Permission Groups (barring Specific Permissions)', 'press-permit-core-hints');
        $pp_caps['pp_delete_groups'] = __('Can delete Permission Groups', 'press-permit-core-hints');
        $pp_caps['pp_manage_members'] = __('If group editing is allowed, can also modify group membership', 'press-permit-core-hints');
        $pp_caps['pp_assign_roles'] = __('Assign Supplemental Roles or Specific Permissions. Other capabilities may also be required.', 'press-permit-core-hints');
        $pp_caps['pp_set_read_exceptions'] = __('Set Read Permissions for specific posts on Edit Post/Term screen (for non-Administrators lacking edit_users capability; may be disabled by Permissions Settings)', 'press-permit-core-hints');
        
        if (class_exists('Fork', false)) {
            $pp_caps['pp_set_fork_exceptions'] = __('Set Forking Permissions on Edit Post/Term screen (where applicable)', 'press-permit-core-hints');
        }

        if (defined('REVISIONARY_VERSION')) {
            $pp_caps['pp_set_revise_exceptions'] = __('Set Revision Permissions on Edit Post/Term screen (where applicable)', 'press-permit-core-hints');
        }

        $pp_caps['pp_set_edit_exceptions'] =            __('Set Editing Permissions on Edit Post/Term screen (where applicable)', 'press-permit-core-hints');
        $pp_caps['pp_set_associate_exceptions'] =       __('Set Association (Parent) Permissions on Edit Post screen (where applicable)', 'press-permit-core-hints');
        $pp_caps['pp_set_term_assign_exceptions'] =     __('Set Term Assignment Permissions on Edit Term screen (in relation to an editable post type)', 'press-permit-core-hints');
        $pp_caps['pp_set_term_manage_exceptions'] =     __('Set Term Management Permissions on Edit Term screen', 'press-permit-core-hints');
        $pp_caps['pp_set_term_associate_exceptions'] =  __('Set Term Association (Parent) Permissions on Edit Term screen', 'press-permit-core-hints');

        $pp_caps['edit_own_attachments'] =          __('Edit own file uploads, even if they become attached to an uneditable post', 'press-permit-core-hints');
        $pp_caps['list_others_unattached_files'] =  __('See other user&apos;s unattached file uploads in Media Library', 'press-permit-core-hints');
        $pp_caps['pp_associate_any_page'] =         __('Disregard association permissions (for all hierarchical post types)', 'press-permit-core-hints');

        $pp_caps['pp_list_all_files'] =     __('Do not alter the Media Library listing provided by WordPress', 'press-permit-core-hints');
        $pp_caps['list_posts'] =            __('On the Posts screen, satisfy a missing edit_posts capability by listing uneditable drafts', 'press-permit-core-hints');
        $pp_caps['list_others_posts'] =     __("On the Posts screen, satisfy a missing edit_others_posts capability by listing other user's uneditable posts", 'press-permit-core-hints');
        $pp_caps['list_private_pages'] =    __('On the Pages screen, satisfy a missing edit_private_pages capability by listing uneditable private pages', 'press-permit-core-hints');
        $pp_caps['pp_force_quick_edit'] =   __('Make Quick Edit and Bulk Edit available to non-Administrators even though some inappropriate selections may be possible', 'press-permit-core-hints');

        if (!defined('PRESSPERMIT_PRO_VERSION') && !presspermit()->moduleActive('status-control') && !presspermit()->keyActive()) {
            $pp_caps = array_merge(
                $pp_caps,
                [
                    'pp_define_post_status' => __('(Permissions Pro capability)', 'press-permit-core-hints'),
                    'pp_define_moderation' => __('(Permissions Pro capability)', 'press-permit-core-hints'),
                    'pp_define_privacy' => __('(Permissions Pro capability)', 'press-permit-core-hints'),
                    'set_posts_status' => __('(Permissions Pro capability)', 'press-permit-core-hints'),
                    'pp_moderate_any' => __('(Permissions Pro capability)', 'press-permit-core-hints'),
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
                if (true === $hint_text) {
                    $hint_text = self::getStr($option_name);
                }

                if ($hint_text) {
                    $hint_class = 'pp-subtext';
                    $hint_class .= (!empty($args['hint_class'])) ? ' ' . $args['hint_class'] : '';

                    echo "<div class='$hint_class'>" . $hint_text . "</div>";
                }
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
