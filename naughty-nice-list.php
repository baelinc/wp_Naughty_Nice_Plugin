<?php
/*
Plugin Name: Naughty and Nice List
Description: A festive list manager with Geofence, Passcode, Smart Profanity Filter, and FPP REST API. Includes GitHub Auto-Updates.
Version: 1.1
Author: Johnathan Evans
GitHub Plugin URI: https://github.com/baelinc/wp_Naughty_Nice_Plugin
Primary Branch: main
*/

if (!defined('ABSPATH')) exit;

// 1. Initialize the global variable for the update checker
global $myUpdateChecker;

// Define path to the library
$puc_file = plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';

if (file_exists($puc_file)) {
    require_once $puc_file;
    
    // Build the checker and assign it to the global variable
    $myUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/baelinc/wp_Naughty_Nice_Plugin/', 
        __FILE__, 
        'wp_Naughty_Nice_Plugin' 
    );

    $myUpdateChecker->setBranch('main');
}

// 2. Load the other plugin components
require_once plugin_dir_path(__FILE__) . 'admin-page.php';
require_once plugin_dir_path(__FILE__) . 'shortcode.php';

/**
 * Database Installation
 */
register_activation_hook(__FILE__, 'nnl_install');
function nnl_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'naughty_nice';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        child_name varchar(100) NOT NULL,
        list_type varchar(20) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Set default settings
    add_option('nnl_verify_method', 'none');
    add_option('nnl_passcode', 'SANTA2025');
    add_option('nnl_geo_radius', '5');
    add_option('nnl_bad_words', 'ass,asshole,bastard,bitch,blowjob,cock,cunt,dick,faggot,fuck,fuk,nigger,pussy,retard,shit,slut,twat,whore,f4ck,a$$,sh1t,p00p');
}

/**
 * Register REST API Route for FPP
 */
add_action('rest_api_init', function () {
    register_rest_route('santa/v1', '/list', array(
        'methods' => 'GET',
        'callback' => 'nnl_get_api_data',
        'permission_callback' => '__return_true', 
    ));
});

function nnl_get_api_data() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'naughty_nice';
    $nice = $wpdb->get_col($wpdb->prepare("SELECT child_name FROM $table_name WHERE list_type = %s ORDER BY id DESC LIMIT 5", 'Nice'));
    $naughty = $wpdb->get_col($wpdb->prepare("SELECT child_name FROM $table_name WHERE list_type = %s ORDER BY id DESC LIMIT 5", 'Naughty'));

    $data = [
        'nice' => $nice,
        'naughty' => $naughty,
        'timestamp' => current_time('mysql'),
        'summary_text' => 'NICE: ' . implode(', ', $nice) . ' | NAUGHTY: ' . implode(', ', $naughty)
    ];
    return new WP_REST_Response($data, 200);
}

if (!function_exists('nnl_calc_dist')) {
    function nnl_calc_dist($lat1, $lon1, $lat2, $lon2) {
        $r = 3959;
        $dLat = deg2rad($lat2-$lat1); 
        $dLon = deg2rad($lon2-$lon1);
        $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2)**2;
        return $r * 2 * atan2(sqrt($a), sqrt(1-$a));
    }
}
