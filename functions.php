<?php

/**
 * Global functions for Gianism Mastodon.
 *
 * @package Gianism Mastodon
 * @since 0.1.0
 * @author noellabo
 */

const GIANISM_MASTODON_INSTANCES_TABLE_NAME         = 'wpgmastodon_instances';
const GIANISM_MASTODON_INSTANCES_TABLE_VERSION_NAME = 'wpgmastodon_instances_table_version';
const GIANISM_MASTODON_INSTANCES_TABLE_VERSION      = '1.0';

function gianism_mastodon_db_init() {
	global $wpdb;

	$instances_table_name = $wpdb->prefix . GIANISM_MASTODON_INSTANCES_TABLE_NAME;

	if ( $wpdb->get_var( $wpdb->prepare( 'show tables like %s', $instances_table_name ) ) != $instances_table_name
		|| get_option( GIANISM_MASTODON_INSTANCES_TABLE_VERSION_NAME ) !== GIANISM_MASTODON_INSTANCES_TABLE_VERSION ) {
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $instances_table_name (
			domain text NOT NULL,
			clientId text,
			clientSecret text,
			title text,
			icon text,
			groupId tinyint,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (domain(255))
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		update_option( GIANISM_MASTODON_INSTANCES_TABLE_VERSION_NAME, GIANISM_MASTODON_INSTANCES_TABLE_VERSION );
	}

}

function gianism_mastodon_update_db_check() {
	if ( get_option( GIANISM_MASTODON_INSTANCES_TABLE_VERSION_NAME ) !== GIANISM_MASTODON_INSTANCES_TABLE_VERSION ) {
		gianism_mastodon_db_init();
	}
}
