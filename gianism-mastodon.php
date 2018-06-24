<?php

/*
Plugin Name: Gianism Mastodon
Plugin URI: https://noellabo.jp/gianism-mastodon/
Description: This plugin add mastodon to Gianism.
Author: noellabo
Version: 0.1.0
License: GPL 2.0 or later
Author URI: https://noellabo.jp
*/

defined( 'ABSPATH' ) or die();

require_once 'vendor/autoload.php';

load_plugin_textdomain( 'gimastodon', false, __DIR__ . '/languages' );

require __DIR__ . '/functions.php';

register_activation_hook(
	__FILE__, function() {
		gianism_mastodon_db_init();
	}
);

/**
 * Show error message.
 */
add_action(
	'plugins_loaded', function() {
		if ( ! defined( 'GIANISM_VERSION' ) || ! version_compare( GIANISM_VERSION, '3.0.0', '>=' ) ) {
			add_action(
				'admin_notices', function() {
					printf( '<div class="error">%s</div>', __( 'This plugin requires Gianism 3.0', 'gimastodon' ) );
				}
			);
		}
		gianism_mastodon_update_db_check();
	}
);

/**
 * Register service
 */
add_filter(
	'gianism_additional_service_classes', function( $services ) {
		require __DIR__ . '/includes/Mastodon.php';
		$services['mastodon'] = 'GianismMastodon\\Mastodon';
		return $services;
	}
);
