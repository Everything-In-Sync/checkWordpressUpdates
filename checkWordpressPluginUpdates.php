<?php
/**
 * Plugin Name: Check Wordpress Pluggin Updates
 * Plugin URI: 
 * Description: Goes through all the plugins and checks if they have updates then sends an email to the admin.
 * Version: 1.0
 * Author: Robert Kososki
 * Author URI: https://robertkososki.com
 * License: GPL2
 */



if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function check_plugin_updates_and_notify() {
    $admin_email = sanitize_email( get_option( 'admin_email' ) );
    if ( empty( $admin_email ) ) {
        error_log( 'Admin email is not set or invalid.' );
        return;
    }
    
    $update_plugins = get_site_transient( 'update_plugins' );
    $plugins_to_update = [];

    if ( isset( $update_plugins->response ) && ! empty( $update_plugins->response ) ) {
        foreach ( $update_plugins->response as $plugin_slug => $plugin_data ) {
            $plugins_to_update[] = sanitize_text_field( $plugin_data->slug );
        }

        $subject = 'Plugin Update Notification';
        $message = 'The following plugins need updates:' . "\n\n" . esc_html( implode( "\n", $plugins_to_update ) ) . "\n\n" . 'Please click this link to update: ' . esc_url( get_site_url() . '/wp-admin/plugins.php' );

        wp_mail( $admin_email, sanitize_text_field( $subject ), $message );

        // Send data to the aggregator
        $updates = array(
            'site' => get_site_url(),
            'updates' => $plugins_to_update
        );
    } else {
        $subject = 'Plugin Update Notification';
        $message = 'No updates are needed for your plugins.';
        wp_mail( $admin_email, sanitize_text_field( $subject ), esc_html( $message ) );

        // Send data to the aggregator indicating no updates
        $updates = array(
            'site' => get_site_url(),
            'updates' => 'No updates needed'
        );
    }

    // Now send the data to the aggregator endpoint
    wp_remote_post( 'https://sandhillsgeeks.biz/pluginUpdates/pluginUpdatesAggregator.php', array(
        'body' => json_encode( $updates ),
        'headers' => array( 'Content-Type' => 'application/json' )
    ) );
}

function plugin_update_notifier_schedule() {
    if ( ! wp_next_scheduled( 'plugin_update_notifier_event' ) ) {
        // Schedule the event for 2:00 AM server time
        $timestamp = strtotime( '07:00:00' ); // Today at 2:00 AM
        if ( $timestamp <= time() ) {
            $timestamp = strtotime( 'tomorrow 07:00:00' ); // Tomorrow at 2:00 AM if it's already past 2:00 AM
        }
        wp_schedule_event( $timestamp, 'daily', 'plugin_update_notifier_event' );
    }
}


add_action( 'wp', 'plugin_update_notifier_schedule' );

function plugin_update_notifier_unschedule() {
    $timestamp = wp_next_scheduled( 'plugin_update_notifier_event' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'plugin_update_notifier_event' );
    }
}
register_activation_hook( __FILE__, 'plugin_update_notifier_schedule' );
register_deactivation_hook( __FILE__, 'plugin_update_notifier_unschedule' );

add_action( 'plugin_update_notifier_event', 'check_plugin_updates_and_notify' );


//------Begin Plugin Updates Aggregator------
$updates = array(
    'site' => get_site_url(),
    'updates' => $plugins_to_update // or 'No updates needed'
);
$response = wp_remote_post('https://sandhillsgeeks.biz/pluginUpdates/pluginUpdatesAggregator.php', array(
    'body' => json_encode($updates),
    'headers' => array('Content-Type' => 'application/json')
));



