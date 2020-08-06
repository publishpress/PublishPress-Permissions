<?php
namespace PublishPress\Permissions\Import\DB;

class DatabaseSetup
{
    function __construct($last_db_ver) {
        require_once(PRESSPERMIT_IMPORT_ABSPATH . '/db-config.php');

        if (is_multisite()) {
            add_action('switch_blog', [$this, 'actMultisiteSupport']);
        }

        self::updateSchema($last_db_ver);
    }

    function actMultisiteSupport()
    {
        require(PRESSPERMIT_IMPORT_ABSPATH . '/db-config.php');
    }

    private static function updateSchema($last_db_ver)
    {
        global $wpdb;

        $charset_collate = '';

        if (!empty($wpdb->charset))
            $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
        if (!empty($wpdb->collate))
            $charset_collate .= " COLLATE $wpdb->collate";

        // note: dbDelta requires two spaces after PRIMARY KEY, no spaces between KEY columns

        // import runs table def 
        $tabledefs = "CREATE TABLE $wpdb->ppi_runs (
        ID int(10) NOT NULL auto_increment,
        import_type varchar(32) NOT NULL,
        import_date datetime NOT NULL default '0000-00-00 00:00:00',
        num_imported bigint(20) NOT NULL,
        num_errors varchar(32) NOT NULL,
            PRIMARY KEY  (ID),
            KEY ppi_import_type (import_type,import_date) )
            $charset_collate
        ;
        ";

        // imported table def 
        $tabledefs .= "CREATE TABLE $wpdb->ppi_imported (
        ID bigint(20) NOT NULL auto_increment,
        run_id int(10) NOT NULL,
        source_tbl tinyint NOT NULL DEFAULT 0,
        source_id bigint(20) unsigned NOT NULL,
        rel_id bigint(20) unsigned NOT NULL DEFAULT 0,
        import_tbl tinyint NOT NULL DEFAULT 0,
        import_id bigint(20) unsigned NOT NULL,
        site bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (ID),
            KEY ppi_src_k (source_tbl,source_id,rel_id,site),
            KEY ppi_import_k (import_tbl,import_id,site) )
            $charset_collate
        ;
        ";

        // errors table def 
        $tabledefs .= "CREATE TABLE $wpdb->ppi_errors (
        ID bigint(20) NOT NULL auto_increment,
        run_id int(10) NOT NULL,
        source_tbl varchar(64) NOT NULL,
        source_id bigint(20) unsigned NOT NULL,
        err_code smallint(6) NOT NULL,
        err_descript varchar(255) NOT NULL,
            PRIMARY KEY  (ID),
            KEY ppi_error (run_id,source_tbl,source_id) )
            $charset_collate
        ;
        ";

        require_once(PRESSPERMIT_CLASSPATH . '/DB/DatabaseSetup.php');
                
        // apply all table definitions
        \PublishPress\Permissions\DB\DatabaseSetup::dbDelta($tabledefs);
    }
}
