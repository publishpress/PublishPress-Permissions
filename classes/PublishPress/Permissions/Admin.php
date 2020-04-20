<?php

namespace PublishPress\Permissions;

class Admin
{
    // object references
    private $agents;

    // status / memcache
    private $last_post_status = [];
    public $errors;

    public function __construct() {
        add_action('admin_notices', [$this, 'rsMigrationNotice']);
    }

    public function rsMigrationNotice()
    {
        if (!presspermit()->getOption('offer_rs_migration') 
        || !presspermit()->isAdministrator() 
        || (apply_filters('presspermit_import_count', 0, 'rs') && empty($_REQUEST['rs-not-imported']))
        ) {
            return;
        }

        $url = admin_url('admin.php?page=presspermit-settings&pp_tab=import');
        
        $this->notice(
            sprintf(
                __('Role Scoper installation detected. To migrate your groups, roles, restrictions and options to PublishPress Permissions, run the %sImport tool%s.', 'press-permit-core'),
                '<a href="' . $url . '">',
                '</a>'
            ), 'rs-migration'
        );
    }

    public function getLastPostStatus($post_id)
    {
        return (isset($this->last_post_status[$post_id])) ? $this->last_post_status[$post_id] : false;
    }

    public function setLastPostStatus($post_id, $status)
    {
        $this->last_post_status[$post_id] = $status;
    }

    public function getMenuParams($for)
    {
        if ((defined('OZH_MENU_VER') && !defined('PP_FORCE_PLUGIN_MENU')) || defined('PP_FORCE_USERS_MENU')) {
            $arr = ['permits' => 'users.php', 'options' => 'options-general.php'];
        } else {
            $arr = ['permits' => 'presspermit-groups', 'options' => 'presspermit-groups'];
        }

        if (isset($arr[$for])) {
            return $arr[$for];
        }
    }

    public function agents()
    {
        if (!isset($this->agents)) {
            require_once(PRESSPERMIT_CLASSPATH . '/UI/Agents.php');
            $this->agents = new UI\Agents();
        }

        return $this->agents;
    }

    // allow lockdown to non-Administrators (while still allowing item-specific role editing for those who have assign_roles capability)
    public function bulkRolesEnabled()
    {
        return (current_user_can('pp_assign_roles') && (current_user_can('pp_administer_content') || current_user_can('pp_assign_bulk_roles'))
                && !defined('PP_DISABLE_BULK_ROLES')) || (current_user_can('edit_users'));
    }

    public function userCanAdminRole($role_name, $post_type, $item_id = 0)
    {
        require_once(PRESSPERMIT_CLASSPATH . '/PermissionsAdmin.php');
        return PermissionsAdmin::userCanAdminRole($role_name, $post_type, $item_id);
    }

    public function canSetExceptions($operation, $for_item_type, $args = [])
    {
        require_once(PRESSPERMIT_CLASSPATH . '/PermissionsAdmin.php');
        return PermissionsAdmin::canSetExceptions($operation, $for_item_type, $args);
    }

    public function getAdministratorRoles()
    {
        // WP roles containing the 'pp_administer_content' cap are always honored regardless of object or term restritions
        global $wp_roles;
        $admin_roles = [];

        if (isset($wp_roles->role_objects)) {
            foreach (array_keys($wp_roles->role_objects) as $wp_role_name) {
                if (!empty($wp_roles->role_objects[$wp_role_name]->capabilities['pp_administer_content'])) {
                    $admin_roles[$wp_role_name] = true;
                }
            }
        }

        return $admin_roles;
    }

    public function getRoleTitle($role_name, $args = [])
    {
        require_once(PRESSPERMIT_CLASSPATH . '/PermissionsAdmin.php');
        return PermissionsAdmin::getRoleTitle($role_name, $args);
    }

    public function getOperationObject($operation, $post_type = '')
    {
        static $operations;

        if (!isset($operations)) {
            $op_captions = apply_filters(
                'presspermit_operation_captions',
                ['read' => (object)['label' => __('Read'), 'noun_label' => __('Reading', 'press-permit-core')]]
            );

            $operations = Arr::subset($op_captions, presspermit()->getOperations());
        }

        // deference op_obj from static array so type-specific filtering is not memcached
        $op_obj = (isset($operations[$operation])) ? (object)(array)$operations[$operation] : false;

        return apply_filters('presspermit_operation_object', $op_obj, $operation, $post_type);
    }

    public function orderTypes($types, $args = [])
    {
        $defaults = ['order_property' => '', 'item_type' => '', 'labels_property' => ''];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        if ('post' == $item_type) {
            $post_types = get_post_types([], 'object');
        } elseif ('taxonomy' == $item_type) {
            $taxonomies = get_taxonomies([], 'object');
        }

        $ordered_types = [];
        foreach (array_keys($types) as $name) {
            if ('post' == $item_type) {
                $ordered_types[$name] = (isset($post_types[$name]->labels->singular_name))
                    ? $post_types[$name]->labels->singular_name
                    : '';
            } elseif ('taxonomy' == $item_type) {
                $ordered_types[$name] = (isset($taxonomies[$name]->labels->singular_name))
                    ? $taxonomies[$name]->labels->singular_name
                    : '';
            } else {
                if (!is_object($types[$name])) {
                    return $types;
                }

                if ($order_property) {
                    $ordered_types[$name] = (isset($types[$name]->$order_property))
                        ? $types[$name]->$order_property
                        : '';
                } else {
                    $ordered_types[$name] = (isset($types[$name]->labels->$labels_property))
                        ? $types[$name]->labels->$labels_property
                        : '';
                }
            }
        }

        asort($ordered_types);

        foreach (array_keys($ordered_types) as $name) {
            $ordered_types[$name] = $types[$name];
        }

        return $ordered_types;
    }

    public function getModuleInfo($args=[])
    {
        $title = [
            'circles' =>        'Access Circles',
            'collaboration' =>  'Collaborative Publishing',
            'compatibility' =>  'Compatibility Pack',
            'teaser' =>         'Teaser',
            'status-control' => 'Status Control',
            'file-access' =>    'File Access',
            'import' =>         'Import',
            'membership' =>     'Membership',
            'sync' =>           'Sync Posts',
            'role-scoper-migration-advisor' => 'Role Scoper Migration Advisor',
        ];
        
        $blurb = [
            'circles' => 'Visibility Circles and Editorial Circles block access to content not authored by other group members.',
            'collaboration' => 'Content-specific permissions for editing, term assignment and page parent selection.',
            'compatibility' => 'Integration with bbPress, BuddyPress, Relevanssi, WPML and other plugins; enhanced Multisite support.',
            'teaser' => 'On the site front end, replace non-readable content with placeholder text.',
            'status-control' => 'Custom post statuses: Control permissions for multi-step Workflow or custom Privacy.',
            'file-access' => 'Filters direct file access, based on user&apos;s access to post(s) which the file is attached to.',
            'import' => 'Import Role Scoper groups, roles, restrictions and settings.',
            'membership' => 'Allows Permission Group membership to be date-limited (delayed and/or scheduled for expiration).',
            'sync' => 'Create or synchronize posts to match users. Designed for Team / Staff plugins, but with broad usage potential.',
            'role-scoper-migration-advisor' => 'Analyzes your Role Scoper installation, identifying PP migration readiness or issues.', 
        ];
        
        $descript = [
            'circles' => 'Visibility Circles and Editorial Circles block access to content not authored by other group members. Any WP Role, BuddyPress Group or custom Group can be marked as a Circle for specified post types.',
            'collaboration' => 'Supports content-specific permissions for editing, term assignment and page parent selection. In combination with other modules, supports workflow statuses, PublishPress, Revisionary and Post Forking.',
            'compatibility' => 'Adds compatibility or integration with bbPress, Relevanssi, Co-Authors Plus, CMS Tree Page View, Custom Post Type UI, Subscribe2, WPML, various other plugins. BuddyPress Permissions Groups. For multisite, provides network-wide permission groups.',
            'teaser' => 'On the site front end, replace non-readable content with placeholder text. Can be enabled for any post type. Custom filters are provided but no programming is required for basic usage.',
            'status-control' => 'Custom post statuses: Workflow statuses (also requires Collaborative Publishing module) allow unlimited orderable steps between pending and published, each with distinct capability requirements and role assignments.  Both privacy and workflow statuses can be type-specific.',
            'file-access' => 'Filters direct file access, based on user&apos;s access to post(s) which the file is attached to. No additional configuration required. Creates/modifies .htaccess file in uploads folder (and in main folder for multisite).',
            'import' => 'Import Role Scoper groups, roles, restrictions and settings.',
            'membership' => 'Allows Permission Group membership to be date-limited (delayed and/or scheduled for expiration). Simple date picker UI alongside member selection.',
            'sync' => 'Create or synchronize posts to match users. Designed for Team / Staff plugins, but with broad usage potential.',
            'role-scoper-migration-advisor' => 'Analyzes your Role Scoper installation, identifying groups, roles, restrictions and options which can (or cannot) be automatically imported by the Import module.', 
        ];

        return (object) compact('title', 'blurb', 'descript');
    }

    public function isPluginAction()
    {
        return false !== strpos( $_SERVER['REQUEST_URI'], 'plugin-install.php' ) 
        || (! empty($_REQUEST['action']) && in_array( $_REQUEST['action'], ['activate', 'deactivate']));
    }

    public function errorNotice($err_slug, $args)
    {
        require_once(PRESSPERMIT_CLASSPATH . '/ErrorNotice.php');
        return new \PublishPress\Permissions\ErrorNotice($err_slug, $args);
    }

    public function notice($notice, $msg_id = '')
    {
		$dismissals = (array) pp_get_option('dismissals');

		if ($msg_id && isset($dismissals[$msg_id]) && (empty($_REQUEST['pp_ignore_dismissal']) || ($msg_id != $_REQUEST['pp_ignore_dismissal'])))
			return;
		
        require_once(PRESSPERMIT_CLASSPATH . '/ErrorNotice.php');
        $err = new \PublishPress\Permissions\ErrorNotice();
        $err->addNotice($notice, ['id' => $msg_id]);
    }

    function publishpressFooter() {
        if (presspermit()->isPro() && !presspermit()->getOption('display_branding')) {
            return;
        }
    ?>
        <footer>

        <div class="pp-rating">
        <a href="https://wordpress.org/support/plugin/press-permit-core/reviews/#new-post" target="_blank" rel="noopener noreferrer">
        <?php printf( 
            __('If you like %s, please leave us a %s rating. Thank you!', 'press-permit-core'),
            '<strong>PublishPress Permissions</strong>',
            '<span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span>'
            );
        ?>
        </a>
        </div>

        <hr>
        <nav>
        <ul>
        <li><a href="https://publishpress.com/permissions" target="_blank" rel="noopener noreferrer" title="<?php _e('About PublishPress Permissions', 'press-permit-core');?>"><?php _e('About', 'press-permit-core');?>
        </a></li>
        <li><a href="https://publishpress.com/documentation/permissions-start/" target="_blank" rel="noopener noreferrer" title="<?php _e('Permissions Documentation', 'press-permit-core');?>"><?php _e('Documentation', 'press-permit-core');?>
        </a></li>
        <li><a href="https://publishpress.com/contact" target="_blank" rel="noopener noreferrer" title="<?php _e('Contact the PublishPress team', 'press-permit-core');?>"><?php _e('Contact', 'press-permit-core');?>
        </a></li>
        <li><a href="https://twitter.com/publishpresscom" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-twitter"></span>
        </a></li>
        <li><a href="https://facebook.com/publishpress" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-facebook"></span>
        </a></li>
        </ul>
        </nav>

        <div class="pp-pressshack-logo">
        <a href="//publishpress.com" target="_blank" rel="noopener noreferrer">
        <img src="<?php echo plugins_url('', PRESSPERMIT_FILE) . '/common/img/publishpress-logo.png';?>" />
        </a>
        </div>

        </footer>
    <?php
    }
}
