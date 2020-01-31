<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

global $wpdb;

$prefix = (!empty($wpdb->base_prefix)) ? $wpdb->base_prefix : $wpdb->prefix;

// table names for PP-specific data; usually no reason to alter these
$wpdb->ppi_runs = $prefix . 'ppi_runs';
$wpdb->ppi_imported = $prefix . 'ppi_imported';
$wpdb->ppi_errors = $prefix . 'ppi_errors';
