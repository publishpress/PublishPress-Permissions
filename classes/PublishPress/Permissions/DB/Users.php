<?php

namespace PublishPress\Permissions\DB;

class Users
{
    public static function getUsers($args = [])
    {
        $defaults = ['cols' => 'all'];
        $args = array_merge($defaults, (array)$args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        global $wpdb;

        switch ($cols) {
            case 'id':
                return $wpdb->get_col("SELECT ID FROM $wpdb->users $orderby");
                break;
            case 'id_name':
                // calling code assumes display_name property for user or group object
                return $wpdb->get_results("SELECT ID, user_login AS display_name FROM $wpdb->users ORDER BY display_name");
                break;
            case 'id_displayname':
                return $wpdb->get_results("SELECT ID, display_name FROM $wpdb->users ORDER BY display_name");
                break;
            case 'all':
                return $wpdb->get_results("SELECT * FROM $wpdb->users ORDER BY display_name");
                break;
            default:
                $qcols = $cols;
                return $wpdb->get_results("SELECT $qcols FROM $wpdb->users ORDER BY display_name");
        }
    }
}
