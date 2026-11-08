<?php
/**
 * Uninstall routine.
 *
 * Member data is kept by default: losing a customer club list because the
 * plugin was removed by mistake is not acceptable. To delete everything,
 * define MCC_REMOVE_DATA_ON_UNINSTALL as true in wp-config.php before
 * uninstalling, or set the option mcc_remove_data_on_uninstall to 1.
 *
 * @package Mazino_Customer_Club
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- one-off cleanup while the plugin is being removed.

$mcc_should_remove = defined( 'MCC_REMOVE_DATA_ON_UNINSTALL' ) && MCC_REMOVE_DATA_ON_UNINSTALL;
$mcc_should_remove = $mcc_should_remove || (bool) get_option( 'mcc_remove_data_on_uninstall' );

if ( $mcc_should_remove ) {
	global $wpdb;

	$mcc_table = $wpdb->prefix . 'mcc_members';

	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $mcc_table ) );
}

delete_option( 'mcc_db_version' );
delete_option( 'mcc_remove_data_on_uninstall' );
