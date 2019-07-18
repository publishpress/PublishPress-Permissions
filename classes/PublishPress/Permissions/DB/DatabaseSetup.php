<?php

namespace PublishPress\Permissions\DB;

class DatabaseSetup
{
    function __construct($last_db_ver = false)
    {
        self::updateSchema();
    }

    private static function updateSchema()
    {
        global $wpdb;

        $charset_collate = '';

        if (!empty($wpdb->charset))
            $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";

        if (!empty($wpdb->collate))
            $charset_collate .= " COLLATE $wpdb->collate";

        // note: dbDelta requires two spaces after PRIMARY KEY, no spaces between KEY columns

        // Groups table def 
        $tabledefs = "CREATE TABLE $wpdb->pp_groups (
         ID bigint(20) NOT NULL auto_increment,
         group_name text NOT NULL,
         group_description text NOT NULL,
         metagroup_id varchar(64) NOT NULL default '',
         metagroup_type varchar(32) NOT NULL default '',
            PRIMARY KEY  (ID),
            KEY pp_grp_metaid (metagroup_type,metagroup_id) )
            $charset_collate
        ;
        ";

        // User2Group table def
        $tabledefs .= "CREATE TABLE $wpdb->pp_group_members (
         group_id bigint(20) unsigned NOT NULL default '0',
         user_id bigint(20) unsigned NOT NULL default '0',
         member_type varchar(32) NOT NULL default 'member',
         status varchar(32) NOT NULL default 'active',
         add_date_gmt datetime NOT NULL default '0000-00-00 00:00:00',
         date_limited tinyint(2) NOT NULL default '0',
         start_date_gmt datetime NOT NULL default '0000-00-00 00:00:00',
         end_date_gmt datetime NOT NULL default '2035-01-01 00:00:00',
            KEY pp_group_user (group_id,user_id),
            KEY pp_member_status (status,member_type),
            KEY pp_member_date (start_date_gmt,end_date_gmt,date_limited,user_id,group_id) )
            $charset_collate
        ;
        ";

        /*  ppc_roles: 
        // note: dbDelta requires two spaces after PRIMARY KEY, no spaces between KEY columns
        //
            agent_type: user / pp_group / bp_group / etc.
            agent_id: ID of user or group who the role is applied to
            role_name potentially contains "base_rolename:source_name:type_or_taxonomy:attribute:condition"
                                                (<=40)     (<=32)         (<=32)        (<=32)    (<=32)
            assigner_id: ID of user who stored the role
        */
        $tabledefs .= "CREATE TABLE $wpdb->ppc_roles (
         assignment_id bigint(20) unsigned NOT NULL auto_increment,
         agent_id bigint(20) unsigned NOT NULL,
         agent_type varchar(32) NOT NULL,
         role_name varchar(176) NOT NULL,
         assigner_id bigint(20) unsigned NOT NULL default '0',
            PRIMARY KEY  (assignment_id),
            KEY pp_role2agent (agent_type,agent_id,role_name),
            KEY pp_rolename (role_name) )
            $charset_collate
        ;
        ";

        /* ppc_exceptions:

            agent_type: user / pp_group / bp_group / etc.
            agent_id: ID of user or group who the exception is applied to
            for_item_source: data source of items which are affected by the exception
            for_item_type: which post or taxonomy type (post / page / category / etc) is affected by the exception (nullstring means all types that use specified for_item_source)
            for_item_status: posts of this status (or other property) are affected by the exception. Storage is "post_status:private" (nullstring means all stati)
            operation: read / edit / assign / parent / revise / etc.
            mod_type: include / exclude / additional
            via_item_source: data source of the item which triggers the exception (if for_item_type is page, via_item_source could be term)
            via_item_type: post type or taxonomy which triggers the exception (Needed for when post exceptions are based on term assignment. Nullstring means redundant / not applicable)
            assigner_id: ID of user who stored the exception
        */

        // KEY pp_exc (agent_id,agent_type,operation,via_item_source,via_item_type,for_item_source,for_item_type),

        $tabledefs .= "CREATE TABLE $wpdb->ppc_exceptions (
         exception_id bigint(20) unsigned NOT NULL auto_increment,
         agent_type varchar(32) NOT NULL,
         agent_id bigint(20) unsigned NOT NULL,
         for_item_source varchar(32) NOT NULL,
         for_item_type varchar(32) NOT NULL default '',
         for_item_status varchar(127) NOT NULL default '',
         operation varchar(32) NOT NULL,
         mod_type varchar(32) NOT NULL,
         via_item_source varchar(32) NOT NULL,
         via_item_type varchar(32) NOT NULL default '',
         assigner_id bigint(20) unsigned NOT NULL default '0',
            PRIMARY KEY  (exception_id),
            KEY pp_exc (agent_type,agent_id,exception_id,operation,for_item_type),
            KEY pp_exc_mod (mod_type),
            KEY pp_exc_status (for_item_status) )
            $charset_collate
        ;
        ";

        /* ppc_exception_items:
        
            exception_id: foreign key to ppc_exceptions
            item_id: post ID or term_id
            assign_for: exception applies to item or its children?
            inherited_from: eitem_id value from propagating parent
            assigner_id: ID of user who stored the exception item
        */
        $tabledefs .= "CREATE TABLE $wpdb->ppc_exception_items (
         eitem_id bigint(20) unsigned NOT NULL auto_increment,
         exception_id bigint(20) unsigned NOT NULL,
         item_id bigint(20) unsigned NOT NULL,
         assign_for enum('item', 'children') NOT NULL default 'item',
         inherited_from bigint(20) unsigned NULL default '0',
         assigner_id bigint(20) unsigned NOT NULL default '0',
            PRIMARY KEY  (eitem_id),
            KEY pp_exception_item (item_id,exception_id,assign_for),
            KEY pp_eitem_fk (exception_id) )
            $charset_collate
        ;
        ";

        require_once(PRESSPERMIT_CLASSPATH_COMMON . '/Database.php');
        
        // apply all table definitions
        \PressShack\Database::dbDelta($tabledefs);
    } //end updateSchema function
}
