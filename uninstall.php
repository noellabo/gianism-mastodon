<?php

/*
 * Delete all data for Literally WordPress
 *
 * @package gianism-mastodon
 * @since 0.1.0
 */

// Check whether WordPress is initialized or not.
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

// Delete Option
delete_option( 'wpgmastodon_instances_table_version' );

$values = get_option( 'wp_gianism_option' );
if ( $values !== false ) {
	foreach ( $values as $key => $value ) {
		if ( substr( $key, 0, strlen( 'mastodon_' ) ) !== 'mastodon_' ) {
			$keep_values[ $key ] = $value;
		}
	}
	update_option( 'wp_gianism_option', $keep_values );
}

// Delete all user meta
global $wpdb;
$query = <<<EOS
    DELETE FROM {$wpdb->usermeta}
    WHERE meta_key LIKE '_wpgmastodon_%'
EOS;
$wpdb->query( $query );

// Drop table.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpgmastodon_instances" );
