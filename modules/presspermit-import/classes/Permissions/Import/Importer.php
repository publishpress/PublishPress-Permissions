<?php
namespace PublishPress\Permissions\Import;

class Importer
{
    private $table_codes = [];
    private $stop_time = 0;

    // set by this class, accessed by subclass
    protected $run_id;

    // set by subclass
    protected $completed = false;
    protected $total_imported = 0;
    protected $total_errors = 0;

    // set by subclass, accessed by UI
    var $import_types = [];
    var $sites_examined = 0;
    var $num_imported = [];
    var $timed_out = false;
    var $return_error;

    public function __construct($submission = false)
    {
        require_once(PRESSPERMIT_IMPORT_CLASSPATH . '/DB/SourceConfig.php');
        new DB\SourceConfig();

        $this->setTableCodes();
    }

    public static function handleSubmission()
    {
        if (!empty($_POST['pp_rs_import'])) {
            if (!current_user_can('pp_manage_settings'))
                wp_die(__('You are not allowed to manage Permissions settings', 'press-permit-core'));

            require_once(PRESSPERMIT_IMPORT_CLASSPATH . '/DB/RoleScoper.php');
            DB\RoleScoper::instance()->doImport();
        }

        if (!empty($_POST['pp_undo_imports'])) {
            if (!current_user_can('pp_manage_settings'))
                wp_die(__('You are not allowed to manage Permissions settings', 'press-permit-core'));

            $importer = new Importer();

            if (!empty($_REQUEST['run_id']))
                $importer->undoImport((int)$_REQUEST['run_id']);
            else
                $importer->undoAllImports();
        }
    }

    public function getTableCode($table)
    {
        return (isset($this->table_codes[$table])) ? $this->table_codes[$table] : 0;
    }

    protected function doImport($import_type)
    {
        global $wpdb;

        do_action('presspermit_importing', $import_type);

        $import_date = current_time('mysql', 1);
        $wpdb->query("INSERT INTO $wpdb->ppi_runs (import_type,import_date) VALUES('$import_type','$import_date')");
        $this->run_id = (int)$wpdb->insert_id;

        $this->num_imported = array_fill_keys(array_keys($this->import_types), 0);

        $max_time = ini_get('max_execution_time');

        if ($max_time < 10)
            $max_time = 10;

        if (defined('PPI_MAX_EXEC')) {
            if (PPI_MAX_EXEC && is_numeric(PPI_MAX_EXEC) && ($max_time > PPI_MAX_EXEC))
                $max_time = PPI_MAX_EXEC;
        }

        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'buffer_metagroup_id_%'");

        $this->stop_time = time() + $max_time - 2;
    }

    protected function updateCounts()
    {
        global $wpdb;

        $this->num_imported = array_intersect_key($this->num_imported, $this->import_types);
        $run_data = ['num_imported' => $this->total_imported, 'num_errors' => $this->total_errors];
        $where = ['ID' => $this->run_id];
        $wpdb->update($wpdb->ppi_runs, $run_data, $where);
    }

    protected function checkTimeout()
    {
        if ((time() >= $this->stop_time) && !defined('PPI_DISABLE_TIMEOUT')) {
            throw new Exception("PublishPress Permissions Import: approached max execution time");
        }
    }

    private function getIdColumn($table_name)
    {
        global $wpdb;

        switch ($table_name) {
            case $wpdb->options :
                return 'option_id';
                break;

            case $wpdb->pp_conditions :
            case $wpdb->ppc_roles:
                return 'assignment_id';
                break;

            case $wpdb->pp_groups:
            case $wpdb->base_prefix . 'pp_groups' :
                return 'ID';
                break;

            case $wpdb->ppc_exceptions:
                return 'exception_id';
                break;

            case $wpdb->ppc_exception_items:
                return 'eitem_id';
                break;
        };

        return false;
    }

    private function getTable($code)
    {
        return array_search($code, $this->table_codes);
    }

    private function undoImport($run_id)
    {
        global $wpdb;

        if (is_multisite() && is_main_site()) {
            $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs ORDER BY blog_id");
            $orig_blog_id = get_current_blog_id();
        } else {
            $blog_ids = [get_main_site_id()];
        }
        
        foreach ($blog_ids as $id) {
            if (count($blog_ids) > 1) {
                switch_to_blog($id);
            }

            $site_clause = (is_multisite()) ? "AND site = '$id'" : '';

            if ($import_tables = $wpdb->get_col("SELECT DISTINCT import_tbl FROM $wpdb->ppi_imported WHERE run_id = '$run_id' $site_clause")) {
                foreach ($import_tables as $import_tbl) {
                    $table_name = $this->getTable($import_tbl);

                    if ($id_col = $this->getIdColumn($table_name)) {
                        $wpdb->query("DELETE FROM `$table_name` WHERE $id_col IN ( SELECT import_id FROM $wpdb->ppi_imported WHERE run_id = '$run_id' AND import_tbl = '$import_tbl' $site_clause )");
                    }
                    $wpdb->query("UPDATE $wpdb->ppi_imported SET run_id = -{$run_id} WHERE run_id = '$run_id' AND import_tbl = '$import_tbl' $site_clause");
                }
            }

            // legacy db schema
            if ($wpdb->get_results("SHOW COLUMNS FROM $wpdb->ppi_imported LIKE 'import_table'")) {
                $import_tables = $wpdb->get_col("SELECT DISTINCT import_table FROM $wpdb->ppi_imported WHERE run_id = '$run_id' $site_clause");
                foreach ($import_tables as $table_name) {
                    if ($id_col = $this->getIdColumn($table_name)) {
                        $wpdb->query("DELETE FROM `$table_name` WHERE $id_col IN ( SELECT import_id FROM $wpdb->ppi_imported WHERE run_id = '$run_id' AND import_table = '$table_name' $site_clause )");
                    }
                    $wpdb->query("UPDATE $wpdb->ppi_imported SET run_id = -{$run_id} WHERE run_id = '$run_id' AND import_table = '$import_table' $site_clause");
                }
            }
        }

        if (count($blog_ids) > 1) {
            switch_to_blog($orig_blog_id);
        }
    }

    private function undoAllImports()
    {
        global $wpdb;

        if (is_multisite()) {
            $site_clause = (is_main_site()) ? "AND site > 0" : "AND site = %d";  // if on main site, will undo import for all sites
        } else {
            $site_clause = '';
        }

        $run_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT run_id FROM $wpdb->ppi_imported WHERE run_id > 0 $site_clause",
                get_current_blog_id()
            )
        );

        foreach ($run_ids as $run_id) {
            $this->undoImport($run_id);
        }
    }

    private function setTableCodes()
    {
        global $wpdb;

        $rs_netwide_groups = $wpdb->base_prefix . 'groups_rs';
        $pp_netwide_groups = $wpdb->base_prefix . 'pp_groups';

        $this->table_codes = [
            $wpdb->options => 1,
            $wpdb->groups_rs => 10,
            $wpdb->user2group_rs => 11,
            $rs_netwide_groups => 12,
            $wpdb->user2role2object_rs => 15,
            $wpdb->role_scope_rs => 16,
            $wpdb->pp_groups => 30,
            $wpdb->pp_group_members => 31,
            $pp_netwide_groups => 32,
            $wpdb->ppc_roles => 35,
            $wpdb->ppc_exceptions => 36,
            $wpdb->ppc_exception_items => 37,
        ];

        if (!empty($wpdb->pp_conditions)) {
            $this->table_codes[$wpdb->pp_conditions] = 26;
        }
    }

    function updateTablecodes()
    {
        global $wpdb;

        if ($wpdb->get_results("SHOW COLUMNS FROM $wpdb->ppi_imported LIKE 'source_table'")) {
            foreach ($this->table_codes as $table => $code) {
                if ($code < 30)
                    $wpdb->update($wpdb->ppi_imported, ['source_tbl' => $code], ['source_table' => $table]);
                else
                    $wpdb->update($wpdb->ppi_imported, ['import_tbl' => $code], ['import_table' => $table]);
            }
        }
    }
}
