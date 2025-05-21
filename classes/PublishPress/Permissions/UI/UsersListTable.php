<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class PP_Users_List_Table extends WP_List_Table {
    public function __construct() {
        global $_wp_column_headers;

        $screen = get_current_screen();

        // clear out empty entry from initial admin_header.php execution
        if (isset($_wp_column_headers[$screen->id])) {
            unset($_wp_column_headers[$screen->id]);
        }

        parent::__construct([
            'singular' => 'User',
            'plural'   => 'Users',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
      $columns = [
          'user_login' => __('Username', 'press-permit-core'),
          'user_email' => __('Email', 'press-permit-core'),
          'display_name' => __('Display Name', 'press-permit-core'),
          'roles' => __('Roles', 'press-permit-core'),
      ];

      return $columns;
    }

    public function prepare_items() {
        $per_page = 10;
        $paged = $this->get_pagenum();
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';

        $args = [
            'number' => $per_page,
            'offset' => ($paged - 1) * $per_page,
            'search' => $search ? '*' . $search . '*' : '',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
        ];

        $users_query = new \WP_User_Query($args);
        $this->items = $users_query->get_results();

        $this->set_pagination_args([
            'total_items' => $users_query->get_total(),
            'per_page'    => $per_page,
            'total_pages' => ceil($users_query->get_total() / $per_page),
        ]);
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'user_login':
                return esc_html($item->user_login);
            case 'user_email':
                return esc_html($item->user_email);
            case 'display_name':
                return esc_html($item->display_name);
            case 'roles':
                return esc_html(implode(', ', $item->roles));
            default:
                return '';
        }
    }
}