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

        switch ($cols) {
            case 'id':
                $qcols = 'ID';
                break;
            case 'id_name':
                $qcols = "ID, user_login AS display_name";  // calling code assumes display_name property for user or group object
                break;
            case 'id_displayname':
                $qcols = "ID, display_name";
                break;
            case 'all':
                $qcols = "*";
                break;
            default:
                $qcols = $cols;
        }

        global $wpdb;

        $orderby = ($cols == 'id') ? '' : 'ORDER BY display_name';
        $qry = "SELECT $qcols FROM $wpdb->users $orderby";

        return ('id' == $cols) ? $wpdb->get_col($qry) : $wpdb->get_results($qry);
    }
}
