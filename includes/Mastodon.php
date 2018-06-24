<?php

namespace GianismMastodon;

use Gianism\Service\AbstractService;
use Gianism\Service\NoMailService;
use GianismMastodon\InstanceRegistry;
use GianismMastodon\AbstractMultiInstanceService;

/**
 * mastodon Controller
 *
 * @package gianism-mastodon
 * @since 0.1.0
 * @author Takeshi Umeda
 */
class Mastodon extends NoMailService {

	/**
	 * URL prefix
	 *
	 * @var string
	 */
	public $url_prefix = 'mastodon-auth';

	/**
	 * Verbose service name
	 *
	 * @var string
	 */
	public $verbose_service_name = 'Mastodon';

	/**
	 * mastodon's allow instance list
	 *
	 * @var array
	 */
	public $mastodon_app_name = '';

	/**
	 * mastodon's allow instance list
	 *
	 * @var string
	 */
	public $mastodon_login_button_list = '*';

	/**
	 * mastodon's deny instance list
	 *
	 * @var string
	 */
	public $mastodon_deny_instance_list = '';


	/**
	 * User meta key for mastodon accounts array
	 *
	 * @var string
	 */
	public $umeta_accounts = '_wpg_mastodon_accounts';

	/**
	 * Option key to copy
	 *
	 * @var array
	 */
	protected $option_keys = [
		'mastodon_enabled'            => false,
		'mastodon_app_name'           => '',
		'mastodon_login_button_list'  => '*',
		'mastodon_deny_instance_list' => '',
	];

	/**
	 * @var Noellabo\OAuth2\Client\Provider\Mastodon
	 */
	protected $cached_provider = null;

	/**
	 * mastodon constructor.
	 *
	 * @param array $argument
	 */
	public function __construct( array $argument = [] ) {
		$this->option_keys['mastodon_app_name'] = get_bloginfo( 'name' );
		parent::__construct( $argument );
		// Filter rewrite name
		add_filter(
			'gianism_filter_service_prefix', function( $prefix ) {
				if ( 'mastodon-auth' == $prefix ) {
					$prefix = 'mastodon';
				}
				return $prefix;
			}
		);
		add_action(
			'admin_enqueue_scripts', [ $this, 'print_script' ]
		);
	}

	public function print_script() {
		wp_enqueue_script( 'font-awesome', 'https://use.fontawesome.com/releases/v5.0.13/js/all.js' );
		wp_script_add_data( 'font-awesome', array( 'integrity', 'crossorigin' ), array( 'sha384-xymdQtn1n3lH2wcu0qhcdaOpQwyoarkgLVxC/wZ5q7h9gHtxICrpcaSUfygqZGOe', 'anonymous' ) );
		wp_enqueue_style( 'gianism-mastodon-style', plugins_url( 'assets/css/gianism-mastodon-style.css', dirname( __FILE__ ) ) );
	}

	/**
	 * Detect if user is connected to this service
	 *
	 * @param int    $user_id
	 * @param string $account_id
	 *
	 * @return bool
	 */
	public function is_connected( $user_id, $account_id = '' ) {
		$accounts = get_user_meta( $user_id, $this->umeta_accounts, false );
		var_dump( $accounts );
		if ( in_array( $account_id, $accounts ) ) {
			return $account_id;
		} else {
			return false;
		}
	}

	/**
	 * Disconnect user from this service
	 *
	 * @param int    $user_id
	 * @param string $account_id
	 *
	 * @return mixed
	 */
	public function disconnect( $user_id, $account_id = '' ) {
		$accounts = get_user_meta( $user_id, $this->umeta_accounts, false );
		if ( in_array( $account_id, $accounts ) ) {
			delete_user_meta( $user_id, $this->umeta_accounts, $account_id );
		}
	}


	/**
	 * Handle callback request
	 *
	 * This function must exit at last.
	 *
	 * @param string $action
	 *
	 * @return void
	 */
	protected function handle_default( $action ) {
		// Get common values
		$redirect_url = $this->session->get( 'redirect_to' );
		$saved_state  = $this->session->get( 'state' );
		$state        = $this->input->get( 'state' );
		$code         = $this->input->get( 'code' );
		switch ( $action ) {
			case 'login':
				try {
					// マストドンログイン情報を取得
					$user_info = $this->get_user_profile( $code, $state, $saved_state );
					$user_id   = $this->get_meta_owner( $this->umeta_accounts, $user_info->getAcct() );
					if ( ! $user_id ) {
						$this->test_user_can_register();
						$email = $user_info->getPseudoEmail();
						if ( email_exists( $email ) ) {
							throw new \Exception( $this->duplicate_account_string() );
						}
						// Check user name
						$user_name = $this->valid_username_from_mail( $email );
						$user_id   = wp_create_user( $user_name, wp_generate_password(), $email );
						if ( is_wp_error( $user_id ) ) {
							throw new \Exception( $this->registration_error_string() );
						}
						// Update extra information
						add_user_meta( $user_id, $this->umeta_accounts, $user_info->getAcct() );
						update_user_meta( $user_id, 'nickname', $user_info->getDisplayname() );
						$this->db->update(
							$this->db->users,
							array(
								'display_name' => $user_info->getDisplayname(),
							),
							array( 'ID' => $user_id ),
							array( '%s' ),
							array( '%d' )
						);
						// Password is unknown
						$this->user_password_unknown( $user_id );
						$this->hook_connect( $user_id, $user_info->toArray(), true );
						$this->welcome( $user_info->getDisplayname() );
					}
					wp_set_auth_cookie( $user_id, true );
					$redirect_url = $this->filter_redirect( $redirect_url, 'login' );
				} catch ( \Exception $e ) {
					$this->auth_fail( $e->getMessage() );
					$redirect_url = wp_login_url( $redirect_url );
					$redirect_url = $this->filter_redirect( $redirect_url, 'login-failure' );
				}
				wp_redirect( $redirect_url );
				exit;
				break;
			case 'connect':
				try {
					// Is user logged in?
					if ( ! is_user_logged_in() ) {
						throw new \Exception( $this->_( 'You must be logged in' ) );
					}
					// Get user info
					$user_info = $this->get_user_profile( $code, $state, $saved_state );
					$owner     = $this->get_meta_owner( $this->umeta_accounts, $user_info->getAcct() );
					if ( $owner ) {
						throw new \Exception( $this->duplicate_account_string() );
					}
					// O.k.
					add_user_meta( get_current_user_id(), $this->umeta_accounts, $user_info->getAcct() );
					$this->hook_connect( get_current_user_id(), $user_info->toArray(), false );
					$this->welcome( (string) $user_info->getDisplayname() );
					$redirect_url = $this->filter_redirect( $redirect_url, 'connect' );
				} catch ( \Exception $e ) {
					$this->auth_fail( $e->getMessage() );
					$redirect_url = $this->filter_redirect( $redirect_url, 'connect-failure' );
				}
				wp_redirect( $redirect_url );
				exit;
				break;
			default:
				/**
				 * @see \Gianism\Service\Facebook
				 */
				do_action(
					'gianism_extra_action', $this->service_name, $action, [
						'redirect_to' => $redirect_url,
					]
				);
				$this->input->wp_die( sprintf( $this->_( 'Sorry, but wrong access. Please go back to <a href="%s">%s</a>.' ), home_url( '/' ), get_bloginfo( 'name' ) ), 500, false );
				break;
		}
	}

	/**
	 * Handle connect
	 *
	 * @param \WP_Query $wp_query
	 */
	protected function handle_connect( \WP_Query $wp_query ) {
		$this->session->write( 'instance_url', $this->input->get( 'instance_url' ) );
		parent::handle_connect( $wp_query );
	}

	/**
	 * Handle disconnect
	 *
	 * @param \WP_Query $wp_query
	 */
	protected function handle_disconnect( \WP_Query $wp_query ) {
		try {
			$redirect_url = $this->input->get( 'redirect_to' ) ?: admin_url( 'profile.php' );
			$acct         = $this->input->get( 'acct' );
			/**
			 * Filter redirect URL
			 */
			$redirect_url = apply_filters( '', $redirect_url, $this->service_name, $wp_query );
			// Is user logged in?
			if ( ! is_user_logged_in() ) {
				throw new \Exception( $this->_( 'You must be logged in.' ) );
			}
			// Has connected
			if ( ! $this->is_connected( get_current_user_id(), $acct ) ) {
				throw new \Exception( sprintf( $this->_( 'Your account is not connected with %s' ), $acct ) );
			}
			// O.K.
			$this->disconnect( get_current_user_id(), $acct );
			$this->add_message( sprintf( $this->_( 'Your account is now unlinked from %s.' ), $acct ) );
			// Redirect
			wp_redirect( $this->filter_redirect( $redirect_url, 'disconnect' ) );
			exit;
		} catch ( \Exception $e ) {
			$this->input->wp_die( $e->getMessage() );
		}
	}

	/**
	 * Show connect button on profile page
	 *
	 * @param \WP_User $user
	 *
	 * @return void
	 */
	public function profile_connect( \WP_User $user ) {
		/**
		 * Filtering message on connection table
		 *
		 * @filter gianism_connect_message
		 * @param string $message
		 * @param string $service
		 * @param bool $is_connected
		 *
		 * @return string
		 */
		$disconnected_message = apply_filters( 'gianism_connect_message', $this->connection_message( 'disconnected' ), $this->service_name, false );
		$connected_message    = apply_filters( 'gianism_connect_message', $this->connection_message( 'connected' ), $this->service_name, true );

		$html            = <<<EOS
<tr>
    <th><i class="fab fa-mastodon wpg-mastodon-fa-color"></i> {$this->verbose_service_name}</th>
    <td class="wpg-connector {$this->service_name}">
%s
%s
    </td><!-- .wpg-connector -->
</tr>
EOS;
		$html_connect    = <<<EOS
        <p class="description desc-disconnected"><i class="lsf lsf-login"></i> %s</p>
        <p class="button-wrap">%s</p>
EOS;
		$html_disconnect = <<<EOS
        <p class="description desc-connected"><i class="lsf lsf-check"></i> %s</p>
        <p class="button-wrap">%s</p>
EOS;
		// connect
		$buttons            = [];
		$instance_info_list = $this->get_instance_info_list();
		foreach ( $instance_info_list as $instance_info ) {
			$buttons[] = $this->connect_button( '', $instance_info );
		}
		$button       = implode( '<br>', $buttons );
		$html_connect = sprintf( $html_connect, $disconnected_message, $button );

		// disconnect
		$accounts = get_user_meta( $user->ID, $this->umeta_accounts, false );
		if (
		//          $this->is_pseudo_mail( $user->user_email ) ||
			empty( $accounts ) ) {
			$html_disconnect = '';
		} else {
			$buttons = [];
			foreach ( $accounts as $account ) {
				$buttons[] = $this->disconnect_button( '', $account );
			}
			$button          = implode( '<br>', $buttons );
			$html_disconnect = sprintf( $html_disconnect, $connected_message, $button );
		}

		printf( $html, $html_connect, $html_disconnect );
	}

	/**
	 * Connection message
	 *
	 * Overriding this function, you can
	 * customize connection message
	 *
	 * @param string $context
	 *
	 * @return string
	 */
	public function connection_message( $context = 'connected' ) {
		switch ( $context ) {
			case 'connected':
				return sprintf( $this->_( 'Your account is already connected to the %s account below. Please select an account to disconnect.' ), $this->verbose_service_name );
				break;
			default: // Disconnected
				return sprintf( $this->_( 'Connecting with %1$s, you can login with %2$s via %1$s without password or email address.' ), $this->verbose_service_name, get_bloginfo( 'name' ) );
				break;
		}
	}

	/**
	 * Returns link to filter
	 *
	 * @param string $markup
	 * @param string $href
	 * @param string $text
	 * @param bool $is_register
	 *
	 * @return string
	 */
	public function filter_link( $markup, $href, $text, $is_register = false, $context = '', array $instance_info = [] ) {
		/**
		 * gianism_link_html
		 *
		 * @package Gianism
		 * @since 3.0.4 Add context parameter.
		 * @param string $markup        Final markup.
		 * @param string $href          Link's attribute.
		 * @param string $text          Link text.
		 * @param bool   $is_register   Is register form.
		 * @param string $service       Service name. facebook, twitter, etc.
		 * @param string $context       Context. Default empty.
		 * @param array  $instance_info Instance url, name, and others. Default empty.
		 */
		$link = apply_filters( 'gianism_link_html', $markup, $href, $text, $is_register, $this->service_name, $context, $instance_info );
		return $link;
	}

	/**
	 * Create common button
	 *
	 * @param string $text        Text to display.
	 * @param string $href        Link's href.
	 * @param bool   $icon_name   Icon name of LSF.
	 * @param array  $class_names Class name for this button.
	 * @param array  $attributes  Attributes for link.
	 * @param string $context     Display context. Default empty.
	 *
	 * @return string
	 */
	public function button( $text, $href, $icon_name = true, array $class_names = [ 'wpg-button' ], array $attributes = [], $context = '' ) {
		// Create icon
		if ( true === $icon_name ) {
			$icon = "<i class=\"lsf lsf-{$this->service_name}\"></i> ";
		} elseif ( is_string( $icon_name ) && '<' == $icon_name[0] ) {
			$icon = $icon_name;
		} elseif ( is_string( $icon_name ) ) {
			$icon = "<i class=\"lsf lsf-{$icon_name}\"></i> ";
		} else {
			$icon = '';
		}
		$class_attr = implode(
			' ', array_map(
				function ( $attr ) {
					return esc_attr( $attr );
				}, $class_names
			)
		);
		$atts       = [];
		foreach ( $attributes as $key => $value ) {
			switch ( $key ) {
				case 'onclick':
					// Do nothing
					break;
				default:
					$key = 'data-' . $key;
					break;
			}
			$value  = esc_attr( $value );
			$atts[] = "{$key}=\"{$value}\"";
		}
		$atts = ' ' . implode( ' ', $atts );

		switch ( $context ) {
			case 'woo-checkout':
				return 'ãƒ­ã‚°ã‚¤ãƒ³';
				break;
			default:
				return sprintf(
					'<a href="%2$s" rel="nofollow" class="%4$s"%5$s>%3$s%1$s</a>',
					$text,
					$href,
					$icon,
					$class_attr,
					$atts
				);
				break;
		}
	}

	/**
	 * Show login button
	 *
	 * @param string $redirect
	 * @param bool   $register
	 * @param string $context
	 *
	 * @return string
	 */
	public function login_button( $redirect = '', $register = false, $context = '' ) {
		if ( ! $redirect ) {
			$redirect = admin_url( 'profile.php' );
			/**
			 * gianism_default_redirect_link
			 *
			 * @package Gianism
			 * @since 3.0.4
			 * @param string $redirect Redirect URL.
			 * @param string $service  Service name. e.g. twitter.
			 * @param bool   $register Detect if this is register context.
			 * @param string $context  Context of this button. Default empty string.
			 */
			$redirect = apply_filters( 'gianism_default_redirect_link', $redirect, $this->service_name, $register, $context );
		}
		$instance_info_list = $this->get_instance_info_list();
		foreach ( $instance_info_list as $instance_info ) {
			$url       = $this->get_redirect_endpoint(
				'login', $this->service_name . '_login', $this->array_filter_empty_value(
					array(
						'redirect_to'  => $redirect,
						'instance_url' => $instance_info['url'] ? $instance_info['url'] : '',
					)
				)
			);
			$text      = sprintf( $this->_( 'Log in with %s' ), $instance_info['name'] );
			$button    = $this->button(
				$text, $url, $instance_info['icon'], array( 'wpg-button', 'wpg-button-login' ), $this->array_filter_empty_value(
					array_merge(
						[
							'gianism-ga-category' => "gianism/{$this->service_name}",
							'gianism-ga-action'   => 'login',
							'gianism-ga-label'    => sprintf( $this->_( 'Login with %s' ), $instance_info['name'] ),
						], isset( $instance_info['attributes'] ) ? $instance_info['attributes'] : []
					)
				), $context
			);
			$buttons[] = $this->filter_link( $button, $url, $text, $register, $context, $instance_info );
		}
		return \implode( '', $buttons );
	}

	/**
	 * Get connect button
	 *
	 * @param string $redirect_to If not set, profile page's URL
	 *
	 * @return string
	 */
	public function connect_button( $redirect_to = '', $instance_info = [] ) {
		if ( empty( $redirect_to ) ) {
			$redirect_to = admin_url( 'profile.php' );
		}
		$url  = $this->get_redirect_endpoint(
			'connect', $this->service_name . '_connect', $this->array_filter_empty_value(
				array(
					'redirect_to'  => $redirect_to,
					'instance_url' => $instance_info['url'] ? $instance_info['url'] : '',
				)
			)
		);
		$args = array_merge(
			[
				'gianism-ga-category' => "gianism/{$this->service_name}",
				'gianism-ga-action'   => 'connect',
				'gianism-ga-label'    => sprintf( $this->_( 'Connect %s' ), $instance_info['name'] ),
			], isset( $instance_info['attributes'] ) ? $instance_info['attributes'] : []
		);

		return $this->button( sprintf( $this->_( 'Connect %s' ), $instance_info['name'] ), $url, 'link', array( 'wpg-button', 'connect' ), $args );
	}

	/**
	 * Get disconnect button
	 *
	 * @param string $redirect_to If not set, profile page's URL
	 *
	 * @return string
	 */
	public function disconnect_button( $redirect_to = '', $acct = '' ) {
		if ( empty( $redirect_to ) ) {
			$redirect_to = admin_url( 'profile.php' );
		}
		$url  = $this->get_redirect_endpoint(
			'disconnect', $this->service_name . '_disconnect', $this->array_filter_empty_value(
				array(
					'redirect_to' => $redirect_to,
					'acct'        => $acct,
				)
			)
		);
		$args = [
			'gianism-ga-category' => "gianism/{$this->service_name}",
			'gianism-ga-action'   => 'disconnect',
			'gianism-ga-label'    => sprintf( $this->_( 'Disconnect %s' ), $acct ),
			'gianism-confirm'     => sprintf( $this->_( 'You really disconnect from %s? If so, please be sure about your credential(email, passowrd), or else you might not be able to login again.' ), $this->verbose_service_name ),
		];

		return $this->button( sprintf( $this->_( 'Disconnect %s' ), $acct ), $url, 'logout', array( 'wpg-button', 'disconnect' ), $args );
	}

	protected function get_instance_info_list() {
		$instances = $this->instance_registry->get_all_client_info();
		foreach ( explode( "\n", $this->mastodon_login_button_list ) as $key ) {
			$key = trim( $key );
			if ( empty( $key ) ) {
				continue;
			} elseif ( '*' == $key ) {
				$instance_info_list[] = [
					'url'        => $key,
					'name'       => $this->verbose_service_name,
					'icon'       => '<i class="fab fa-mastodon wpg-mastodon-fa-color wpg-mastodon-fa-lsf"></i> ',
					'attributes' => [
						'onclick' => "javascript:alert('not implimentation');return false;",
					],
				];
			} else {
				$key = $this->instance_registry->get_valid_domain( $key );
				if ( false === $key ) {
					continue;
				} elseif ( false === \array_key_exists( $key, $instances ) ) {
					$provider    = $this->instance_registry->get_provider( $key, $this->mastodon_app_name, $this->get_redirect_endpoint(), site_url() );
					$client_info = $this->instance_registry->get_client_info( $key );
				} else {
					$client_info = $instances[ $key ];
				}
				$instance_info_list[] = [
					'url'  => $key,
					'name' => $client_info['title'],
					'icon' => '<i class="fab fa-mastodon wpg-mastodon-fa-color wpg-mastodon-fa-lsf"></i> ',
				];
			}
		}
		return $instance_info_list;
	}

	protected function array_filter_empty_value( array $array ) {
		return array_filter(
			$array, function( $val ) {
				return ! empty( $val );
			}
		);
	}

	protected function get_instance_info_list_() {
		return [
			[
				'url'  => '',
				'name' => $this->verbose_service_name,
				'icon' => $this->service_name,
			],
		];
	}

	/**
	 * Get user profile
	 *
	 * @param string $code
	 * @param string $state
	 * @param string $saved_state
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function get_user_profile( $code, $state, $saved_state ) {
		$token = $this->get_access_token( $code, $state, $saved_state );
		try {
			$user_info = $this->provider->getResourceOwner( $token );
		} catch ( \Exception $e ) {
			throw new \Exception( $this->mail_fail_string() );
		}

		return $user_info;
	}

	/**
	 * Get token
	 *
	 * @param string $code
	 * @param string $state
	 * @param string $saved_state
	 *
	 * @return bool
	 * @throws \Exception
	 */
	private function get_access_token( $code, $state, $saved_state ) {
		if ( ! $code || ! $state || ! $saved_state || $state != $saved_state ) {
			throw new \Exception( $this->api_error_string() );
		}
		$token = $this->provider->getAccessToken(
			'authorization_code', [
				'code' => $code,
			]
		);
		return $token;
	}

	/**
	 * Returns if given mail address is pseudo.
	 *
	 * @param string $mail
	 *
	 * @return boolean
	 */
	public function is_pseudo_mail( $mail ) {
		return \preg_match( '/\.invalid$/', $mail );
	}

	/**
	 * Make user login
	 *
	 * @param \WP_Query $wp_query
	 */
	public function handle_login( \WP_Query $wp_query ) {
		$this->session->write( 'instance_url', $this->input->get( 'instance_url' ) );
		parent::handle_login( $wp_query );
	}

	/**
	 * Return api URL to authenticate
	 *
	 * If you need additional information (ex. token),
	 * use $this->session->write inside.
	 *
	 * <code>
	 * $this->session->write('token', $token);
	 * return $url;
	 * </code>
	 *
	 * @param string $action 'connect', 'login'
	 *
	 * @return string|false URL to redirect
	 * @throws \Exception
	 */
	protected function get_api_url( $action ) {
		switch ( $action ) {
			case 'connect':
			case 'login':
				$url = $this->provider->getAuthorizationUrl();
				$this->session->write( 'state', $this->provider->getState() );
				return $url;
				break;
			default:
				return false;
				break;
		}
	}

	/**
	 * Get template screen
	 *
	 * @param string $template_dir
	 * @return string
	 */
	public function get_admin_template( $template_dir ) {
		switch ( $template_dir ) {
			case 'setting':
			case 'setup':
				return dirname( __DIR__ ) . '/templates/' . $template_dir . '.php';
				break;
			default:
				return parent::get_admin_template( $template_dir );
				break;
		}
	}

	/**
	 * Getter
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'instance_registry':
				return InstanceRegistry::get_instance();
				break;
			case 'provider':
				if ( ! isset( $this->cached_provider ) ) {
					$this->session->write(
						'instance_url', $instance_url =
						$this->input->get( 'instance_url' ) ? $this->input->get( 'instance_url' ) : $this->session->get( 'instance_url' )
					);
					$this->cached_provider            = $this->instance_registry->get_provider(
						$instance_url,
						$this->mastodon_app_name,
						$this->get_redirect_endpoint(),
						site_url()
					);
				}
				return $this->cached_provider;
				break;
			case 'enabled':
				return $this->option->is_enabled( $this->service_name );
				break;
			default:
				return parent::__get( $name );
				break;
		}
	}
}
