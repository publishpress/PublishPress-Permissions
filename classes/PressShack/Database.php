<?php

namespace PressShack;

class Database
{
    /**
     * {@internal Missing Short Description}}
     *
     * {@internal Missing Long Description}}
     *
     * @param unknown_type $queries
     * @param unknown_type $execute
     * @return unknown
     * @since unknown
     *
     */
    public static function dbDelta($queries, $execute = true)
    {  // lifted from WP because forced inclusion of schema.php by PressPermit interferes with site creation
        global $wpdb;

        // Separate individual queries into an array
        if (!is_array($queries)) {
            $queries = explode(';', $queries);
            if ('' == $queries[count($queries) - 1]) array_pop($queries);
        }

        $cqueries = []; // Creation Queries
        $iqueries = []; // Insertion Queries
        $for_update = [];

        // Create a tablename index for an array ($cqueries) of queries
        foreach ($queries as $qry) {
            if (preg_match("|CREATE TABLE (?:IF NOT EXISTS )?([^ ]*)|", $qry, $matches)) {
                $cqueries[trim(strtolower($matches[1]), '`')] = $qry;
                $for_update[$matches[1]] = 'Created table ' . $matches[1];
            } else if (preg_match("|CREATE DATABASE ([^ ]*)|", $qry, $matches)) {
                array_unshift($cqueries, $qry);
            } else if (preg_match("|INSERT INTO ([^ ]*)|", $qry, $matches)) {
                $iqueries[] = $qry;
            } else if (preg_match("|UPDATE ([^ ]*)|", $qry, $matches)) {
                $iqueries[] = $qry;
            } else {
                // Unrecognized query type
            }
        }

        // Check to see which tables and fields exist
        if ($tables = $wpdb->get_col('SHOW TABLES;')) {
            // For every table in the database
            foreach ($tables as $table) {
                // If a table query exists for the database table...
                if (array_key_exists(strtolower($table), $cqueries)) {
                    // Clear the field and index arrays
                    unset($cfields);
                    unset($indices);
                    // Get all of the field names in the query from between the parens
                    preg_match("|\((.*)\)|ms", $cqueries[strtolower($table)], $match2);
                    $qryline = trim($match2[1]);

                    // Separate field lines into an array
                    $flds = explode("\n", $qryline);

                    // For every field line specified in the query
                    foreach ($flds as $fld) {
                        // Extract the field name
                        preg_match("|^([^ ]*)|", trim($fld), $fvals);
                        $fieldname = trim($fvals[1], '`');

                        // Verify the found field name
                        $validfield = true;
                        switch (strtolower($fieldname)) {
                            case '':
                            case 'primary':
                            case 'index':
                            case 'fulltext':
                            case 'unique':
                            case 'key':
                                $validfield = false;
                                $indices[] = trim(trim($fld), ", \n");
                                break;
                        }
                        $fld = trim($fld);

                        // If it's a valid field, add it to the field array
                        if ($validfield) {
                            $cfields[strtolower($fieldname)] = trim($fld, ", \n");
                        }
                    }

                    // Fetch the table column structure from the database
                    $tablefields = $wpdb->get_results("DESCRIBE {$table};");

                    // For every field in the table
                    foreach ($tablefields as $tablefield) {
                        // If the table field exists in the field array...
                        if (array_key_exists(strtolower($tablefield->Field), $cfields)) {
                            // Get the field type from the query
                            preg_match("|" . $tablefield->Field . " ([^ ]*( unsigned)?)|i", $cfields[strtolower($tablefield->Field)], $matches);
                            $fieldtype = $matches[1];

                            // Is actual field type different from the field type in query?
                            if ($tablefield->Type != $fieldtype) {
                                // Add a query to change the column type
                                $cqueries[] = "ALTER TABLE {$table} CHANGE COLUMN {$tablefield->Field} " . $cfields[strtolower($tablefield->Field)];
                                $for_update[$table . '.' . $tablefield->Field] = "Changed type of {$table}.{$tablefield->Field} from {$tablefield->Type} to {$fieldtype}";
                            }

                            // Get the default value from the array
                            //echo "{$cfields[strtolower($tablefield->Field)]}<br>";
                            if (preg_match("| DEFAULT '(.*)'|i", $cfields[strtolower($tablefield->Field)], $matches)) {
                                $default_value = $matches[1];
                                if ($tablefield->Default != $default_value) {
                                    // Add a query to change the column's default value
                                    $cqueries[] = "ALTER TABLE {$table} ALTER COLUMN {$tablefield->Field} SET DEFAULT '{$default_value}'";
                                    $for_update[$table . '.' . $tablefield->Field] = "Changed default value of {$table}.{$tablefield->Field} from {$tablefield->Default} to {$default_value}";
                                }
                            }

                            // Remove the field from the array (so it's not added)
                            unset($cfields[strtolower($tablefield->Field)]);
                        } else {
                            // This field exists in the table, but not in the creation queries?
                        }
                    }

                    // For every remaining field specified for the table
                    foreach ($cfields as $fieldname => $fielddef) {
                        // Push a query line into $cqueries that adds the field to that table
                        $cqueries[] = "ALTER TABLE {$table} ADD COLUMN $fielddef";
                        $for_update[$table . '.' . $fieldname] = 'Added column ' . $table . '.' . $fieldname;
                    }

                    // Index stuff goes here
                    // Fetch the table index structure from the database
                    $tableindices = $wpdb->get_results("SHOW INDEX FROM {$table};");

                    if ($tableindices) {
                        // Clear the index array
                        unset($index_ary);

                        // For every index in the table
                        foreach ($tableindices as $tableindex) {
                            // Add the index to the index data array
                            $keyname = $tableindex->Key_name;
                            $index_ary[$keyname]['columns'][] = ['fieldname' => $tableindex->Column_name, 'subpart' => $tableindex->Sub_part];
                            $index_ary[$keyname]['unique'] = ($tableindex->Non_unique == 0) ? true : false;
                        }

                        // For each actual index in the index array
                        foreach ($index_ary as $index_name => $index_data) {
                            // Build a create string to compare to the query
                            $index_string = '';
                            if ($index_name == 'PRIMARY') {
                                $index_string .= 'PRIMARY ';
                            } else if ($index_data['unique']) {
                                $index_string .= 'UNIQUE ';
                            }
                            $index_string .= 'KEY ';
                            if ($index_name != 'PRIMARY') {
                                $index_string .= $index_name;
                            }
                            $index_columns = '';
                            // For each column in the index
                            foreach ($index_data['columns'] as $column_data) {
                                if ($index_columns != '') $index_columns .= ',';
                                // Add the field to the column list string
                                $index_columns .= $column_data['fieldname'];
                                if ($column_data['subpart'] != '') {
                                    $index_columns .= '(' . $column_data['subpart'] . ')';
                                }
                            }
                            // Add the column list to the index create string
                            $index_string .= ' (' . $index_columns . ')';
                            if (!(($aindex = array_search($index_string, $indices)) === false)) {
                                unset($indices[$aindex]);
                            }
                        }
                    }

                    // For every remaining index specified for the table
                    foreach ((array)$indices as $index) {
                        // Push a query line into $cqueries that adds the index to that table
                        $cqueries[] = "ALTER TABLE {$table} ADD $index";
                        $for_update[$table . '.' . $fieldname] = 'Added index ' . $table . ' ' . $index;
                    }

                    // Remove the original table creation query from processing
                    unset($cqueries[strtolower($table)]);
                    unset($for_update[strtolower($table)]);
                } else {
                    // This table exists in the database, but not in the creation queries?
                }
            }
        }

        $allqueries = array_merge($cqueries, $iqueries);
        if ($execute) {
            foreach ($allqueries as $query) {
                $wpdb->query($query);
            }
        }

        return $for_update;
    }
}
