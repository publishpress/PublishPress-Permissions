<?php
namespace PublishPress\Permissions;

class CollabHooks
{
    function __construct() {
        // Divi Page Builder
        if (!empty($_REQUEST['action']) && ('editpost' == $_REQUEST['action']) && !empty($_REQUEST['et_pb_use_builder']) && !empty($_REQUEST['auto_draft'])) {
            return;
        }
        
        // Divi Page Builder  @todo: test whether these can be implemented with 'presspermit_unfiltered_ajax' filter in PostFilters::fltPostsClauses instead
        if (strpos($_SERVER['REQUEST_URI'], 'admin-ajax.php') 
        && in_array(
            $_REQUEST['action'], 
            apply_filters('presspermit_unfiltered_ajax_actions',
            ['et_fb_ajax_drop_autosave',
            'et_builder_resolve_post_content',
            'et_fb_get_shortcode_from_fb_object',
            'et_builder_library_get_layout',
            'et_builder_library_get_layouts_data',
            'et_fb_update_builder_assets',
            ]
            )
        )
        ) {
            return;
        }

        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Capabilities.php');

        add_action('init', [$this, 'init']);

        add_filter('presspermit_meta_caps', [$this, 'fltMetaCaps']);
        add_filter('presspermit_exclude_arbitrary_caps', [$this, 'fltExcludeArbitraryCaps']);

        add_filter('presspermit_default_options', [$this, 'fltDefaultOptions']);
        add_filter('presspermit_default_advanced_options', [$this, 'fltDefaultAdvancedOptions']);

        if ( is_admin() ) {
            add_action('presspermit_init', [$this, 'actNonAdministratorEditingFilters']);  // fires after user load
        } else {
            // Also filter for Administrator, to include private and unpublished pages in Page Parent dropdown
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/RESTInit.php');
            new Collab\RESTInit();

            add_filter('rest_pre_dispatch', [$this, 'fltRestAddEditingFilters'], 99, 3);  // also add the filters on REST request
        }

        if (class_exists('Fork', false) && !defined('PP_DISABLE_FORKING_SUPPORT')) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Compat/PostForking.php'); // load this early to block rolecap addition if necessary
            new Collab\Compat\PostForking();
        }

        if (defined('WPB_VC_VERSION')) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Compat/BakeryPageBuilder.php');
            new Collab\Compat\BakeryPageBuilder();
        }

        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Compat/MultipleAuthors.php'); // load this early to block rolecap addition if necessary
        new Collab\Compat\MultipleAuthors();

        // Filtering of terms selection:
        add_filter('pre_post_tax_input', [$this, 'fltTaxInput'], 50, 1);
        add_filter('pre_post_category', [$this, 'fltPrePostTerms'], 50, 1);
        add_filter('presspermit_pre_object_terms', [$this, 'fltPrePostTerms'], 50, 2);
    }

    function init()
    {
        add_action('presspermit_pre_init', [$this, 'actOnInit']);
        add_action('presspermit_options', [$this, 'actAdjustOptions']);
        add_filter('presspermit_role_caps', [$this, 'fltRoleCaps'], 10, 2);
        add_filter('presspermit_pattern_roles', [$this, 'fltPatternRoles']);
        add_action('presspermit_roles_defined', [$this, 'actSetRoleUsage']);

        add_action('presspermit_maintenance_triggers', [$this, 'actLoadFilters']); // fires early if is_admin() - at bottom of AdminUI constructor
        add_action('presspermit_post_filters', [$this, 'actLoadPostFilters']);
        add_action('presspermit_cap_filters', [$this, 'actLoadCapFilters']);
        add_action('presspermit_page_filters', [$this, 'actLoadWorkaroundFilters']);

        //add_filter('presspermit_get_terms_is_term_admin', [$this, 'flt_get_terms_is_term_admin'], 10, 2);  // @todo: should this be applied?

        // if PPS is active, hook into its visibility forcing mechanism and UI (applied by PPS for specific pages)
        add_filter('presspermit_getItemCondition', [$this, 'fltForceDefaultVisibility'], 10, 4);
        add_filter('presspermit_read_own_attachments', [$this, 'fltReadOwnAttachments'], 10, 2);
        add_filter('presspermit_ajax_edit_actions', [$this, 'fltAjaxEditActions']);

        add_action('attachment_updated', [$this, 'actAttachmentEnsureParentStorage'], 10, 3);

        add_action('pre_get_posts', [$this, 'actPreventTrashSuffixing']);
    }

    function fltDefaultOptions($def)
    {
        $new = [
            'lock_top_pages' => 0,
            'admin_others_attached_files' => 0,
            'admin_others_attached_to_readable' => 0,
            'admin_others_unattached_files' => 0,
            'edit_others_attached_files' => 0,
            'own_attachments_always_editable' => 1,
            'admin_nav_menu_filter_items' => 1,
            'admin_nav_menu_partial_editing' => 0,
            'admin_nav_menu_lock_custom' => 1,
            'limit_user_edit_by_level' => 1,
            'add_author_pages' => 1,
            'publish_author_pages' => 0,
            'editor_hide_html_ids' => '',
            'editor_ids_sitewide_requirement' => 0,
            /*'prevent_default_forking_caps' => 0,*/
            'fork_published_only' => 0,
            'fork_require_edit_others' => 0,
            'force_taxonomy_cols' => 0,
            'default_privacy' => [],
            'force_default_privacy' => [],
            'page_parent_order' => '',
        ];

        return array_merge($def, $new);
    }

    function fltDefaultAdvancedOptions($def = [])
    {
        $new = [
            'role_usage' => [], // note: this stores user-defined pattern role and direct role enable
            'non_admins_set_edit_exceptions' => 0,
            'publish_exceptions' => defined('PP_PUBLISH_EXCEPTIONS') ? 1 : 0,  // this setting was previously controlled by define statement
        ];

        return array_merge($def, $new);
    }

    function actNonAdministratorEditingFilters()
    {
        if (!presspermit()->isUserUnfiltered()) {
            require_once(__DIR__ . '/CollabHooksAdminNonAdministrator.php');
            new CollabHooksAdminNonAdministrator();

            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/CapabilityFiltersAdmin.php');
            new Collab\CapabilityFiltersAdmin();
        }
    }

    function fltRestAddEditingFilters($rest_response, $rest_server, $request) 
    {
        if (Collab::isEditREST()) {
            $this->actNonAdministratorEditingFilters();
        }

        return $rest_response;
    }

    // Safeguard against improper filtering (to zero) of post_parent value. Forces mirroring of postmeta _thumbnail_id <> post_id relationship 
    function actAttachmentEnsureParentStorage($post_id, $post_after, $post_before) {
        if ($post_before->post_parent && !$post_after->post_parent) {
            if ($post_id == get_post_meta($post_before->post_parent, '_thumbnail_id', true)) {
                global $wpdb;
                $wpdb->update($wpdb->posts, ['post_parent' => $post_before->post_parent], ['ID' => $post_id]);
            }
        }
    }

    function actPreventTrashSuffixing($wp_query)
    {
        if (strpos($_SERVER['REQUEST_URI'], 'wp-admin/nav-menus.php') 
        && !empty($_POST) && !empty($_POST['action']) && ('update' == $_POST['action'])
        ) {
            $bt = debug_backtrace();

            foreach ($bt as $fcall) {
                if (!empty($fcall['function']) && 'wp_add_trashed_suffix_to_post_name_for_trashed_posts' == $fcall['function']) {
                    $wp_query->query_vars['suppress_filters'] = 1;
                    break;
                }
            }
        }
    }

    function fltMetaCaps($meta_caps)
    {
        // Divi Page Builder
		if (defined('ET_BUILDER_THEME')) {
			if (!empty($_REQUEST['action']) && ('edit' == $_REQUEST['action']) && !empty($_REQUEST['post'])) {
				if ($_post = get_post($_REQUEST['post'])) {
					global $current_user;
					if (in_array($_post->post_status, ['draft', 'auto-draft']) && ($_post->post_author == $current_user->ID) && !$_post->post_name) {
						return $meta_caps;
					}
				}
			}
		}

        return array_merge(
            $meta_caps, 
            [
                'edit_post' => 'edit', 
                'edit_page' => 'edit', 
                'delete_post' => 'delete', 
                'delete_page' => 'delete',
                'edit_post_meta' => 'edit',
                'delete_post_meta' => 'delete',
            ]
        );
    }

    function fltAjaxEditActions($actions)
    {
        if (!presspermit()->getOption('admin_others_attached_to_readable')) {
            $actions = array_merge($actions, ['query-attachments', 'mla-query-attachments']);
        }

        return $actions;
    }

    function fltReadOwnAttachments($read_own, $args = [])
    {
        if (!$read_own) {
            global $current_user;
            return presspermit()->getOption('own_attachments_always_editable') || !empty($current_user->allcaps['edit_own_attachments']);
        }

        return $read_own;
    }

    function fltForceDefaultVisibility($item_condition, $source_name, $attribute, $args = [])
    {
        // allow any existing page-specific settings to override default forcing
        if (('post' == $source_name) && ('force_visibility' == $attribute) && !$item_condition && isset($args['post_type'])) {
            if (empty($args['assign_for']) || ('item' == $args['assign_for'])) {
                if ($default_privacy = presspermit()->getTypeOption('default_privacy', $args['post_type'])) {

                    /*
                    // @todo: Force default privacy with Gutenberg
                    if ((defined('PRESSPERMIT_STATUSES_VERSION') && version_compare(PRESSPERMIT_STATUSES_VERSION, '2.7-beta', '<')) 
                    && PWP::is-BlockEditorActive($args['post_type'])) {
                        return $item_condition;
                    }
                    */

                    if ($force = presspermit()->getTypeOption('force_default_privacy', $args['post_type'])) {
                        // only apply if status is currently registered and PP-enabled for the post type
                        if (PWP::getPostStatuses(['name' => $default_privacy, 'post_type' => $args['post_type']])) {
                            if (!empty($args['return_meta']))
                                return (object)['force_status' => $default_privacy, 'force_basis' => 'default'];
                            else
                                return $default_privacy;
                        }
                    }
                }
            }
        }

        return $item_condition;
    }

    function actOnInit()
    {
        Collab\Capabilities::instance();

        // --- version check ---
        $ver = get_option('ppce_version');

        if ($ver && !empty($ver['version'])) {
            // These maintenance operations only apply when a previous version of PP was installed 
            if (version_compare(PRESSPERMIT_COLLAB_VERSION, $ver['version'], '!=')) {
                require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Updated.php');
                new Collab\Updated($ver['version']);
                update_option('ppce_version', ['version' => PRESSPERMIT_COLLAB_VERSION, 'db_version' => 0]);
            }
        } elseif (!$ver) {
            Collab::populateRoles();
            update_option('ppce_version', ['version' => PRESSPERMIT_COLLAB_VERSION, 'db_version' => 0]);
        }
        // --- end version check ---


        if (defined('XMLRPC_REQUEST')) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/XmlRpc.php');
            new Collab\XmlRpc();
        }

        if (false !== strpos($_SERVER['REQUEST_URI'], '/wp-json/wp/v2')) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/REST_Workarounds.php');
            new Collab\REST_Workarounds();
        }
    }

    function actAdjustOptions($options)
    {
        if (isset($options['presspermit_enabled_taxonomies']))
            $options['presspermit_enabled_taxonomies'] = array_merge(
                maybe_unserialize($options['presspermit_enabled_taxonomies']), 
                ['nav_menu' => '1']
            );

        if (!empty($options['presspermit_default_privacy'])) {
            $disabled_types = (class_exists('bbPress', false)) ? ['forum', 'topic', 'reply'] : [];
            if ($disabled_types = apply_filters('presspermit_disabled_default_privacy_types', $disabled_types)) {
                if ($_default_privacy = maybe_unserialize($options['presspermit_default_privacy']))
                    $options['presspermit_default_privacy'] = array_diff_key($_default_privacy, array_fill_keys($disabled_types, true));
            }
        }

        return $options;
    }

    function fltPatternRoles($roles)
    {
        return array_merge($roles, ['contributor' => (object)[], 'author' => (object)[], 'editor' => (object)[]]);
    }

    function actSetRoleUsage()
    {
        global $wp_roles;

        if (empty($wp_roles))
            return;

        $pp = presspermit();

        // don't apply custom Role Usage settings if advanced options are disabled
        $stored_usage = ($pp->getOption('advanced_options')) ? $pp->getOption('role_usage') : [];

        if ($stored_usage) {
            $enabled_pattern_roles = array_intersect((array)$stored_usage, ['pattern']);
            $enabled_direct_roles = array_intersect((array)$stored_usage, ['direct']);
            $no_usage_roles = array_intersect((array)$stored_usage, ['0', 0, false]);
        } else {
            $enabled_pattern_roles = $enabled_direct_roles = [];
        }

        $pp->role_defs->pattern_roles = apply_filters('presspermit_default_pattern_roles', $pp->role_defs->pattern_roles);

        if ($stored_usage) {  // if no role usage is stored, use default pattern roles
            $pp->role_defs->pattern_roles = array_diff_key($pp->role_defs->pattern_roles, $enabled_direct_roles, $no_usage_roles);

            $additional_pattern_roles = array_diff_key($enabled_pattern_roles, $pp->role_defs->pattern_roles);

            foreach (array_keys($additional_pattern_roles) as $role_name) {
                if (isset($wp_roles->role_names[$role_name]))
                    $pp->role_defs->pattern_roles[$role_name] = (object)[
                        'is_additional' => true, 
                        'labels' => (object)[
                            'name' => $wp_roles->role_names[$role_name], 
                            'singular_name' => $wp_roles->role_names[$role_name]
                            ]
                        ];
            }

            // Direct Role Usage
            $use_wp_roles = array_diff_key($wp_roles->role_names, ['administrator' => true]);
            $use_wp_roles = array_intersect_key($use_wp_roles, $enabled_direct_roles, $wp_roles->role_names);

            foreach (array_keys($use_wp_roles) as $role_name) {
                $labels = (isset($pp->role_defs->pattern_roles[$role_name])) 
                ? $pp->role_defs->pattern_roles[$role_name]->labels 
                : (object)['name' => $wp_roles->role_names[$role_name], 'singular_name' => $wp_roles->role_names[$role_name]];
                
                $pp->role_defs->direct_roles[$role_name] = (object)compact('labels');
            }
        }
    }

    function fltRoleCaps($caps, $role_name)
    {
        $matches = [];
        preg_match("/pp_(.*)_manager/", $role_name, $matches);

        if (!empty($matches[1])) {
            $taxonomy = $matches[1];
            if ($tx_obj = get_taxonomy($taxonomy)) {
                $caps = array_diff((array)$tx_obj->cap, ['edit_posts']);
            }
        }

        return $caps;
    }

    /*
    function flt_get_terms_is_term_admin($is_term_admin, $taxonomy)
    {
        global $pagenow;

        return $is_term_admin
            || in_array($pagenow, ['edit-tags.php', 'term.php'])
            || ('nav_menu' == $taxonomy && ('nav-menus.php' == $pagenow)
                || (('admin-ajax.php' == $pagenow) && (!empty($_REQUEST['action']) 
                && in_array($_REQUEST['action'], ['add-menu-item', 'menu-locations-save']))));
    }
    */

    function actLoadWorkaroundFilters()
    { 
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PageFilters.php');
        new Collab\PageFilters();
    }

    function actLoadCapFilters()
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/CapabilityFilters.php');
        new Collab\CapabilityFilters();

        if (defined('REVISIONARY_VERSION')) {
            require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Revisionary/CapabilityFilters.php');
            new Collab\Revisionary\CapabilityFilters();
        }
    }

    function actLoadPostFilters()
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostFilters.php');
        new Collab\PostFilters();
    }

    function actLoadFilters()
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/AdminFilters.php');
        new Collab\AdminFilters();
    }

    function fltExcludeArbitraryCaps($caps)
    {
        return array_merge($caps, [
            'pp_force_quick_edit', 
            'edit_own_attachments', 
            'list_others_unattached_files', 
            'admin_others_unattached_files', 
            'pp_list_all_files'
            ]
        );
    }

    function fltTaxInput($tax_input)
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostTermsSave.php');
        return Collab\PostTermsSave::fltTaxInput($tax_input);
    }

    function fltPrePostTerms($terms, $taxonomy = 'category')
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostTermsSave.php');
        return Collab\PostTermsSave::fltPreObjectTerms($terms, $taxonomy);
    }

    /* // this is now handled by fltPreObjectTerms instead
    function flt_default_term( $default_term_id, $taxonomy = 'category' ) {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostTermsSave.php');
        return PostTermsSave::flt_default_term( $default_term_id, $taxonomy );
    }
    */
}
