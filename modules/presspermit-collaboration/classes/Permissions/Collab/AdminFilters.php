<?php
namespace PublishPress\Permissions\Collab;

class AdminFilters
{
    private $inserting_post = false;

    // Backend filtering which is generally enabled for all requests 
    //
    function __construct()
    {
        add_action('presspermit_init', [$this, 'actDisableForumPatternRoles']);

        add_filter('presspermit_enabled_taxonomies', [$this, 'fltGetEnabledTaxonomies'], 10, 2);
        add_filter('wp_dropdown_pages', [$this, 'fltDropdownPages']);

        add_filter('pre_post_parent', [$this, 'fltPageParent'], 50, 1);
        add_filter('pre_post_status', [$this, 'fltPostStatus'], 50, 1);

        add_filter('user_has_cap', [$this, 'fltHasEditUserCap'], 99, 3);

        add_filter('presspermit_append_attachment_clause', [$this, 'fltAppendAttachmentClause'], 10, 3);

        add_filter('presspermit_operation_captions', [$this, 'fltOperationCaptions']);

        // called by permissions-ui
        add_filter('presspermit_exception_types', [$this, 'fltExceptionTypes']);
        add_filter('presspermit_append_exception_types', [$this, 'fltAppendExceptionTypes']);
        add_action('presspermit_role_types_dropdown', [$this, 'actDropdownTaxonomyTypes']);
        add_action('presspermit_exception_types_dropdown', [$this, 'actDropdownTaxonomyTypes']);

        // called by ajax-exceptions-ui
        add_filter('presspermit_exception_operations', [$this, 'fltExceptionOperations'], 2, 3);
        add_filter('presspermit_exception_via_types', [$this, 'fltExceptionViaTypes'], 10, 5);
        add_filter('presspermit_exceptions_status_ui', [$this, 'fltExceptionsStatusUi'], 4, 3);

        add_filter('presspermit_ajax_role_ui_vars', [$this, 'actAjaxRoleVars'], 10, 2);
        add_filter('presspermit_get_type_roles', [$this, 'fltGetTypeRoles'], 10, 3);
        add_filter('presspermit_role_title', [$this, 'fltGetRoleTitle'], 10, 2);

        // called by agent-edit-handler
        add_filter('presspermit_add_exception', [$this, 'fltAddException']);

        // Track autodrafts by postmeta in case WP sets their post_status to draft
        add_action('save_post', [$this, 'actSavePost'], 10, 3);
        add_filter('wp_insert_post_empty_content', [$this, 'fltLogInsertPost'], 10, 2);

        add_filter('save_post', [$this, 'fltUnloadCurrentUserExceptions']);
        add_filter('created_term', [$this, 'fltUnloadCurrentUserExceptions']);

        add_filter('editable_roles', [$this, 'fltEditableRoles'], 99);
    }

    function actSavePost($post_id, $post, $update)
    {
        if (!empty(presspermit()->flags['ignore_save_post'])) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            update_post_meta($post_id, '_pp_is_autodraft', true);

        } elseif (!$update) {
            // For configurations that limit access by term selection, need to default to an allowed term
            if (!presspermit()->isAdministrator()) {
                require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostTermsSave.php');
        
                foreach(get_object_taxonomies($post->post_type) as $taxonomy) {
                    if (!$terms = wp_get_object_terms($post->ID, $taxonomy, ['fields' => 'ids'])) {
                        if ($terms = PostTermsSave::fltPreObjectTerms($terms, $taxonomy)) {
                            wp_set_post_terms($post->ID, $terms, $taxonomy);
                        }
                    }
                }
            }
        }
    }

    function fltUnloadCurrentUserExceptions($item_id)
    {
        if (!empty(presspermit()->flags['ignore_save_post'])) {
            return;
        }

        presspermit()->getUser()->except = []; // force current user exceptions to be reloaded at relevant next capability check
    }

    function actDisableForumPatternRoles()
    {
        $pp = presspermit();
        $pp->role_defs->disabled_pattern_role_types = array_merge(
            $pp->role_defs->disabled_pattern_role_types, 
            array_fill_keys(['forum', 'topic', 'reply'], true)
        );
    }

    function fltGetEnabledTaxonomies($taxonomies, $args)
    {
        if (empty($args['object_type']) || ('nav_menu_item' == $args['object_type']))
            $taxonomies['nav_menu'] = 'nav_menu';

        return $taxonomies;
    }

    function fltAddException($exception)
    {
        if ('_term_' == $exception['for_type']) {
            $exception['for_type'] = $exception['via_type'];
        }

        return $exception;
    }

    function fltExceptionTypes($types)
    {
        if (!isset($types['attachment']))
            $types['attachment'] = get_post_type_object('attachment');

        return $types;
    }

    function fltAppendExceptionTypes($types)
    {
        $types['pp_group'] = (object)[
            'name' => 'pp_group', 
            'labels' => (object)[
                'singular_name' => __('Permission Group', 'press-permit-core'), 
                'name' => __('Permission Groups', 'press-permit-core')
                ]
            ];
        
        return $types;
    }

    function actDropdownTaxonomyTypes($args = [])
    {
        if (empty($args['agent']) || empty($args['agent']->metagroup_id) 
        || !in_array($args['agent']->metagroup_id, ['wp_anon', 'wp_all'], true)) 
        {
            echo "<option value='_term_'>" . __('term (manage)', 'press-permit-core') . '</option>';
        }
    }

    function fltOperationCaptions($op_captions)
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/AjaxUI.php');
        return UI\AjaxUI::fltOperationCaptions($op_captions);
    }

    function fltExceptionOperations($ops, $for_source_name, $for_item_type)
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/AjaxUI.php');
        return UI\AjaxUI::fltExceptionOperations($ops, $for_source_name, $for_item_type);
    }

    function fltExceptionViaTypes($types, $for_source_name, $for_type, $operation, $mod_type)
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/AjaxUI.php');
        return UI\AjaxUI::fltExceptionViaTypes($types, $for_source_name, $for_type, $operation, $mod_type);
    }

    function fltExceptionsStatusUi($html, $for_type, $args = [])
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/AjaxUI.php');
        return UI\AjaxUI::fltExceptionsStatusUi($html, $for_type, $args);
    }

    function fltGetRoleTitle($role_title, $args)
    {
        $matches = [];
        preg_match("/pp_(.*)_manager/", $role_title, $matches);

        if (!empty($matches[1])) {
            $taxonomy = $matches[1];
            if ($tx_obj = get_taxonomy($taxonomy))
                $role_title = sprintf(__('%s Manager', 'press-permit-core'), $tx_obj->labels->singular_name);
        }

        return $role_title;
    }

    function actAjaxRoleVars($force, $args)
    {
        if (0 === strpos($args['for_item_type'], '_term_')) {
            $force = (array)$force;
            $force['for_item_source'] = 'term';
            $force['for_item_type'] = substr($args['for_item_type'], strlen('_term_'));
        }

        return $force;
    }

    function fltGetTypeRoles($type_roles, $for_item_source, $for_item_type)
    {
        if ('term' == $for_item_source) {
            $pp = presspermit();

            foreach ($pp->getEnabledTaxonomies(['object_type' => false]) as $taxonomy) {
                $type_roles["pp_{$taxonomy}_manager"] = $pp->admin()->getRoleTitle("pp_{$taxonomy}_manager");
            }
        }

        return $type_roles;
    }

    // Optionally, prevent anyone from editing or deleting a user whose level is higher than their own
    function fltHasEditUserCap($wp_sitecaps, $orig_reqd_caps, $args)
    {
        if (presspermit()->filteringEnabled() && (
            in_array('edit_users', $orig_reqd_caps, true) || in_array('delete_users', $orig_reqd_caps, true) 
            || in_array('remove_users', $orig_reqd_caps, true) || in_array('promote_users', $orig_reqd_caps, true)
            ) && !empty($args[2])
        ) {
            if ($editing_limitation = presspermit()->getOption('limit_user_edit_by_level')) {
                require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Users.php');
                $wp_sitecaps = Users::hasEditUserCap($wp_sitecaps, $orig_reqd_caps, $args, $editing_limitation);
            }
        }

        return $wp_sitecaps;
    }

    function fltLogInsertPost($maybe_empty, $postarr)
    {
        $this->inserting_post = true;
        return $maybe_empty;
    }

    function fltPageParent($parent_id, $args = [])
    {
        if (!presspermit()->filteringEnabled() || ('revision' == PWP::findPostType()) || did_action('pp_disable_page_parent_filter') || ($this->inserting_post))
            return $parent_id;

        // Avoid preview failure with ACF active
        if (!empty($_REQUEST['wp-preview']) && ('dopreview' == $_REQUEST['wp-preview']) 
        && !empty($_REQUEST['action']) && ('editpost' == $_REQUEST['action'])
        && !empty($_REQUEST['post_ID']) && ($parent_id == $_REQUEST['post_ID'])
        ) {
            return $parent_id;
        }

        $orig_parent_id = $parent_id;
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostSaveHierarchical.php');
        $parent_id = PostSaveHierarchical::fltPageParent($parent_id);
        
        // Don't allow media attachment page to be cleared if user has editing capability (conflict with Image Source Control plugin)
        if (!$parent_id && $orig_parent_id 
        && (
            false !== strpos($_SERVER['SCRIPT_NAME'], 'async-upload.php')
            || ('attachment' == PWP::findPostType())
            || (false !== strpos($_SERVER['SCRIPT_NAME'], 'admin-ajax.php') && in_array($_REQUEST['action'], ['save-attachment', 'save-attachment-compat']))
            )
        ) {
            if (current_user_can('edit_post', $orig_parent_id)) {
                $parent_id = $orig_parent_id;
            }
        }

        return $parent_id;
    }

    // filter page dropdown contents for Page Parent controls; leave others alone
    function fltDropdownPages($orig_options_html)
    {
        if (presspermit()->isUserUnfiltered() || (!strpos($orig_options_html, 'parent_id') && !strpos($orig_options_html, 'post_parent')))
            return $orig_options_html;

        global $pagenow;

        if (0 === strpos($pagenow, 'options-'))
            return $orig_options_html;

        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PageHierarchyFilters.php');
        return PageHierarchyFilters::fltDropdownPages($orig_options_html);
    }

    function fltPostStatus($status)
    {
        if (presspermit()->isUserUnfiltered() || ('auto-draft' == $status) || strpos($_SERVER['REQUEST_URI'], 'nav-menus.php'))
            return $status;

        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/PostEdit.php');
        return PostEdit::fltPostStatus($status);
    }

    function fltAppendAttachmentClause($where, $clauses, $args)
    {
        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/MediaQuery.php');
        return MediaQuery::appendAttachmentClause($where, $clauses, $args);
    }

    // optional filter for WP role edit based on user level
    function fltEditableRoles($roles)
    {
        if (!presspermit()->filteringEnabled() || !presspermit()->getOption('limit_user_edit_by_level'))
            return $roles;

        require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Users.php');
        return Users::editableRoles($roles);
    }
}
