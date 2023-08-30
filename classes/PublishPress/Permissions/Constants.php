<?php

namespace PublishPress\Permissions;

class Constants 
{
    public $constants = [];
    public $constant_types = [];
    public $constants_by_type = [];

    public function __construct() {
        $this->loadConstants();
        $this->loadConstantTypes();
    }

private function loadConstants() {
$type = 'filtering-switches';
$consts = [
    'PP_RESTRICTION_PRIORITY' => esc_html__("Specific Permissions: restrictions ('Blocked') take priority over additions ('Enabled')", 'press-permit-core-hints'),
    'PP_GROUP_RESTRICTIONS' => esc_html__("Specific Permissions: restrictions ('Blocked') can be applied to custom-defined groups", 'press-permit-core-hints'),
    'PP_ALL_ANON_ROLES' => esc_html__("Supplemental roles assignment available for {All} and {Anonymous} metagroups", 'press-permit-core-hints'),
    'PP_ALL_ANON_FULL_EXCEPTIONS' => esc_html__("Allow the {All} and {Anonymous} metagroups to be granted specific reading permissions for private content", 'press-permit-core-hints'),
    'PP_EDIT_EXCEPTIONS_ALLOW_DELETION' => esc_html__("PRO: Users who have specific editing permissions for a post or attachment can also delete it", 'press-permit-core-hints'),
    'PP_EDIT_EXCEPTIONS_ALLOW_ATTACHMENT_DELETION' => esc_html__("PRO: Users who have custom editing permissions for an attachment can also delete it", 'press-permit-core-hints'),
    'PP_ALLOW_UNFILTERED_FRONT' => esc_html__("Disable front end filtering if logged user is a content administrator (normally filter to force inclusion of readable private posts in get_pages() listing, post counts, etc.", 'press-permit-core-hints'),
    'PP_UNFILTERED_FRONT' => esc_html__("Disable front end filtering for all users (subject to limitation by PP_UNFILTERED_FRONT_TYPES)", 'press-permit-core-hints'),
    'PP_UNFILTERED_FRONT_TYPES' => esc_html__("Comma-separated list of post types to limit the effect of PP_UNFILTERED_FRONT and apply_filters( 'presspermit_skip_cap_filtering' )", 'press-permit-core-hints'),
    'PP_NO_ADDITIONAL_ACCESS' => esc_html__("Specific Permissions: additions ('Enabled') are not applied, cannot be assigned", 'press-permit-core-hints'),
    'PP_POST_NO_EXCEPTIONS' => esc_html__("Don't assign or apply specific permissions for the 'post' type", 'press-permit-core-hints'),
    'PP_PAGE_NO_EXCEPTIONS' => esc_html__("Don't assign or apply specific permissions for the 'page' type", 'press-permit-core-hints'),
    'PP_ATTACHMENT_NO_EXCEPTIONS' => esc_html__("Don't assign or apply specific permissions for the 'media' type", 'press-permit-core-hints'),
    'PP_MY_CUSTOM_TYPE_NO_EXCEPTIONS' => esc_html__("Don't assign or apply specific permissions for the specified custom post type", 'press-permit-core-hints'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'front-end';
$consts = [
    'PP_FUTURE_POSTS_BLOGROLL' => esc_html__("Include scheduled posts in the posts query if user can edit them", 'press-permit-core-hints'),
    'PP_UNFILTERED_TERM_COUNTS' => esc_html__("Don't filter term post counts in get_terms() call", 'press-permit-core-hints'),
    'PP_DISABLE_NAV_MENU_FILTER' => esc_html__("Leave unreadable posts on WP Navigation Menus", 'press-permit-core-hints'),
    'PP_NAV_MENU_SHOW_EMPTY_TERMS' => esc_html__("Leave terms with no readable posts on WP Navigation Menus", 'press-permit-core-hints'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'get-pages';
$consts = [
    'PP_GET_PAGES_PRIORITY' => esc_html__("Filter priority for 'get_pages' filter (default: 1)", 'press-permit-core-hints'),
    'PP_SUPPRESS_PRIVATE_PAGES' => esc_html__("Don't include readable private pages in the Pages widget or other wp_list_pages() / get_pages() results    ", 'press-permit-core-hints'),
    'PPC_FORCE_PAGE_REMAP' => esc_html__("If some pages have been suppressed from get_pages() results, change child pages' corresponding post_parent values to a visible ancestor", 'press-permit-core-hints'),
    'PPC_NO_PAGE_REMAP' => esc_html__("Never modify the post_parent value in the get_pages() result set, even if some pages have been suppressed", 'press-permit-core-hints'),
    'PP_GET_PAGES_LEAN' => esc_html__("For performance, change the get_pages() database query to return only a subset of fields, excluding post_content", 'press-permit-core-hints'),
    'PP_TEASER_HIDE_PAGE_LISTING' => esc_html__("PRO: Don't apply content teaser to get_pages() results (leave unreadable posts hidden)", 'press-permit-core-hints'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'get-terms';
$consts = [
    'PPC_FORCE_TERM_REMAP' => esc_html__("If some terms have been suppressed from get_terms() results, change child terms' corresponding parent values to a visible ancestor", 'press-permit-core-hints'),
    'PPC_NO_TERM_REMAP' => esc_html__("Never modify the parent value in the get_terms() result set, even if some terms have been suppressed", 'press-permit-core-hints'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'media';
$consts = [
    'PP_MEDIA_LIB_UNFILTERED' => esc_html__("Leave Media Library with normal access criteria based on user's role capabilities ", 'press-permit-core-hints'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'admin';
$consts = [
    'PP_USERS_UI_GROUP_FILTER_LINK' => esc_html__("On Users listing, Permission groups in custom column are list filter links instead of group edit links", 'press-permit-core-hints'),
    'PP_ADMIN_READONLY_LISTABLE' => esc_html__("Unlock Permissions > Settings > Core > Admin Back End > 'Hide non-editable posts'", 'press-permit-core-hints'),
    'PP_UPLOADS_FORCE_FILTERING' => esc_html__("Within the async-upload.php script, filtering author's retrieval of the attachment they just uploaded", 'press-permit-core-hints'),
    'PP_NO_COMMENT_FILTERING' => esc_html__("Don't filter comment display or moderation within wp-admin", 'press-permit-core-hints'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'permissions-admin';
$consts = [
    'PP_DISABLE_BULK_ROLES' => "",
    'PP_FORCE_EXCEPTION_OVERWRITE' => esc_html__("If propagating permissions are assigned to a page branch, overwrite any explicitly assigned permissions in sub-pages", 'press-permit-core-hints'),
    'PP_EXCEPTIONS_MAX_INSERT_ROWS' => esc_html__("Max number of specific permissions to insert in a single database query (default 1000)", 'press-permit-core-hints'),
    'PP_DISABLE_MENU_TWEAK' => esc_html__("Don't tweak the admin menu indexes to position Permissions menu under Users", 'press-permit-core-hints'),
    'PP_FORCE_USERS_MENU' => esc_html__("Don't add a Permissions menu. Instead, add menu items to the Users and Settings menus.", 'press-permit-core-hints'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'permission-groups-ui';
$consts = [
    'PP_GROUPS_CAPTION' => esc_html__("Customize 'Permission Groups' caption", 'press-permit-core-hints'),
    'GROUPS_CAPTION_RS' => esc_html__("Customize 'Permission Groups' caption on user profile", 'press-permit-core-hints'),
    'PRESSPERMIT_ADD_USER_SINGLE_GROUP_SELECT' => esc_html__("Only one group is selectable on Add User screen", 'press-permit-core-hints'),
    'PRESSPERMIT_EDIT_USER_SINGLE_GROUP_SELECT' => esc_html__("Only one group is selectable on Edit User screen", 'press-permit-core-hints'),
    'PP_GROUPS_HINT' => esc_html__("Customize description under 'Permission Groups' caption ", 'press-permit-core-hints'),
    'PP_ITEM_MENU_PER_PAGE' => esc_html__("Max number of non-hierarchical posts / terms to display at one time (per page)", 'press-permit-core-hints'),
    'PP_ITEM_MENU_HIERARCHICAL_PER_PAGE' => esc_html__("Max number of hierarchical posts / terms to display at one time (per page)", 'press-permit-core-hints'),
    'PP_ITEM_MENU_FORCE_DISPLAY_DEPTH' => esc_html__("Disable auto-determination of how many levels of page tree to make visble by default. Instead, use specified value.", 'press-permit-core-hints'),
    'PP_ITEM_MENU_DEFAULT_MAX_VISIBLE' => esc_html__("Target number of visible pages/terms, used for auto-determination of number of visible levels", 'press-permit-core-hints'),
    'PP_ITEM_MENU_SEARCH_CONTENT' => esc_html__("Make search function on the post selection metabox look at post content", 'press-permit-core-hints'),
    'PP_ITEM_MENU_SEARCH_EXCERPT' => esc_html__("Make search function on the post selection metabox look at post excerpt", 'press-permit-core-hints'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'user-selection';
$consts = [
    'PP_USER_LASTNAME_SEARCH' => esc_html__("Search by last name instead of display name", 'press-permit-core-hints'),
    'PP_USER_SEARCH_FIELD' => esc_html__("User field to search by default", 'press-permit-core-hints'),
    'PP_USER_SEARCH_META_FIELDS' => esc_html__("User meta fields selectable for search (comma-separated)", 'press-permit-core-hints'),
    'PP_USER_SEARCH_NUMERIC_FIELDS' => esc_html__("User meta fields which should be treated as numeric (comma-separated)", 'press-permit-core-hints'),
    'PP_USER_SEARCH_BOOLEAN_FIELDS' => esc_html__("User meta fields which should be treated as boolean (comma-separated)", 'press-permit-core-hints'),
    'PP_USER_RESULTS_DISPLAY_NAME' => esc_html__("Use display name for search results instead of user_login", 'press-permit-core-hints'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'user-sync';
$consts = [
    'PP_SKIP_USER_SYNC' => esc_html__("Don't auto-assign role metagroups for all users. Instead, assign per-user at first login.", 'press-permit-core-hints'),
    'PP_AUTODELETE_ROLE_METAGROUPS' => esc_html__("When synchronizing role metagroups to currently defined WP roles, don't delete groups for previously defined WP roles.", 'press-permit-core-hints'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'force-pp-settings';
$consts = [
    'PP_FORCE_DYNAMIC_ROLES' => esc_html__("Force detection of WP user roles which are appended dynamically but not stored to the WP database.", 'press-permit-core-hints'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'perf';
$consts = [
    'PP_NO_FRONTEND_ADMIN' => esc_html__("To save memory on front end access, don't register any filters related to content editing", 'press-permit-core-hints'),
    'PP_NO_ATTACHMENT_COMMENTS' => esc_html__("Attached media do not have any comments, so don't append clauses to comment queries for them", 'press-permit-core-hints'),
    'PP_LEAN_PAGE_LISTING' => esc_html__("Reduce overhead of pages query (in get_pages() and wp-admin) by defaulting fields to a set list that does not include post_content ", 'press-permit-core-hints'),
    'PP_LEAN_POST_LISTING' => esc_html__("Reduce overhead of wp-admin posts query by defaulting fields to a set list that does not include post_content ", 'press-permit-core-hints'),
    'PP_LEAN_MEDIA_LISTING' => esc_html__("Reduce overhead of wp-admin Media query by defaulting fields to a set list that does not include post_content ", 'press-permit-core-hints'),
    'PP_LEAN_MY_CUSTOM_TYPE_LISTING' => esc_html__("Reduce overhead of wp-admin query for specified custom post type by defaulting fields to a set list that does not include post_content ", 'press-permit-core-hints'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'wp-compat';
$consts = [
    'PP_UNFILTERED_PAGE_URI' => esc_html__("Don't restore pre-4.4 behavior of not requiring 'publish' status for inclusion in page uri hierarchy", 'press-permit-core-hints'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];


if (defined('PUBLISHPRESS_REVISIONS_VERSION') || defined("REVISIONARY_VERSION")) {
    $type = 'third-party';
    $consts = [
        'SCOPER_DEFAULT_MONITOR_GROUPS' => "",
        'PP_DEFAULT_MONITOR_GROUPS' => "",
    ];
    foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];
}

$type = 'support';
$consts = [
    'PPI_LEGACY_UPLOAD' => "",
    'PPI_ERROR_LOG_UPLOAD_LIMIT' => "",
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type, 'suppress_display' => true];

$type = 'debug-dev';
$consts = [
    'PRESSPERMIT_DEBUG' => "",
    'PRESSPERMIT_DEBUG_LOGFILE' => "",
    'PRESSPERMIT_MEMORY_LOG' => "",
    'AGP_NO_USAGE_MSG' => "",
    'PRESSPERMIT_DEBUG_ACTIVATE_KEY' => "",
    'PRESSPERMIT_DEBUG_DEACTIVATE_KEY' => "",
    'PRESSPERMIT_DEBUG_UPDATE_CHECK_PPC' => "",
    'PRESSPERMIT_DEBUG_EXT_INFO' => "",
    'PRESSPERMIT_DEBUG_CHANGELOG_PPC' => "",
    'PRESSPERMIT_DEBUG_CONFIG_CHECK' => "",
    'PRESSPERMIT_DEBUG_CONFIG_UPLOAD' => "",
    'PP_FORCE_PPCOM_INFO' => "",
    'PP_DISABLE_CAP_CACHE' => "",
    'PP_FILTER_JSON_REST' => "",
    'PP_DISABLE_UNFILTERED_TYPES_CLAUSE' => esc_html__("Development use only (suppresses post_status = 'publish' clause for unfiltered post types with anonymous user)", 'press-permit-core-hints'),
    'PP_RETAIN_PUBLISH_FILTER' => esc_html__("Development use only (on front end, do not replace 'post_status = 'publish'' clause with filtered equivalent)", 'press-permit-core-hints'),
    'PP_GET_TERMS_SHORTCUT' => "",
    'PP_LEGACY_HTTP_REDIRECT' => "",
    'PP_AGENTS_CAPTION_LIMIT' => "",
    'PP_AGENTS_EMSIZE_THRESHOLD' => "",
    'PP_UI_EMS_PER_CHARACTER' => "",
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type, 'suppress_display' => true];

$this->constants = apply_filters('presspermit_constants', $this->constants);

} // end function

function loadConstantTypes() {
    foreach ($this->constants as $name => $const) {
        if (empty($const->suppress_display)) {
            if (!isset($this->constant_types[$const->type])) {
                $this->constant_types[$const->type] = ucwords(str_replace('-', ' ', $const->type));

                foreach (['-' => ' ', 'Pp' => 'PP', 'Wp' => 'WP', 'Ui' => 'UI'] as $find => $repl)
                    $this->constant_types[$const->type] = ucwords(str_replace($find, $repl, $this->constant_types[$const->type]));
            }

            if (!isset($this->constants_by_type[$const->type])) $this->constants_by_type[$const->type] = [];

            $this->constants_by_type[$const->type][] = $name;
        }
    }

    $this->constant_types = apply_filters('presspermit_constant_types', $this->constant_types);
}

} // end class