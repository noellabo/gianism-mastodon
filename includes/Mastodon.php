<?php

namespace GianismMastodon;

use Gianism\Service\AbstractService;
use Gianism\Service\NoMailService;
use GianismMastodon\InstanceRegistry;

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
	 * mastodon app name
	 *
	 * @var string
	 */
	public $mastodon_app_name = '';

	/**
	 * mastodon's login button list
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
	 * acct consent explanation
	 *
	 * @var string
	 */
	public $acct_consent_explanation = '';

	/**
	 * post link enabled
	 *
	 * @var bool
	 */
	public $post_link_enabled = false;

	/**
	 * post link auto or manual
	 *
	 * @var bool
	 */
	public $post_link_manual = true;

	/**
	 * post link visibility
	 *
	 * @var string
	 */
	public $post_link_visibility = 'public';

	/**
	 * post link sensitive
	 *
	 * @var bool
	 */
	public $post_link_sensitive = true;

	/**
	 * post link title template
	 *
	 * @var string
	 */
	public $post_link_title_template = '';

	/**
	 * post link content template
	 *
	 * @var string
	 */
	public $post_link_content_template = '';

	/**
	 * comment link enabled
	 *
	 * @var bool
	 */
	public $comment_link_enabled = false;

	/**
	 * comment link visibility
	 *
	 * @var string
	 */
	public $comment_link_visibility = 'public';

	/**
	 * comment link sensitive
	 *
	 * @var bool
	 */
	public $comment_link_sensitive = true;

	/**
	 * comment link title template
	 *
	 * @var string
	 */
	public $comment_link_title_template = '';

	/**
	 * comment link content template
	 *
	 * @var string
	 */
	public $comment_link_content_template = '';

	/**
	 * Maximum content length per comment link post
	 *
	 * @var integer
	 */
	public $mastodon_content_length_max = 500;

	/**
	 * User meta key for mastodon accounts array
	 *
	 * @var string
	 */
	public $umeta_accounts = '_wpg_mastodon_accounts';

	/**
	 * User meta key for comment link account
	 *
	 * @var string
	 */
	public $umeta_comment_link_acct = '_wpg_comment_link_acct';

	/**
	 * User meta key for comment link account access token
	 *
	 * @var string
	 */
	public $umeta_comment_link_acct_access_token = '_wpg_comment_link_acct_access_token';

	/**
	 * Option key to copy
	 *
	 * @var array
	 */
	protected $option_keys = [
		'mastodon_enabled'              => false,
		'mastodon_app_name'             => '',
		'mastodon_login_button_list'    => '*',
		'mastodon_deny_instance_list'   => '',
		'acct_consent_explanation'      => '',
		'post_link_enabled'             => false,
		'post_link_manual'              => true,
		'post_link_visibility'          => 'public',
		'post_link_sensitive'           => false,
		'post_link_title_template'      => '『%title%』を公開しました。',
		'post_link_content_template'    => "%post_excerpt%\n%post_url%",
		'comment_link_enabled'          => false,
		'comment_link_visibility'       => 'public',
		'comment_link_sensitive'        => true,
		'comment_link_title_template'   => '『%title%』へのコメント',
		'comment_link_content_template' => "%content%\n\n%post_url%",
		'mastodon_content_length_max'   => 500,
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
		$this->option_keys['mastodon_app_name']        = get_bloginfo( 'name' );
		$this->option_keys['acct_consent_explanation'] =
			sprintf( $this->_( 'If you allow this setting, when you post, %s will post on your Mastodon account.' ), get_bloginfo( 'name' ) );
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
	}

	protected function init_action() {
		if ( method_exists( $this, 'print_style' ) ) {
			//Add Hook On footer
			add_action( 'admin_print_styles', [ $this, 'print_style' ] );
			add_action( 'wp_head', [ $this, 'print_style' ] );
			add_action( 'login_enqueue_scripts', [ $this, 'print_style' ] );
		}
		if ( method_exists( $this, 'print_script' ) ) {
			//Add Hook On footer
			add_action( 'admin_enqueue_scripts', [ $this, 'print_script' ] );
			add_action( 'wp_footer', [ $this, 'print_script' ] );
			add_action( 'login_footer', [ $this, 'print_script' ] );
		}
		if ( $this->post_link_enabled ) {
			add_action( 'show_password_fields', [ $this, 'set_user_profile' ] );
		}
		if ( $this->post_link_enabled && ! $this->post_link_manual ) {
			add_action( 'transition_post_status', [ $this, 'post_toot' ], 10, 3 );
		}
		if ( $this->comment_link_enabled ) {
			add_action( 'show_password_fields', [ $this, 'set_user_profile' ] );
			add_action( 'comment_post', [ $this, 'comment_toot' ], 10, 3 );
		}
		$this->rest_api_init();
	}

	public function rest_api_init() {
		add_action(
			'rest_api_init', function () {
				register_rest_route(
					'gianism-mastodon/v1', '/instance', array(
						'methods'  => 'GET',
						'callback' => function( $data ) {
							try {
								$mastodon = Mastodon::get_instance();
								$instance_registry = InstanceRegistry::get_instance();
								$instance_url = $instance_registry->get_valid_domain( $data['url'] );
								if ( false === $instance_url ) {
									throw new \Exception( sprintf( 'Invalid instance url: %s', $data['url'] ) );
								}
								if ( $mastodon->is_deny_instance( $instance_url ) ) {
									throw new \Exception( sprintf( 'Deny instance: %s', $data['url'] ) );
								}
								$response = new \WP_REST_Response( $instance_registry->get_instance_info( $instance_url ) );
								$response->set_status( 200 );
								return $response;
							} catch ( \Exception $e ) {
								return new \WP_Error( 'no_instance', $e->getMessage(), array( 'status' => 404 ) );
							}
						},
					)
				);
				register_rest_route(
					'gianism-mastodon/v1', '/extract_domain', array(
						'methods'  => 'POST',
						'callback' => function() {
							try {
								$data = $_POST;
								$instance_registry = InstanceRegistry::get_instance();
								$instance_url = $instance_registry->get_valid_domain( $data['url'] );
								if ( false === $instance_url ) {
									throw new \Exception( sprintf( 'Invalid instance url: %s', $data['url'] ) );
								}
								$response = new \WP_REST_Response( [ 'instance_url' => $instance_url ] );
								$response->set_status( 200 );
								return $response;
							} catch ( \Exception $e ) {
								return new \WP_Error( 'no_domain', $e->getMessage(), array( 'status' => 400 ) );
							}
						},
					)
				);
				register_rest_route(
					'gianism-mastodon/v1', '/comment_link', array(
						'methods'  => 'DELETE',
						'callback' => function( $data ) {
							try {
								if ( ! is_user_logged_in() ) {
									throw new \Exception( $this->_( 'You must be logged in' ) );
								}
								$acct = get_user_meta( get_current_user_id(), $this->umeta_comment_link_acct, true );
								delete_user_meta( get_current_user_id(), $this->umeta_comment_link_acct );
								delete_user_meta( get_current_user_id(), $this->umeta_comment_link_acct_access_token );
								$response = new \WP_REST_Response( [ 'acct' => $acct ] );
								$response->set_status( 200 );
								return $response;
							} catch ( \Exception $e ) {
								return new \WP_Error( 'not_login', $e->getMessage(), array( 'status' => 400 ) );
							}
						},
					)
				);
			}
		);
	}

	public function print_style() {
		wp_enqueue_style( 'jquery-ui-dialog-min-css', includes_url() . 'css/jquery-ui-dialog.min.css' );
		wp_enqueue_style( 'gianism-mastodon-style', plugins_url( 'assets/css/gianism-mastodon-style.css', dirname( __FILE__ ) ) );
		wp_enqueue_style( 'font-awesome', 'https://use.fontawesome.com/releases/v5.2.0/css/all.css"' );
	}

	public function print_script() {
		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_script( 'gianism-mastodon-script', plugins_url( 'assets/js/gianism-mastodon-script.js', dirname( __FILE__ ) ) );
		wp_enqueue_script( 'wp-api', home_url( '/wp-content/plugins/rest-api/wp-api.js' ) );
		wp_localize_script(
			'wp-api', 'WP_API_Settings', [
				'root'  => esc_url_raw( rest_url() ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			]
		);
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
			case 'expand':
				try {
					// Is user logged in?
					if ( ! is_user_logged_in() ) {
						throw new \Exception( $this->_( 'You must be logged in' ) );
					}
					// Get user info
					list($user_info, $token) = $this->get_user_profile_expand( $code, $state, $saved_state );
					$owner                   = $this->get_meta_owner( $this->umeta_accounts, $user_info->getAcct() );
					if ( ! $owner ) {
						throw new \Exception( $this->_( 'You must be connected account' ) );
					}
					// O.k.
					update_user_meta( get_current_user_id(), $this->umeta_comment_link_acct, $user_info->getAcct() );
					update_user_meta( get_current_user_id(), $this->umeta_comment_link_acct_access_token, $token );
					$this->hook_connect( get_current_user_id(), $user_info->toArray(), false );
					$this->add_message( $this->_( 'Write permission recorded!' ) );
					$redirect_url = $this->filter_redirect( $redirect_url, 'expand' );
				} catch ( \Exception $e ) {
					$this->auth_fail( $e->getMessage() );
					$redirect_url = $this->filter_redirect( $redirect_url, 'expand-failure' );
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
		add_action( 'admin_footer-profile.php', [ $this, 'instance_entry_dialog' ] );
		add_action( 'admin_footer-profile.php', [ $this, 'comment_link_acct_dialog' ] );
		add_action( 'admin_footer-profile.php', [ $this, 'comment_link_acct_delete_dialog' ] );
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

	public function login_form( $is_register = false, $redirect_to = '', $context = '' ) {
		add_action( 'wp_footer', [ $this, 'instance_entry_dialog' ] );
		add_action( 'login_footer', [ $this, 'instance_entry_dialog' ] );
		parent::login_form( $is_register, $redirect_to, $context );
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
				$text,
				$url,
				$instance_info['icon'],
				$this->array_filter_empty_value(
					array_merge(
						[
							'wpg-button',
							'wpg-button-login',
						], isset( $instance_info['classes'] ) ? $instance_info['classes'] : []
					)
				),
				$this->array_filter_empty_value(
					array_merge(
						[
							'gianism-ga-category' => "gianism/{$this->service_name}",
							'gianism-ga-action'   => 'login',
							'gianism-ga-label'    => sprintf( $this->_( 'Login with %s' ), $instance_info['name'] ),
						], isset( $instance_info['attributes'] ) ? $instance_info['attributes'] : []
					)
				),
				$context
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
		$url     = $this->get_redirect_endpoint(
			'connect', $this->service_name . '_connect', $this->array_filter_empty_value(
				array(
					'redirect_to'  => $redirect_to,
					'instance_url' => $instance_info['url'] ? $instance_info['url'] : '',
				)
			)
		);
		$classes = array_merge(
			[
				'wpg-button',
				'connect',
			], isset( $instance_info['classes'] ) ? $instance_info['classes'] : []
		);
		$args    = array_merge(
			[
				'gianism-ga-category' => "gianism/{$this->service_name}",
				'gianism-ga-action'   => 'connect',
				'gianism-ga-label'    => sprintf( $this->_( 'Connect %s' ), $instance_info['name'] ),
			], isset( $instance_info['attributes'] ) ? $instance_info['attributes'] : []
		);

		return $this->button( sprintf( $this->_( 'Connect %s' ), $instance_info['name'] ), $url, 'link', $classes, $args );
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
		$url     = $this->get_redirect_endpoint(
			'disconnect', $this->service_name . '_disconnect', $this->array_filter_empty_value(
				array(
					'redirect_to' => $redirect_to,
					'acct'        => $acct,
				)
			)
		);
		$classes = array_merge(
			[
				'wpg-button',
				'disconnect',
			], isset( $instance_info['classes'] ) ? $instance_info['classes'] : []
		);
		$args    = [
			'gianism-ga-category' => "gianism/{$this->service_name}",
			'gianism-ga-action'   => 'disconnect',
			'gianism-ga-label'    => sprintf( $this->_( 'Disconnect %s' ), $acct ),
			'gianism-confirm'     => sprintf( $this->_( 'You really disconnect from %s? If so, please be sure about your credential(email, passowrd), or else you might not be able to login again.' ), $this->verbose_service_name ),
		];

		return $this->button( sprintf( $this->_( 'Disconnect %s' ), $acct ), $url, 'logout', $classes, $args );
	}

	public function instance_entry_dialog() {
		$html = <<<EOS
		<div class="gimastodon ui-widget" id="connect_to_instance" title="%s">
			<label for="instance_url">%s</label><br>
			<input class="ui-widget-content ui-corner-all" type="text" name="instance_url" id="instance_url" value="">
			<p class="ui-state-error-text ui-helper-hidden" id="connect_to_instance_error">%s</p>

			<fieldset>
				<legend>%s</legend>
				<ul id="recentry-instances">
				</ul>
			</fieldset>
		</div>
EOS;
		echo sprintf(
			$html,
			$this->_( 'Connect to instance' ),
			$this->_( 'Please enter the URL of the instance' ),
			$this->_( 'The format is incorrect, or a URL that is not Mastodon is specified.' ),
			$this->_( 'Select from recent instance' )
		);
	}

	public function comment_link_acct_dialog() {
		$html = <<<EOS
		<div class="ui-widget" id="comment_link_acct_dialog" title="%s">
			<p>%s</p>
		</div>
EOS;
		echo sprintf(
			$html,
			$this->_( 'Comment link acct consent explanation' ),
			$this->comment_link_acct_consent_explanation
		);
	}

	public function comment_link_acct_delete_dialog() {
		$html = <<<EOS
		<div class="ui-widget" id="comment_link_acct_delete_dialog" title="%s">
			<p>%s</p>
		</div>
EOS;
		echo sprintf(
			$html,
			$this->_( 'Confirm delete comment link acct' ),
			$this->_( 'Would you like to release the comment linkage?' )
		);
	}

	protected function get_instance_info_list() {
		$instances = $this->instance_registry->get_all_client_info();
		foreach ( explode( "\n", $this->mastodon_login_button_list ) as $key ) {
			$key = trim( $key );
			if ( empty( $key ) ) {
				continue;
			}
			list($key, $name) = explode( ',', $key ) + [ null, null ];
			if ( '*' == $key ) {
				$instance_info_list[] = [
					'url'     => $key,
					'name'    => empty( $name ) ? $this->verbose_service_name : $name,
					'icon'    => '<i class="fab fa-mastodon wpg-mastodon-fa-color wpg-mastodon-fa-lsf"></i> ',
					'classes' => [ 'with-instance-dialog' ],
				];
			} else {
				$key = $this->instance_registry->get_valid_domain( $key );
				if ( false === $key || $this->is_deny_instance( $key ) ) {
					continue;
				} elseif ( false === \array_key_exists( $key, $instances ) ) {
					$provider    = $this->instance_registry->get_provider( $key, $this->mastodon_app_name, $this->get_redirect_endpoint(), site_url() );
					$client_info = $this->instance_registry->get_client_info( $key );
				} else {
					$client_info = $instances[ $key ];
				}
				$instance_info_list[] = [
					'url'  => $key,
					'name' => '<i>' . ( empty( $name ) ? $client_info['title'] : $name ) . '</i>',
					'icon' => '<i class="fab fa-mastodon wpg-mastodon-fa-color wpg-mastodon-fa-lsf"></i> ',
				];
			}
		}
		return $instance_info_list;
	}

	public function is_deny_instance( $instance_url ) {
		foreach ( explode( "\n", $this->mastodon_deny_instance_list ) as $deny_regex ) {
			if ( ! empty( $deny_regex ) && preg_match( trim( $deny_regex ), $instance_url ) ) {
				return true;
			}
		}
		return false;
	}

	protected function array_filter_empty_value( array $array ) {
		return array_filter(
			$array, function( $val ) {
				return ! empty( $val );
			}
		);
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
	 * Get user profile expand
	 *
	 * @param string $code
	 * @param string $state
	 * @param string $saved_state
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function get_user_profile_expand( $code, $state, $saved_state ) {
		$token = $this->get_access_token_expand( $code, $state, $saved_state );
		try {
			$user_info = $this->provider->getResourceOwner( $token );
		} catch ( \Exception $e ) {
			throw new \Exception( $this->mail_fail_string() );
		}

		return [ $user_info, $token->getToken() ];
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
				'code'  => $code,
				'scope' => 'read:accounts',
			]
		);
		return $token;
	}

	/**
	 * Get token expand
	 *
	 * @param string $code
	 * @param string $state
	 * @param string $saved_state
	 *
	 * @return bool
	 * @throws \Exception
	 */
	private function get_access_token_expand( $code, $state, $saved_state ) {
		if ( ! $code || ! $state || ! $saved_state || $state != $saved_state ) {
			throw new \Exception( $this->api_error_string() );
		}
		$token = $this->provider->getAccessToken(
			'authorization_code', [
				'code'  => $code,
				'scope' => 'read write',
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
			case 'expand':
				$url = $this->provider->getAuthorizationUrl(
					[
						'scope' => 'read write',
					]
				);
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
	 * Create valid username from email address
	 *
	 * @param string $email
	 *
	 * @return string
	 * @throws \Exception
	 */
	protected function valid_username_from_mail( $email ) {
		$email_elements = explode( '@', $email );
		$suffix         = array_shift( $email_elements );
		if ( ! username_exists( $suffix ) ) {
			return $suffix;
		}
		$service_domain = $suffix . '@' . $this->service_name;
		if ( ! username_exists( $service_domain ) ) {
			return $service_domain;
		}
		$original_domain = $suffix . '@' . $_SERVER['SERVER_NAME'];
		if ( ! username_exists( $original_domain ) ) {
			return $original_domain;
		}
		throw new \Exception( $this->_( 'Sorry, but cannot create valid user name.' ) );
	}

	/**
	 * Handle expand
	 *
	 * @param \WP_Query $wp_query
	 */
	protected function handle_expand( \WP_Query $wp_query ) {
		try {
			// Is user logged in?
			if ( ! is_user_logged_in() ) {
				throw new \Exception( $this->_( 'You must be logged in.' ) );
			}
			// Set redirect URL
			$url = $this->get_api_url( 'expand' );
			if ( ! $url ) {
				throw new \Exception( $this->_( 'Sorry, but failed to expand with API.' ) );
			}
			// Write session
			$this->session->write( 'redirect_to', $this->input->get( 'redirect_to' ) );
			$this->session->write( 'action', 'expand' );
			// OK, let's redirect.
			wp_redirect( $url );
			exit;
		} catch ( \Exception $e ) {
			$this->input->wp_die( $e->getMessage() );
		}
	}

	public function set_user_profile( $bool ) {
		global $profileuser;
		if ( get_current_user_id() != $profileuser->ID ) {
			return $bool;
		}

		$html                 = <<<EOM
		<tr><th><label for="comment_link_acct">%s</label></th><td>
		<select name="comment_link_acct" id="comment_link_acct" data-href="%s">
		%s
		</select>
		<p class="description">%s<p>
		</td></tr>
EOM;
		$comment_link_account = get_user_meta( get_current_user_id(), $this->umeta_comment_link_acct, true );
		$accounts             = get_user_meta( get_current_user_id(), $this->umeta_accounts, false );
		array_unshift( $accounts, '' );
		$option_tags = '';
		foreach ( $accounts as $account ) {
			$option_tags .= '<option value="' . $account . '"'
				. ( ( $comment_link_account == $account ) ? ' selected="selected"' : '' ) . '>'
				. ( ( '' == $account ) ? $this->_( '(No selection)' ) : $account ) . '</option>' . "\n";
		}
		echo sprintf(
			$html,
			$this->_( 'Comment Link Acct' ),
			$this->get_redirect_endpoint(
				'expand', $this->service_name . '_expand',
				[
					'redirect_to'  => admin_url( 'profile.php' ),
					'instance_url' => '',
				]
			),
			$option_tags,
			$this->_( 'Specify the mastodon instance to be linked posting at the time of comment.' )
		);

		return $bool;
	}

	public function post_toot( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || ! empty( get_post_meta( $post->ID, 'status_uri', true ) ) ) {
			return;
		}
		if ( 'post' !== $post->post_type && 'page' !== $post->post_type ) {
			return;
		}
		if ( ! $this->acct ) {
			return;
		}

		mb_internal_encoding( 'UTF-8' );
		mb_regex_encoding( 'UTF-8' );

		$post_content = $post->post_content;
		try {
			$status_uri = get_post_meta( $post->post_parent, 'status_uri', true );
			if ( empty( $status_uri ) ) {
				throw new \Exception( 'status_uri not found.' );
			}
			if ( 0 === strncmp( $this->instance_url, $status_domain, strlen( $this->instance_url ) ) ) {
				$in_reply_to_id = substr( $status_uri, strrpos( $status_uri, '/' ) + 1 );
				$parent_status  = $this->mastodon_get_status( $in_reply_to_id );
				$parent_acct    = '@' . $parent_status['account']['acct'];
			} else {
				$searched       = $this->mastodon_get_search( $status_uri );
				$in_reply_to_id = $searched['statuses'][0]['id'];
				$parent_acct    = '@' . $searched['statuses'][0]['account']['acct'];
			}
			$post_content = $parent_acct . ' ' . $post_content;
		} catch ( \Exception $e ) {
			$in_reply_to_id = '';
		}
		$content_length   = mb_strlen( $post_content );
		$title_template   = $this->post_link_title_template;
		$content_template = $this->post_link_content_template;

		$category_names = [];
		$category_slugs = [];
		if ( $categories = get_the_category( $post->ID ) ) {
			foreach ( $categories as $category ) {
				$category_names[] = '#' . $category->name;
				$category_slugs[] = '#' . $category->slug;
			}
		}
		$tag_names = [];
		$tag_slugs = [];
		if ( $tags = get_the_tags( $post->ID ) ) {
			foreach ( $tags as $tag ) {
				$tag_names[] = '#' . $tag->name;
				$tag_slugs[] = '#' . $tag->slug;
			}
		}

		$replace_tags = [
			'/%title%/'          => $post->post_title,
			'/%post_excerpt%/'   => $post->post_excerpt,
			'/%slug%/'           => $post->post_name,
			'/%category_name%/'  => isset( $category_names[0] ) ? '' : $category_names[0],
			'/%category_slug%/'  => isset( $category_slugs[0] ) ? '' : $category_slugs[0],
			'/%category_names%/' => implode( ' ', $category_names ),
			'/%category_slugs%/' => implode( ' ', $category_slugs ),
			'/%tag_names%/'      => implode( ' ', $tag_names ),
			'/%tag_slugs%/'      => implode( ' ', $tag_slugs ),
			'/%slug%/'           => $post->post_name,
			'/%post_url%/'       => get_permalink( $post->ID ),
			'/%home_url%/'       => home_url(),
			'/%site_name%/'      => get_bloginfo( 'name' ),
		];

		$title              = $this->tag_replace( $title_template, $replace_tags );
		$content_length_max = $this->mastodon_content_length_max - mb_strlen( $title ) -
			mb_strlen( $this->tag_replace( $content_template, $replace_tags ) );

		$status = [
			'visibility'   => $this->post_link_visibility,
			'sensitive'    => $this->post_link_sensitive,
			'spoiler_text' => $this->post_link_sensitive ? $title : '',
		] + ( empty( $in_reply_to_id ) ? [] : [
			'in_reply_to_id' => $in_reply_to_id,
		] );

		try {
			if ( $content_length > $content_length_max ) {
				$content_length_max -= 9; // '… (10/10)'
				$split_num           = ceil( $content_length / $content_length_max );
				if ( $split_num > 20 ) {
					throw new \Exception( 'Too many divisions of content.' );
				} elseif ( $split_num >= 10 ) {
					$content_length_max -= 2;
					$split_num           = ceil( $content_length / $content_length_max );
				}
				$split_contents = $this->split_content( $post_content, $content_length_max );
				$split_num      = count( $split_contents );
				$counter        = 1;
				foreach ( $split_contents as $split_content ) {
					if ( $this->post_link_sensitive ) {
						$status['spoiler_text'] = sprintf( '%s (%d/%d)', $title, $counter, $split_num );
						$status['status']       = '';
					} else {
						$status['spoiler_text'] = '';
						$status['status']       = sprintf( '%s (%d/%d)', $title, $counter, $split_num ) . "\n\n";
					}
					$status['status']        .= $this->tag_replace( $content_template, $replace_tags, $split_content );
					$posted_status            = $this->mastodon_post_status( $status );
					$status['in_reply_to_id'] = $posted_status['id'];
					$posted_statuses[]        = $posted_status;
					$counter++;
				}
			} else {
				$status['status']  = $this->tag_replace( $content_template, $replace_tags, $post_content );
				$posted_statuses[] = $this->mastodon_post_status( $status );
			}

			add_post_meta( $post->ID, 'status_uri', $posted_statuses[0]['uri'] );
		} catch ( \Exception $e ) {
			return;
		}

		return $posted_statuses;
	}

	public function comment_toot( $comment_id, $comment_approved, $commentdata ) {
		if ( get_current_user_id() != $commentdata['user_ID'] ) {
			return;
		}
		if ( ! $this->acct ) {
			return;
		}

		mb_internal_encoding( 'UTF-8' );
		mb_regex_encoding( 'UTF-8' );

		$post            = get_post( $commentdata['comment_post_ID'], 'ARRAY_A' );
		$comment_content = $commentdata['comment_content'];
		$comment_parent  = $commentdata['comment_parent'];
		try {
			if ( '' != $comment_parent ) {
				$status_uri = get_comment_meta( $comment_parent, 'status_uri', true );
			} else {
				$status_uri = get_post_meta( $post['ID'], 'status_uri', true );
			}
			if ( empty( $status_uri ) ) {
				throw new \Exception( 'status_uri not found.' );
			}
			$status_domain = substr( $status_uri, 0, strpos( $status_uri, '/', strlen( 'https://' ) ) );
			if ( $this->instance_url === $status_domain ) {
				$in_reply_to_id  = substr( $status_uri, strrpos( $status_uri, '/' ) + 1 );
				$parent_status   = $this->mastodon_get_status( $in_reply_to_id );
				$comment_content = '@' . $parent_status['account']['acct'] . ' ' . $comment_content;
			} else {
				$searched        = $this->mastodon_get_search( $status_uri );
				$in_reply_to_id  = $searched['statuses'][0]['id'];
				$comment_content = '@' . $searched['statuses'][0]['account']['acct'] . ' ' . $comment_content;
			}
		} catch ( \Exception $e ) {
			$in_reply_to_id = '';
		}
		$content_length   = mb_strlen( $comment_content );
		$title_template   = $this->comment_link_title_template;
		$content_template = $this->comment_link_content_template;

		$replace_tags = [
			'/%title%/'        => $post['post_title'],
			'/%post_excerpt%/' => $post['post_excerpt'],
			'/%slug%/'         => $post['post_name'],
			'/%post_url%/'     => get_permalink( $post['ID'] ),
			'/%home_url%/'     => home_url(),
			'/%site_name%/'    => get_bloginfo( 'name' ),
		];

		$title              = $this->tag_replace( $title_template, $replace_tags );
		$content_length_max = $this->mastodon_content_length_max - mb_strlen( $title ) -
			mb_strlen( $this->tag_replace( $content_template, $replace_tags ) );

		$status = [
			'visibility'   => $this->comment_link_visibility,
			'sensitive'    => $this->comment_link_sensitive,
			'spoiler_text' => $this->comment_link_sensitive ? $title : '',
		] + ( empty( $in_reply_to_id ) ? [] : [
			'in_reply_to_id' => $in_reply_to_id,
		] );

		try {
			if ( $content_length > $content_length_max ) {
				$content_length_max -= 9; // '… (10/10)'
				$split_num           = ceil( $content_length / $content_length_max );
				if ( $split_num > 20 ) {
					throw new \Exception( 'Too many divisions of content.' );
				} elseif ( $split_num >= 10 ) {
					$content_length_max -= 2;
					$split_num           = ceil( $content_length / $content_length_max );
				}
				$split_contents = $this->split_content( $comment_content, $content_length_max );
				$split_num      = count( $split_contents );
				$counter        = 1;
				foreach ( $split_contents as $split_content ) {
					if ( $this->comment_link_sensitive ) {
						$status['spoiler_text'] = sprintf( '%s (%d/%d)', $title, $counter, $split_num );
						$status['status']       = '';
					} else {
						$status['spoiler_text'] = '';
						$status['status']       = sprintf( '%s (%d/%d)', $title, $counter, $split_num ) . "\n\n";
					}
					$status['status']        .= $this->tag_replace( $content_template, $replace_tags, $split_content );
					$posted_status            = $this->mastodon_post_status( $status );
					$status['in_reply_to_id'] = $posted_status['id'];
					$posted_statuses[]        = $posted_status;
					$counter++;
				}
			} else {
				$status['status']  = $this->tag_replace( $content_template, $replace_tags, $comment_content );
				$posted_statuses[] = $this->mastodon_post_status( $status );
			}

			add_comment_meta( $comment_id, 'status_uri', $posted_statuses[0]['uri'] );
		} catch ( \Exception $e ) {
			return;
		}

		return $posted_statuses;
	}

	private function split_content( $text, $width ) {
		return preg_split( '/(.{1,' . $width . '})(?:\s|$)|(.{' . $width . '})/suS', $text, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );
	}

	private function tag_replace( $template, $replace_tags, $content = '' ) {
		$replace_tags['/%content%/'] = $content;
		return preg_replace(
			array_keys( $replace_tags ),
			array_values( $replace_tags ),
			$template
		);
	}

	private function mastodon_post_status( $status ) {
		return $this->mastodon_api( 'POST', '/api/v1/statuses', $status );
	}

	private function mastodon_get_status( $id ) {
		return $this->mastodon_api( 'GET', '/api/v1/statuses/' . esc_url( $id ) );
	}

	private function mastodon_get_search( $url ) {
		return $this->mastodon_api( 'GET', '/api/v1/search', [ 'q' => esc_url( $url ) ] );
	}

	private function mastodon_api( $method, $endpoint, $data = [] ) {
		$param = [
			'method'      => $method,
			'timeout'     => '5',
			'redirection' => '5',
			'httpversion' => '1.1',
			'blocking'    => true,
			'cookies'     => [],
			'user-agent'  => isset( $_SERVER['HTTP_USER_AGENT'] ) ?
				$_SERVER['HTTP_USER_AGENT'] :
				'Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; rv:11.0) like Gecko',
		];

		switch ( $method ) {
			case 'POST':
			case 'PUT':
			case 'PATCH':
				$data['access_token'] = $this->token;
				$param['body']        = $data;
				break;
			case 'GET':
			case 'DELETE':
			default:
				$param['head'] = [
					'Content-Type: application/json',
					'Authorization: Bearer ' . $this->token,
				];
				$endpoint     .= '?' . build_query( $data );
				break;
		}

		$response = wp_remote_request( esc_url_raw( $this->instance_url ) . $endpoint, $param );
		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'WP_Error: ' . $response->get_error_message() );
		} elseif ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			throw new \Exception( 'Invalid API response:' . wp_remote_retrieve_response_message( $response ) );
		}
		return json_decode( wp_remote_retrieve_body( $response ), true );
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
			case 'acct':
				return get_user_meta( get_current_user_id(), $this->umeta_comment_link_acct, true );
				break;
			case 'access_token':
				return get_user_meta( get_current_user_id(), $this->umeta_comment_link_acct_access_token, true );
				break;
			case 'instance_url':
				return $this->instance_registry->get_valid_domain( $this->acct );
				break;
			default:
				return parent::__get( $name );
				break;
		}
	}
}
