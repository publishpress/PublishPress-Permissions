<?php

namespace PublishPress\Permissions\UI;

class UsersListTable extends \WP_List_Table
{
    protected $_column_headers;
    public $user_ids;

    public function __construct()
    {
        global $_wp_column_headers;
        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && isset($_wp_column_headers[$screen->id])) {
                unset($_wp_column_headers[$screen->id]);
            }
        }
        parent::__construct([
            'singular' => 'User',
            'plural' => 'Users',
            'ajax' => false,
        ]);

        // Add custom query filter for users
        require_once(PRESSPERMIT_CLASSPATH . '/UI/Dashboard/UsersListing.php');
        new Dashboard\UsersListing();

        add_filter('pre_user_query', ['\PublishPress\Permissions\UI\Dashboard\UsersListing', 'fltUserQueryExceptions']);
    }

    public function get_columns()
    {
        $column_attr = [
            'pp_no_groups' => [
                'title' => esc_html__('Click to show only users who have no group', 'press-permit-core'),
                'style' => (!PWP::empty_REQUEST('pp_no_group') && !PWP::is_REQUEST('orderby', 'pp_group'))
                    ? 'style="font-weight:bold; color:black"'
                    : '',
            ],
            'pp_roles' => [
                'title' => (empty($_REQUEST['pp_has_roles'])) 
                ? esc_html__('Click to show only users who have Extra Roles (by group or directly)', 'press-permit-core')
                : esc_html__('Restore normal User Roles view', 'press-permit-core'),

                'style' => (!PWP::empty_REQUEST('pp_has_roles')) ? 'style="font-weight:bold; color:black"' : '',
            ],
            'pp_exceptions' => [
                'title' => (empty($_REQUEST['pp_has_exceptions'])) 
                ? esc_html__('Click to show only users who have Specific Permissions (by group or directly)', 'press-permit-core')
                : esc_html__('Restore normal User Permissions view', 'press-permit-core'),

                'style' => (!PWP::empty_REQUEST('pp_has_exceptions')) ? 'style="font-weight:bold; color:black"' : '',
            ],
        ];
        $columns = [
            'cb' => '<input type = "checkbox" />',
            'user_login' => __('Username', 'press-permit-core'),
            'name' => __('Name', 'press-permit-core'),
            'user_email' => __('Email', 'press-permit-core'),
            'pp_no_groups' => sprintf(
                esc_html__('%1$s(x)%2$s', 'press-permit-core'),
                '<a href="' . esc_url(add_query_arg('pp_no_group', intval(empty($_REQUEST['pp_no_group'])))) . '" title="' . esc_attr($column_attr['pp_no_groups']['title']) . '" ' . $column_attr['pp_no_groups']['style'] . '>',
                '</a>'
            ),
            'pp_groups' => __('Groups', 'press-permit-core'),
            'pp_exceptions' => sprintf(
                (empty($_REQUEST['pp_has_exceptions'])) ? esc_html__('User Permissions %1$s%2$s', 'press-permit-core') : esc_html__('Specific Permissions %1$s%2$s', 'press-permit-core'),
                (empty($_REQUEST['pp_user_perms'])) ? '<a href="' . esc_url(add_query_arg('pp_has_exceptions', intval(empty($_REQUEST['pp_has_exceptions'])))) . '" title="' . esc_attr($column_attr['pp_exceptions']['title']) . '" ' . $column_attr['pp_exceptions']['style'] . '>*' : '',
                (empty($_REQUEST['pp_user_perms'])) ? '</a>' : ''
            ),
            'pp_roles' => sprintf(
                (empty($_REQUEST['pp_has_roles'])) ? esc_html__('User Roles %1$s%2$s', 'press-permit-core') : esc_html__('Extra Roles %1$s%2$s', 'press-permit-core'),
                (empty($_REQUEST['pp_user_perms'])) ? '<a href="' . esc_url(add_query_arg('pp_has_roles', intval(empty($_REQUEST['pp_has_roles'])))) . '" title="' . esc_attr($column_attr['pp_roles']['title']) . '" ' . $column_attr['pp_roles']['style'] . '>*' : '',
                (empty($_REQUEST['pp_user_perms'])) ? '</a>' : ''
            ),
        ];
        return $columns;
    }

    public function get_sortable_columns()
    {
        return [
            'user_login' => ['user_login', false],
            'user_email' => ['user_email', false],
            'name' => ['display_name', false],
            'posts' => ['posts', false],
            'pp_groups' => ['pp_groups', false],
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

        $this->user_ids = array_map(function ($u) {
            return $u->ID;
        }, $this->items);

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
        return '';
    }

    // Custom column: Groups
    public function column_pp_groups($item)
    {
        return apply_filters('manage_users_custom_column', '', 'pp_groups', $item->ID, ['table_obj' => $this]);
    }

    // Custom column: Roles (with anchor)
    public function column_pp_roles($item)
    {
        $join_groups = !empty($_REQUEST['pp_has_exceptions']) || !empty($_REQUEST['pp_has_roles']);
        return apply_filters('manage_users_custom_column', '', 'pp_roles', $item->ID, ['join_groups' => $join_groups, 'table_obj' => $this]);
    }

    // Custom column: Specific Permissions
    public function column_pp_exceptions($item)
    {
        $join_groups = !empty($_REQUEST['pp_has_exceptions']) || !empty($_REQUEST['pp_has_roles']);
        return apply_filters('manage_users_custom_column', '', 'pp_exceptions', $item->ID, ['join_groups' => $join_groups, 'table_obj' => $this]);
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'user_login':
                $edit_permissions_url = esc_url(admin_url('admin.php?page=presspermit-edit-permissions&action=edit&agent_id=' . $item->ID . '&agent_type=user'));
                $edit_user_url = get_edit_user_link($item->ID);

                $row_actions = $this->row_actions([
                    'edit-permissions' => '<a href="' . $edit_permissions_url . '">' . esc_html__('Permissions', 'press-permit-core') . '</a>',
                    'edit' => '<a href="' . esc_url($edit_user_url) . '">' . esc_html__('Edit User', 'presspermit-core') . '</a>',
                ]);

                return '<strong><a href="' . esc_url($edit_permissions_url) . '">' . esc_html($item->user_login) . '</a></strong><br>' . $row_actions;
            case 'user_email':
                return '<a href="mailto:' . esc_attr($item->user_email) . '">' . esc_html($item->user_email) . '</a>';
            default:
                return '';
        }
    }


}