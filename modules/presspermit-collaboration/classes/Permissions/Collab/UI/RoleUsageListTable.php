<?php
namespace PublishPress\Permissions\Collab\UI;

require_once(PRESSPERMIT_COLLAB_CLASSPATH . '/UI/RoleUsageQuery.php');

class RoleUsageListTable extends \WP_List_Table
{
    var $site_id;
    var $role_info;

    private static $instance = null;

    public static function instance() {
        if ( is_null(self::$instance) ) {
            self::$instance = new RoleUsageListTable();
        }

        return self::$instance;
    }

    public function __construct() // PHP 5.6.x and some PHP 7.x configurations prohibit restrictive subclass constructors
    {
        $screen = get_current_screen();

        // clear out empty entry from initial admin_header.php execution
        global $_wp_column_headers;
        if (isset($_wp_column_headers[$screen->id]))
            unset($_wp_column_headers[$screen->id]);

        parent::__construct([
            'singular' => 'role',
            'plural' => 'roles'
        ]);
    }

    function ajax_user_can()
    {
        return current_user_can('pp_manage_settings');
    }

    function prepare_items()
    {
        // Query the user IDs for this page
        $search = new RoleUsageQuery();

        $this->items = $search->get_results();

        $this->set_pagination_args([
            'total_items' => $search->get_total(),
            //'per_page' => $groups_per_page,
        ]);
    }

    function no_items()
    {
        _e('No matching roles were found.', 'press-permit-core');
    }

    function get_views()
    {
        return [];
    }

    function get_bulk_actions()
    {
        return [];
    }

    function get_columns()
    {
        $c = [
            'role_name' => PWP::__wp('Role'),
            'usage' => __('Usage', 'press-permit-core'),
        ];

        return $c;
    }

    function get_sortable_columns()
    {
        $c = [
            //'usage' => 'usage',
        ];

        return $c;
    }

    function display_rows()
    {
        $style = '';

        foreach ($this->items as $role_object) {
            $style = (' class="alternate"' == $style) ? '' : ' class="alternate"';
            echo "\n\t", $this->single_row($role_object, $style);
        }
    }

    function display_tablenav($which)
    {
        
    }

    /**
     * Generate HTML for a single row on the PP Role Groups admin panel.
     *
     * @param object $user_object
     * @param string $style Optional. Attributes added to the TR element.  Must be sanitized.
     * @param int $num_users Optional. User count to display for this group.
     * @return string
     */
    function single_row($role_obj, $style = '')
    {
        static $base_url;

        $role_name = $role_obj->name;

        // Set up the hover actions for this user
        $actions = [];
        $checkbox = '';

        static $can_manage;
        if (!isset($can_manage))
            $can_manage = current_user_can('pp_manage_settings');

        // Check if the group for this row is editable
        if ($can_manage) {
            $edit_link = $base_url . "?page=presspermit-role-usage-edit&amp;action=edit&amp;role={$role_name}";
            $edit = "<strong><a href=\"$edit_link\">{$role_obj->labels->singular_name}</a></strong><br />";
            $actions['edit'] = '<a href="' . $edit_link . '">' . PWP::__wp('Edit') . '</a>';
        } else {
            $edit = '<strong>' . $role_obj->labels->name . '</strong>';
        }

        $actions[''] = '&nbsp;';  // temp workaround to prevent shrunken row

        $actions = apply_filters('presspermit_role_usage_row_actions', $actions, $role_obj);
        $edit .= $this->row_actions($actions);

        $r = "<tr $style>";

        list($columns, $hidden) = $this->get_column_info();

        foreach ($columns as $column_name => $column_display_name) {
            $class = "class=\"$column_name column-$column_name\"";

            $style = '';
            if (in_array($column_name, $hidden, true))
                $style = ' style="display:none;"';

            $attributes = "$class$style";

            switch ($column_name) {
                case 'role_name':
                    $r .= "<td $attributes>$edit</td>";
                    break;
                case 'usage':
                    switch ($role_obj->usage) {
                        case 'direct':
                            $caption = __('Direct Assignment', 'press-permit-core');
                            break;
                            
                        default:
                            $caption = (empty($role_obj->usage)) 
                            ? __('no supplemental assignment', 'press-permit-core') 
                            : __('Pattern Role', 'press-permit-core');
                    }
                    $r .= "<td $attributes>$caption</td>";
                    break;
                default:
                    $r .= "<td $attributes>";
                    $r .= apply_filters('presspermit_manage_role_usage_custom_column', '', $column_name, $role_obj);
                    $r .= "</td>";
            }
        }
        $r .= '</tr>';

        return $r;
    }
}
