<?php
/*
* Plugin Name: Privacy Acceptance Tracker
* Plugin URI: https://www.webalchemy.it/wa-privacy-acceptance/
* Description: Tracks privacy acceptance on account creation and checkout, including IP address, date, and time
* Version: 1.0.4
* Author: WebAlchemy
* Author URI: http://www.webalchemy.it
* Text Domain: wa-privacy-acceptance

Privacy Acceptance Tracker is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Privacy Acceptance Tracker is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Privacy Acceptance Tracker. If not, see https://opensource.org/licenses/GPL-2.0.
*/

add_action( 'woocommerce_register_form', 'wapa_privacy_acceptance_checkbox' );
add_action( 'woocommerce_checkout_after_terms_and_conditions', 'wapa_privacy_acceptance_checkbox');
function wapa_privacy_acceptance_checkbox() {
    ?>
    <p class="form-row">
        <label class="woocommerce-form__label">
            <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox" name="privacy_acceptance" id="privacy_acceptance" /> 
            <span><?php _e( 'Dichiaro di aver letto e compreso la <a href="/privacy-policy" target="_blank">privacy policy</a> di questo sito', 'woocommerce' ); ?></span>
            <span class="required">*</span>
        </label>
    </p>
    <?php
}

add_action( 'woocommerce_register_post', 'wapa_privacy_acceptance_validation', 10, 3 );
function wapa_privacy_acceptance_validation( $username, $email, $validation_errors ) {
	if(is_user_logged_in() && wapa_privacy_accepted(get_current_user_id(), 'checkout')) {
        return;    
    }
    if ( ! isset( $_POST['privacy_acceptance'] ) ) {
        $validation_errors->add( 'privacy_acceptance_error', __( 'Privacy acceptance is required', 'woocommerce' ) );
    }

    return $validation_errors;
}

add_action( 'woocommerce_checkout_process', 'wapa_privacy_acceptance_checkout_validation' );
function wapa_privacy_acceptance_checkout_validation() {
    if ( ! isset( $_POST['privacy_acceptance'] ) ) {
        wc_add_notice( __( 'Please accept the privacy policy to continue', 'woocommerce' ), 'error' );
    }else{
        global $wpdb;

        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        if(rest_is_ip_address($ip)) {
            $data = array('user_id' => get_current_user_id(), 'ip' => $ip, 'type' => 'checkout');
            $wpdb->insert($wpdb->prefix . "wapaprivacyregistrations", $data);
        }
    }
}

add_action( 'woocommerce_created_customer', 'wapa_privacy_acceptance_registration', 10, 1);
function wapa_privacy_acceptance_registration($user_id) {
    global $wpdb;

    $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
    if(rest_is_ip_address($ip)) {
        $data = array('user_id' => $user_id, 'ip' => $ip, 'type' => 'registration');
        $wpdb->insert($wpdb->prefix . "wapaprivacyregistrations", $data);
    }
}

function wapa_privacy_accepted($user_id, $type) {
    global $wpdb;

    $results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix . "wapaprivacyregistrations wpa"." WHERE wpa.user_id = $user_id AND wpa.type = '$type'");
    return ($wpdb->last_error == '' && count($results) > 0);
}

add_action( 'admin_menu', 'wapa_privacy_acceptance_menu' );
function wapa_privacy_acceptance_menu() {
    add_menu_page( 'Privacy Acceptance Records', 'Privacy Acceptance', 'manage_options', 'privacy_acceptance_records', 'wapa_privacy_acceptance_records_page', 'dashicons-admin-site', 30 );
}

function wapa_privacy_acceptance_records_page() {
    if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    echo '<div class="wrap">';
    echo '<h1>Privacy Acceptance Records</h1>';

    global $wpdb;
    $table_name = $wpdb->prefix . "wapaprivacyregistrations";
    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY `time` DESC");

    if (is_plugin_active('user-registration/user-registration.php')) {
        $ur_query = "SELECT um.umeta_id as ID, um.meta_value AS ip, u.ID as user_id, u.user_registered as time, 'registration-ur' as type 
        FROM ".$wpdb->prefix."usermeta um 
        JOIN ".$wpdb->prefix."users u on um.user_id = u.ID 
        WHERE um.meta_key LIKE 'ur_user_ip'";
        $ur_results = $wpdb->get_results($ur_query);
        $results = array_merge($results, $ur_results);
    }

    echo '<div id="messagearea"></div>';
    echo '<table class="wp-list-table widefat striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>User ID</th>';
    echo '<th>Acceptance Date</th>';
    echo '<th>Acceptance IP</th>';
    echo '<th>Registration Type</th>';
    echo '<th>Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    if(count($results)>0){
        foreach($results as $row){
            echo '<tr>';
            echo '<td>'.(int)$row->ID.'</td>';
            echo '<td>'.(int)$row->user_id.'</td>';
            echo '<td>'.esc_textarea($row->time).'</td>';
            echo '<td>'.esc_textarea($row->ip).'</td>';
            echo '<td>'.esc_textarea($row->type).'</td>';
            echo '<td><button type="button" class="wapadelbutton" data-wapa-id="'.esc_attr($row->ID).'" data-wapa-type="'.esc_attr($row->type).'">Delete</button></td>';
            echo '</tr>';
        }
    }else{
        echo '<tr>';
        echo '<td colspan="3">No records found</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    echo '<input type="hidden" id="nonce" style="display: none;" value="'.wp_create_nonce("wapa_delete_nonce").'"/>';
    echo '<input type="hidden" id="ajaxlink" style="display: none;" value="'.admin_url('admin-ajax.php').'"/>';
}

function wapa_install () {
    global $wpdb;

    $table_name = $wpdb->prefix . "wapaprivacyregistrations"; 

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        `ID` bigint(20) NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) NOT NULL,
        `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `ip` varchar(20) NOT NULL,
        `type` varchar(20) NOT NULL,
        PRIMARY KEY (`ID`)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

register_activation_hook( __FILE__, 'wapa_install');

function wapa_admin_enqueue($hook) {
    if ('toplevel_page_privacy_acceptance_records' !== $hook) {
        return;
    }
    wp_enqueue_script('wapa_admin', plugin_dir_url(__FILE__) . '/admin.js');
}

add_action('admin_enqueue_scripts', 'wapa_admin_enqueue');

add_action("wp_ajax_wapa_delete", "wapa_delete");

function wapa_delete() {

    $result = array();
    if ( !wp_verify_nonce( $_REQUEST['nonce'], "wapa_delete_nonce")) {
        $result['type'] = "error";
        $result['message'] = "Wrong nonce";
        die(json_encode($result));
    }   

    if(!isset($_REQUEST['row_id'])) {
        $result['type'] = "error";
        $result['message'] = "Missing ID parameter";
        die(json_encode($result));
    }

    if(!isset($_REQUEST['row_type'])) {
        $result['type'] = "error";
        $result['message'] = "Missing type parameter";
        die(json_encode($result));
    }

    $row_id = sanitize_text_field($_REQUEST['row_id']);
    $row_type = $_REQUEST['row_type'];

    if(empty($row_id) || !is_numeric($row_id)) {
        $result['type'] = 'error';
        $result['message'] = 'No id received';
        die(json_encode($result));
    }

    $valid_types = array("registration", "checkout", "registration-ur");
    if(empty($row_type) || !in_array($row_type, $valid_types)) {
        $result['type'] = 'error';
        $result['message'] = 'No valid type received';
        die(json_encode($result));
    }

    global $wpdb;
    if($row_type === "registration-ur") {
        $wpdb->delete($wpdb->prefix . "usermeta", array('umeta_id' => (int)$row_id));
    } else {
        $wpdb->delete($wpdb->prefix . "wapaprivacyregistrations", array('ID' => (int)$row_id));
    }
    
    if($wpdb->last_error == '') {
        $result['type'] = 'success';
        $result['message'] = 'Row deleted!';
    } else {
        $result['type'] = 'error';
        $result['message'] = 'DB error: '. esc_textarea($wpdb->last_error);
    }

    die(json_encode($result));
}