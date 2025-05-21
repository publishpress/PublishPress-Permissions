<?php

namespace PublishPress\Permissions\UI;

class UsersListTable extends \WP_List_Table
{
    protected $_column_headers;
    protected $user_groups = [];
    protected $user_exceptions = [];

    public function __construct()
    {
        global $_wp_column_headers;
        $screen = get_current_screen();
        if (isset($_wp_column_headers[$screen->id])) {
            unset($_wp_column_headers[$screen->id]);
        }
        parent::__construct([
            'singular' => 'User',
            'plural' => 'Users',
            'ajax' => false,
        ]);
    }

    public function get_columns()
    {
        $columns = [
            'cb'            => '<input type = "checkbox" />',
            'user_login'    => __('Username', 'press-permit-core'),
            'name'          => __('Name', 'press-permit-core'),
            'user_email'    => __('Email', 'press-permit-core'),
            'pp_no_groups'  => '<a href     = "?pp_no_group = 1" title = "Click to show only users who have no group">(x)</a>',
            'pp_groups'     => __('Groups', 'press-permit-core'),
            'pp_roles'      => __('Roles', 'press-permit-core'),
            'pp_exceptions' => __('Specific Permissions', 'press-permit-core'),
        ];
        return $columns;
    }

    public function get_sortable_columns()
    {
        return [
            'user_login' => ['user_login', false],
            'user_email' => ['user_email', false],
            'name'       => ['display_name', false],
            'posts'      => ['posts', false],
            'pp_groups'  => ['pp_groups', false],
        ];
    }

    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="users[]" value="%s" />',
            esc_attr($item->ID)
        );
    }

    public function get_bulk_actions()
    {
        return [
            'delete' => __('Delete', 'press-permit-core'),
        ];
    }

    public function prepare_items()
    {
        $per_page = 10;
        $paged = $this->get_pagenum();
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'user_login';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'asc';

        $args = [
            'number' => $per_page,
            'offset' => ($paged - 1) * $per_page,
            'search' => $search ? '*' . $search . '*' : '',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'orderby' => $orderby,
            'order' => $order,
        ];

        $users_query = new \WP_User_Query($args);
        $this->items = $users_query->get_results();

        // Pre-fetch group memberships, roles, and permissions for all users in this page
        $user_ids = array_map(function($u) { return $u->ID; }, $this->items);
        $this->user_groups = $this->get_users_groups($user_ids);
        $this->user_exceptions = $this->get_users_exceptions($user_ids);

        $this->set_pagination_args([
            'total_items' => $users_query->get_total(),
            'per_page' => $per_page,
            'total_pages' => ceil($users_query->get_total() / $per_page),
        ]);
    }

    // Custom column: Name
    public function column_name($item)
    {
        return esc_html($item->display_name);
    }

    // Custom column: (x) no groups
    public function column_pp_no_groups($item)
    {
        $groups = isset($this->user_groups[$item->ID]) ? $this->user_groups[$item->ID] : [];
        return empty($groups) ? '<span class="dashicons dashicons-no"></span>' : '';
    }

    // Custom column: Groups
    public function column_pp_groups($item)
    {
        $groups = isset($this->user_groups[$item->ID]) ? $this->user_groups[$item->ID] : [];
        if (empty($groups)) return '';
        $out = [];
        foreach ($groups as $group) {
            $url = add_query_arg([
                'page' => 'presspermit-groups',
                'action' => 'edit',
                'group_id' => $group['ID'],
            ], admin_url('admin.php'));
            $out[] = '<a href="' . esc_url($url) . '">' . esc_html($group['name']) . '</a>';
        }
        return implode(', ', $out);
    }

    // Custom column: Roles (with anchor)
    public function column_pp_roles($item)
    {
        $roles = $item->roles;
        if (empty($roles)) return '';
        $url = add_query_arg([
            'page' => 'presspermit-edit-permissions',
            'action' => 'edit',
            'agent_id' => $item->ID,
            'agent_type' => 'user',
        ], admin_url('admin.php'));
        $out = [];
        foreach ($roles as $role) {
            $out[] = '<span class="pp-group-site-roles">' . esc_html(ucfirst($role)) . '</span>';
        }
        return '<a href="' . esc_url($url) . '">' . implode(', ', $out) . '</a>';
    }

    // Custom column: Specific Permissions
    public function column_pp_exceptions($item)
    {
        $exceptions = isset($this->user_exceptions[$item->ID]) ? $this->user_exceptions[$item->ID] : [];
        if (empty($exceptions)) return '';
        $url = add_query_arg([
            'page' => 'presspermit-edit-permissions',
            'action' => 'edit',
            'agent_id' => $item->ID,
            'agent_type' => 'user',
        ], admin_url('admin.php'));
        return '<a href="' . esc_url($url) . '">' . esc_html__('View', 'press-permit-core') . '</a>';
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'user_login':
                $url = get_edit_user_link($item->ID);
                // $avatar = get_avatar($item->ID, 32);
                $row_actions = $this->row_actions([
                    'edit' => '<a href="' . esc_url($url) . '">' . esc_html__('Edit') . '</a>',
                    'view' => '<a href="' . esc_url(get_author_posts_url($item->ID)) . '">' . esc_html__('View') . '</a>',
                ]);
                return '<strong><a href="' . esc_url($url) . '">' . esc_html($item->user_login) . '</a></strong><br>' . $row_actions;
            case 'user_email':
                return '<a href="mailto:' . esc_attr($item->user_email) . '">' . esc_html($item->user_email) . '</a>';
            default:
                return '';
        }
    }

    // Helper: get group memberships for users
    protected function get_users_groups($user_ids)
    {
        // This should use the plugin's group membership API. For now, use a placeholder.
        $groups = [];
        foreach ($user_ids as $user_id) {
            // Example: $groups[$user_id] = [ ['ID' => 1, 'name' => 'Editors'], ... ];
            $groups[$user_id] = [];
        }
        // TODO: Replace with real group fetching logic
        return $groups;
    }

    // Helper: get specific permissions for users
    protected function get_users_exceptions($user_ids)
    {
        // This should use the plugin's API. For now, use a placeholder.
        $exceptions = [];
        foreach ($user_ids as $user_id) {
            $exceptions[$user_id] = [];
        }
        // TODO: Replace with real exception fetching logic
        return $exceptions;
    }

    // Helper: get posts count for users
    protected function get_users_posts_count($user_ids)
    {
        global $wpdb;
        $counts = [];
        if (empty($user_ids)) return $counts;
        $user_ids_sql = implode(',', array_map('intval', $user_ids));
        $results = $wpdb->get_results("SELECT post_author, COUNT(*) as count FROM $wpdb->posts WHERE post_author IN ($user_ids_sql) AND post_status = 'publish' GROUP BY post_author");
        foreach ($results as $row) {
            $counts[$row->post_author] = $row->count;
        }
        return $counts;
    }
}