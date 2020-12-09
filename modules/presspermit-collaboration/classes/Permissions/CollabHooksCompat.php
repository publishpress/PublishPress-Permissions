<?php
namespace PublishPress\Permissions;

class CollabHooksCompat
{
    function __construct() {
        add_action('init', [$this, 'actStatusRegistrations'], 44);  // statuses need to be registered before establish_status_caps() execution

        add_action('presspermit_registrations', [$this, 'actRegistrations']);  // this action is triggered by Status Control module
        add_action('presspermit_roles_defined', [$this, 'actAdjustDefaultPatternRoles']);

        add_filter('presspermit_operations', [$this, 'fltOperations']);
        add_filter('presspermit_define_pattern_caps', [$this, 'fltDefinePatternCaps']);
        add_filter('presspermit_apply_arbitrary_caps', [$this, 'fltApplyArbitraryCaps'], 10, 3);
    }

    function fltOperations($ops)
    {
        $ops[] = 'edit';

        if (presspermit()->getOption('publish_exceptions'))
            $ops[] = 'publish';

        if (class_exists('Fork', false) && !defined('PP_DISABLE_FORKING_SUPPORT'))
            $ops[] = 'fork';

        if (defined('REVISIONARY_VERSION'))
            $ops[] = 'revise';

        $ops = array_merge($ops, ['associate', 'assign', 'manage']);

        return $ops;
    }

    function fltDefinePatternCaps($pattern_role_caps)
    {
        $type_obj = get_taxonomy('category');
        $type_caps['category'] = array_intersect_key(get_object_vars($type_obj->cap), array_fill_keys(['manage_terms'], true));

        $cap_caster = presspermit()->capCaster();

        foreach (array_keys($pattern_role_caps) as $role_name) {
            // log caps defined for the "category" taxonomy
            $cap_caster->pattern_role_taxonomy_caps[$role_name] = array_intersect_key($pattern_role_caps[$role_name], $type_caps['category']);
        }
    }

    function fltApplyArbitraryCaps($caps, $arr_name, $type_obj)
    {
        $base_role_name = $arr_name[0];

        $cap_caster = presspermit()->capCaster();

        // "Misc" caps are other caps in the pattern role which are not type-defined
        //
        // for now, don't apply arbitrary caps for typecast term management roles
        if (!empty($cap_caster->pattern_role_arbitrary_caps[$base_role_name]) && post_type_exists($type_obj->name)) {
            $arbitrary_caps = $cap_caster->pattern_role_arbitrary_caps[$base_role_name];

            // these caps will be added only for supplemental roles with no status specified
            if (!empty($arr_name[4])) {
                $arbitrary_caps = array_diff_key(
                    $arbitrary_caps, 
                    array_fill_keys(
                        apply_filters(
                            'presspermit_status_role_skip_caps', 
                            [
                                'list_users', 
                                'edit_users', 
                                'delete_users', 
                                'switch_themes', 
                                'edit_themes', 
                                'activate_plugins', 
                                'edit_plugins', 
                                'manage_options', 
                                'manage_links', 
                                'import'
                            ]
                        ), 
                        true
                    )
                );
            }

            $caps = array_merge($arbitrary_caps, $caps);
        }

        return $caps;
    }

    function actStatusRegistrations()
    {
        if (!defined('PRESSPERMIT_VERSION'))
            return;

        if (defined('PRESSPERMIT_STATUSES_VERSION') && !defined('PP_NO_MODERATION')) {
            // custom moderation stati
            register_post_status('approved', [
                'label' => _x('Approved', 'post'),
                'labels' => (object)['publish' => __('Approve', 'press-permit-core')],
                'moderation' => true,
                'protected' => true,
                'internal' => false,
                'label_count' => _n_noop('Approved <span class="count">(%s)</span>', 'Approved <span class="count">(%s)</span>'),
                'pp_builtin' => true,
            ]);
        }
    }

    function actRegistrations()
    {
        if (defined('PRESSPERMIT_STATUSES_VERSION')) {
            global $wp_post_statuses;

            $pp = presspermit();

            $user = presspermit()->getUser();

            foreach (['pending', 'future'] as $status) {
                if (!empty($wp_post_statuses[$status])) {
                    $wp_post_statuses[$status]->moderation = true;
                }
            }

            // unfortunate little hack due to execution order
            if ($pp->getOption('supplemental_cap_moderate_any') && $user->ID 
            && $user->site_roles && !$pp->isContentAdministrator()
            ) {
                require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/Permissions.php');
                Collab\Permissions::supplementModerateAnyCap();
            }

            $skip_metacaps = !empty($user->allcaps['pp_moderate_any']) 
            && (!is_admin() || ('presspermit-statuses' != presspermitPluginPage()))       // Capabilities screen needs all status capabilities loaded for administration
            && ((false == strpos($_SERVER['SCRIPT_NAME'], 'admin.php') || empty($_REQUEST['page'] || ('capsman' != $_REQUEST['page']))) || (!presspermit()->isAdministrator() && !current_user_can('manage_capabilities')));

            // register each custom post status as an attribute condition with mapped caps
            foreach (get_post_stati([], 'object') as $status => $status_obj) {
                if (!empty($status_obj->moderation)) {
                    if (in_array($status, ['pending', 'future'], true) || !empty($status_obj->pp_custom)) { // pp_custom = defined by PublishPress
                        if (!$pp->getOption("custom_{$status}_caps") 
                        || (defined('PP_LEGACY_PENDING_STATUS') && ('pending' == $status))
                        ) {
                            continue;
                        }
                    }

                    if (isset($status_obj->capability_status) && ('' === $status_obj->capability_status)) {
                        continue;
                    }

                    $_status = (isset($status_obj->capability_status) && ($status != $status_obj->capability_status) 
                    && !empty($wp_post_statuses[$status_obj->capability_status])) 
                    ? $status_obj->capability_status 
                    : $status;

                    $metacap_map = ($skip_metacaps) 
                    ? [] 
                    : ['edit_post' => "edit_{$_status}_posts", 'delete_post' => "delete_{$_status}_posts"];

                    PPS::registerCondition('post_status', $status, [
                        'label' => $status_obj->label,
                        'metacap_map' => $metacap_map,
                        'cap_map' => [
                            'set_posts_status' => "set_posts_{$_status}",                         
                            'edit_others_posts' => "edit_others_{$status}_posts",
                            'delete_others_posts' => "delete_others_{$status}_posts"
                        ]
                    ]);
                }
            }
        }
    }

    function actAdjustDefaultPatternRoles()
    {
        //presspermit()->registerPatternRole( 'reviewer', ['labels' => (object)['name' => __('Reviewers', 'press-permit-core'), 'singular_name' => __('Reviewer', 'press-permit-core')]]);

        if (defined('REVISIONARY_VERSION')) {
            presspermit()->registerPatternRole(
                'revisor', 
                [
                    'labels' => (object)[
                        'name' => __('Revisors', 'press-permit-core'), 
                        'singular_name' => __('Revisor', 'press-permit-core')
                    ]
                ]
            );
        }
    }
}
