<?php

namespace GianismMastodon;

use Gianism\Pattern\Singleton;
use Noellabo\OAuth2\Client\Provider\Mastodon as MastodonAuth;

class InstanceRegistry extends Singleton {

	public function get_provider( $url, $client_name, $redirect_uri, $website ) {
		$domain = $this->get_valid_domain( $url );
		if ( false === $domain ) {
			throw new \Exception( 'Invalid instance url' );
		}
		$client_info = $this->get_client_info( $domain );

		$client_info['domain']      = $domain;
		$client_info['appName']     = $client_name;
		$client_info['redirectUri'] = $redirect_uri;
		$client_info['website']     = $website;

		$provider = new MastodonAuth( $client_info );

		$params = $provider->getRegenerateParams();
		if ( ! empty( $params['clientId'] ) ) {
			$client_info['clientId']     = $params['clientId'];
			$client_info['clientSecret'] = $params['clientSecret'];
			$this->update_client_info( $domain, $client_info );
		}
		return $provider;
	}

	private function update_client_info( $domain, $params ) {
		global $wpdb;

		if ( empty( $domain ) ) {
			return false;
		}
		$option_keys = [
			'clientId',
			'clientSecret',
			'title',
			'icon',
			'groupId',
		];

		$options           = array_intersect_key( array_merge( $params, $this->get_instance_info( $domain ) ), array_flip( $option_keys ) );
		$options['domain'] = $domain;

		return $wpdb->replace( $wpdb->prefix . 'wpgmastodon_instances', $options );
	}

	public function get_all_client_info() {
		global $wpdb;

		foreach ( $wpdb->get_results( "SELECT domain, clientId, clientSecret, title, icon, groupId FROM {$wpdb->prefix}wpgmastodon_instances", ARRAY_A ) as $row ) {
			$client_info_list[ $row['domain'] ] = $row;
		}
		return $client_info_list;
	}

	public function get_client_info( $domain ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT clientId, clientSecret, title, icon, groupId FROM {$wpdb->prefix}wpgmastodon_instances WHERE domain=%s", $domain
			), ARRAY_A
		);
	}

	public function get_instance_info( $domain ) {
		$response = wp_remote_get( esc_url_raw( $domain ) . '/api/v1/instance' );
		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'WP_Error: ' . $response->get_error_message() );
		} elseif ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			throw new \Exception( 'Invalid API response:' . wp_remote_retrieve_response_message( $response ) );
		}
		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	public function get_valid_domain( $url ) {
		$url = trim( $url );
		if ( preg_match( '/^(?:@?[^@:]+)?@([^@]+)$/', $url, $matches ) ) {
			return 'https://' . $matches[1];
		} elseif ( substr_count( $url, '@' ) > 0 ) {
			return false;
		}
		if ( preg_match( '#^(?:(?:(.+?):)?(?://)?)?(?:[^@/]+@)?((?:[A-Za-z0-9][A-Za-z0-9\-]{1,61}[A-Za-z0-9]\.)+[A-Za-z]+)#', $url, $matches ) ) {
			$scheme = empty( $matches[1] ) ? 'https' : $matches[1];
			if ( 'https' == $scheme ) {
				return $scheme . '://' . $matches[2];
			}
		}
		return false;
	}
}
