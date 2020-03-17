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
    'PP_RESTRICTION_PRIORITY' => __("Specific Permissions: restrictions ('Blocked') take priority over additions ('Enabled')", 'press-permit-core'),
    'PP_GROUP_RESTRICTIONS' => __("Specific Permissions: restrictions ('Blocked') can be applied to custom-defined groups", 'press-permit-core'),
    'PP_ALL_ANON_ROLES' => __("Supplemental roles assignment available for {All} and {Anonymous} metagroups", 'press-permit-core'),
    'PP_ALL_ANON_FULL_EXCEPTIONS' => __("Allow the {All} and {Anonymous} metagroups to be granted specific reading permissions for private content", 'press-permit-core'),
    'PP_EDIT_EXCEPTIONS_ALLOW_DELETION' => __("PRO: Users who have specific editing permissions for a post or attachment can also delete it", 'press-permit-core'),
    'PP_EDIT_EXCEPTIONS_ALLOW_ATTACHMENT_DELETION' => __("PRO: Users who have custom editing permissions for an attachment can also delete it", 'press-permit-core'),
    'PP_ALLOW_UNFILTERED_FRONT' => __("Disable front end filtering if logged user is a content administrator (normally filter to force inclusion of readable private posts in get_pages() listing, post counts, etc.", 'press-permit-core'),
    'PP_UNFILTERED_FRONT' => __("Disable front end filtering for all users (subject to limitation by PP_UNFILTERED_FRONT_TYPES)", 'press-permit-core'),
    'PP_UNFILTERED_FRONT_TYPES' => __("Comma-separated list of post types to limit the effect of PP_UNFILTERED_FRONT and apply_filters( 'presspermit_skip_cap_filtering' )", 'press-permit-core'),
    'PP_NO_ADDITIONAL_ACCESS' => __("Specific Permissions: additions ('Enabled') are not applied, cannot be assigned", 'press-permit-core'),
    'PP_POST_NO_EXCEPTIONS' => __("Don't assign or apply specific permissions for the 'post' type", 'press-permit-core'),
    'PP_PAGE_NO_EXCEPTIONS' => __("Don't assign or apply specific permissions for the 'page' type", 'press-permit-core'),
    'PP_MEDIA_NO_EXCEPTIONS' => __("Don't assign or apply specific permissions for the 'media' type", 'press-permit-core'),
    'PP_MY_CUSTOM_TYPE_NO_EXCEPTIONS' => __("Don't assign or apply specific permissions for the specified custom post type", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'front-end';
$consts = [
    'PP_FUTURE_POSTS_BLOGROLL' => __("Include scheduled posts in the posts query if user can edit them", 'press-permit-core'),
    'PP_UNFILTERED_TERM_COUNTS' => __("Don't filter term post counts in get_terms() call", 'press-permit-core'),
    'PP_DISABLE_NAV_MENU_FILTER' => __("Leave unreadable posts on WP Navigation Menus", 'press-permit-core'),
    'PP_NAV_MENU_SHOW_EMPTY_TERMS' => __("Leave terms with no readable posts on WP Navigation Menus", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'get-pages';
$consts = [
    'PP_GET_PAGES_PRIORITY' => __("Filter priority for 'get_pages' filter (default: 1)", 'press-permit-core'),
    'PP_SUPPRESS_PRIVATE_PAGES' => __("Don't include readable private pages in the Pages widget or other wp_list_pages() / get_pages() results    ", 'press-permit-core'),
    'PPC_FORCE_PAGE_REMAP' => __("If some pages have been suppressed from get_pages() results, change child pages' corresponding post_parent values to a visible ancestor", 'press-permit-core'),
    'PPC_NO_PAGE_REMAP' => __("Never modify the post_parent value in the get_pages() result set, even if some pages have been suppressed", 'press-permit-core'),
    'PP_GET_PAGES_LEAN' => __("For performance, change the get_pages() database query to return only a subset of fields, excluding post_content", 'press-permit-core'),
    'PP_TEASER_HIDE_PAGE_LISTING' => __("PRO: Don't apply content teaser to get_pages() results (leave unreadable posts hidden)", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'get-terms';
$consts = [
    'PPC_FORCE_TERM_REMAP' => __("If some terms have been suppressed from get_terms() results, change child terms' corresponding parent values to a visible ancestor", 'press-permit-core'),
    'PPC_NO_TERM_REMAP' => __("Never modify the parent value in the get_terms() result set, even if some terms have been suppressed", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'media';
$consts = [
    'PP_MEDIA_LIB_UNFILTERED' => __("Leave Media Library with normal access criteria based on user's role capabilities ", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'admin';
$consts = [
    'PP_USERS_UI_GROUP_FILTER_LINK' => __("On Users listing, Permission groups in custom column are list filter links instead of group edit links", 'press-permit-core'),
    'PP_ADMIN_READONLY_LISTABLE' => __("Unlock Permissions > Settings > Core > Admin Back End > 'Hide non-editable posts'", 'press-permit-core'),
    'PP_UPLOADS_FORCE_FILTERING' => __("Within the async-upload.php script, filtering author's retrieval of the attachment they just uploaded", 'press-permit-core'),
    'PP_NO_COMMENT_FILTERING' => __("Don't filter comment display or moderation within wp-admin", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'permissions-admin';
$consts = [
    'PP_DISABLE_BULK_ROLES' => "",
    'PP_FORCE_EXCEPTION_OVERWRITE' => __("If propagating permissions are assigned to a page branch, overwrite any explicitly assigned permissions in sub-pages", 'press-permit-core'),
    'PP_EXCEPTIONS_MAX_INSERT_ROWS' => __("Max number of specific permissions to insert in a single database query (default 1000)", 'press-permit-core'),
    'PP_DISABLE_MENU_TWEAK' => __("Don't tweak the admin menu indexes to position Permissions menu under Users", 'press-permit-core'),
    'PP_FORCE_USERS_MENU' => __("Don't add a Permissions menu. Instead, add menu items to the Users and Settings menus.", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'permission-groups-ui';
$consts = [
    'PP_GROUPS_CAPTION' => __("Customize 'Permission Groups' caption", 'press-permit-core'),
    'GROUPS_CAPTION_RS' => __("Customize 'Permission Groups' caption on user profile", 'press-permit-core'),
    'PP_GROUPS_HINT' => __("Customize description under 'Permission Groups' caption ", 'press-permit-core'),
    'PP_ITEM_MENU_PER_PAGE' => __("Max number of non-hierarchical posts / terms to display at one time (per page)", 'press-permit-core'),
    'PP_ITEM_MENU_HIERARCHICAL_PER_PAGE' => __("Max number of hierarchical posts / terms to display at one time (per page)", 'press-permit-core'),
    'PP_ITEM_MENU_FORCE_DISPLAY_DEPTH' => __("Disable auto-determination of how many levels of page tree to make visble by default. Instead, use specified value.", 'press-permit-core'),
    'PP_ITEM_MENU_DEFAULT_MAX_VISIBLE' => __("Target number of visible pages/terms, used for auto-determination of number of visible levels", 'press-permit-core'),
    'PP_ITEM_MENU_SEARCH_CONTENT' => __("Make search function on the post selection metabox look at post content", 'press-permit-core'),
    'PP_ITEM_MENU_SEARCH_EXCERPT' => __("Make search function on the post selection metabox look at post excerpt", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'user-selection';
$consts = [
    'PP_USER_LASTNAME_SEARCH' => __("Search by last name instead of display name", 'press-permit-core'),
    'PP_USER_SEARCH_FIELD' => __("User field to search by default", 'press-permit-core'),
    'PP_USER_SEARCH_META_FIELDS' => __("User meta fields selectable for search (comma-separated)", 'press-permit-core'),
    'PP_USER_SEARCH_NUMERIC_FIELDS' => __("User meta fields which should be treated as numeric (comma-separated)", 'press-permit-core'),
    'PP_USER_SEARCH_BOOLEAN_FIELDS' => __("User meta fields which should be treated as boolean (comma-separated)", 'press-permit-core'),
    'PP_USER_RESULTS_DISPLAY_NAME' => __("Use display name for search results instead of user_login", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'user-sync';
$consts = [
    'PP_SKIP_USER_SYNC' => __("Don't auto-assign role metagroups for all users. Instead, assign per-user at first login.", 'press-permit-core'),
    'PP_AUTODELETE_ROLE_METAGROUPS' => __("When synchronizing role metagroups to currently defined WP roles, don't delete groups for previously defined WP roles.", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'force-pp-settings';
$consts = [
    'PP_FORCE_DYNAMIC_ROLES' => __("Force detection of WP user roles which are appended dynamically but not stored to the WP database.", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'perf';
$consts = [
    'PP_NO_FRONTEND_ADMIN' => __("To save memory on front end access, don't register any filters related to content editing", 'press-permit-core'),
    'PP_NO_ATTACHMENT_COMMENTS' => __("Attached media do not have any comments, so don't append clauses to comment queries for them", 'press-permit-core'),
    'PP_LEAN_PAGE_LISTING' => __("Reduce overhead of pages query (in get_pages() and wp-admin) by defaulting fields to a set list that does not include post_content ", 'press-permit-core'),
    'PP_LEAN_POST_LISTING' => __("Reduce overhead of wp-admin posts query by defaulting fields to a set list that does not include post_content ", 'press-permit-core'),
    'PP_LEAN_MEDIA_LISTING' => __("Reduce overhead of wp-admin Media query by defaulting fields to a set list that does not include post_content ", 'press-permit-core'),
    'PP_LEAN_MY_CUSTOM_TYPE_LISTING' => __("Reduce overhead of wp-admin query for specified custom post type by defaulting fields to a set list that does not include post_content ", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];

$type = 'wp-compat';
$consts = [
    'PP_UNFILTERED_PAGE_URI' => __("Don't restore pre-4.4 behavior of not requiring 'publish' status for inclusion in page uri hierarchy", 'press-permit-core'),
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type];


if (defined("REVISIONARY_VERSION")) {
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
    'PP_DISABLE_UNFILTERED_TYPES_CLAUSE' => __("Development use only (suppresses post_status = 'publish' clause for unfiltered post types with anonymous user)", 'press-permit-core'),
    'PP_RETAIN_PUBLISH_FILTER' => __("Development use only (on front end, do not replace 'post_status = 'publish'' clause with filtered equivalent)", 'press-permit-core'),
    'PP_GET_TERMS_SHORTCUT' => "",
    'PP_LEGACY_HTTP_REDIRECT' => "",
    'PP_AGENTS_CAPTION_LIMIT' => "",
    'PP_AGENTS_EMSIZE_THRESHOLD' => "",
    'PP_UI_EMS_PER_CHARACTER' => "",
];
foreach ($consts as $k => $v) $this->constants[$k] = (object)['descript' => $v, 'type' => $type, 'suppress_display' => true];

/*  // Save for possible use with future constants
$type = 'status';
$consts = [
'PRESSPERMIT_MULTISITE' => "",
];
foreach( $consts as $k => $v ) $this->constants[$k] = (object) ['descript' => $v, 'type' => $type, 'suppress_display' => true];
*/

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