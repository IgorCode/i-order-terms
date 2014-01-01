<?php
/**
 * @package IOrderTerms
 * @author Igor Jerosimic
 */

// exit if uninstall not called from WordPress
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}


/**
 * Removes column and index from database.
 *
 * @return void
 */
function i_order_terms_uninstall() {
	global $wpdb;

	// remove index
	$sql_index = "ALTER TABLE `{$wpdb->term_taxonomy}` DROP INDEX `custom_order_index`;";
	$wpdb->query( $sql_index );

	// remove column
	$sql_column = "ALTER TABLE `{$wpdb->term_taxonomy}` DROP COLUMN `custom_order`;";
	$wpdb->query( $sql_column );

	// remove options
	// NOTE: delete_option for local option, delete_site_option for global option
	delete_option( 'iorderterms.general' ); // old option name
	delete_option( 'iorderterms_general' );
}


if ( function_exists( 'is_multisite' ) && is_multisite() ) {
	// multisite

	global $wpdb;

	// remember current blog ID
	$current_blog = $wpdb->blogid;

	// get all blog ID's
	$sql_blogs = "SELECT blog_id FROM {$wpdb->blogs};";
	$blog_ids = $wpdb->get_col( $sql_blogs );
	foreach ( $blog_ids as $blog_id ) {
		switch_to_blog( $blog_id );
		i_order_terms_uninstall();
	}

	switch_to_blog( $current_blog );
} else {
	// single site

	i_order_terms_uninstall();
}
