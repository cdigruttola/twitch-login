<?php

/**
 * Plugin Name: Twitch Login by cdigruttola
 * Plugin URI: https://www.cdigruttola.it
 * Description: Allow login with Twitch in specific page
 * Version: 1.0.0
 * Author: <a href="https://www.cdigruttola.it/">cdigruttola</a>
 * License: GPL3
 */

if ( ! function_exists( 'write_log' ) ) {
	function write_log( $log ) {
		if ( true === WP_DEBUG ) {
			if ( is_array( $log ) || is_object( $log ) ) {
				error_log( print_r( $log, true ) );
			} else {
				error_log( $log );
			}
		}
	}
}

class twitch_login {

	private $plugin_name = 'twitch-login-plugin';
	private $page_id;
	private $twitch_client_id;
	private $twitch_client_secret;
	private $twitch_channel_name;
	private $html;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		add_action( 'the_content', array( $this, 'add_twitch_login_button' ), 500 );
		add_action( 'template_redirect', array( $this, 'twitch_login_page_redirect' ), 999 );

		$this->page_id              = get_option( 'twitch_login_page_id' );
		$this->twitch_client_id     = get_option( 'twitch_client_id' );
		$this->twitch_client_secret = get_option( 'twitch_client_secret' );
		$this->twitch_channel_name  = get_option( 'twitch_channel_name' );
		$this->html                 = get_option( 'twitch_html' );
	}

	public function add_plugin_page() {
		add_menu_page(
			'Twitch Login Page',
			'Twitch Login Page',
			'manage_options',
			'twitch-login-plugin',
			array( $this, 'create_admin_page' ),
			'dashicons-groups',
			100
		);
	}

	public function create_admin_page() {
		$this->page_id              = $this->page_id ?? get_option( 'twitch_login_page_id' );
		$this->twitch_client_id     = $this->twitch_client_id ?? get_option( 'twitch_client_id' );
		$this->twitch_client_secret = $this->twitch_client_secret ?? get_option( 'twitch_client_secret' );
		$this->twitch_channel_name  = $this->twitch_channel_name ?? get_option( 'twitch_channel_name' );
		$this->html                 = $this->html ?? get_option( 'twitch_html' );
		?>
        <div class="wrap">
            <h2>Twitch Login Page Settings</h2>
            <form method="post" action="options.php">
				<?php
				settings_fields( 'twitch_login_page_group' );
				do_settings_sections( $this->plugin_name );
				submit_button();
				?>
            </form>
        </div>
		<?php
	}

	public function page_init() {
		register_setting(
			'twitch_login_page_group',
			'twitch_login_page_id'
		);
		register_setting(
			'twitch_login_page_group',
			'twitch_client_id'
		);
		register_setting(
			'twitch_login_page_group',
			'twitch_client_secret'
		);
		register_setting(
			'twitch_login_page_group',
			'twitch_channel_name'
		);
		register_setting(
			'twitch_login_page_group',
			'twitch_html'
		);
		add_settings_section(
			'twitch_login_page_section',
			'Page Settings',
			array( $this, 'print_section_info' ),
			$this->plugin_name
		);
		add_settings_field(
			'twitch_login_page_id',
			'Page ID',
			array( $this, 'twitch_login_page_id_callback' ),
			$this->plugin_name,
			'twitch_login_page_section'
		);
		add_settings_field(
			'twitch_client_id',
			'Twitch Client ID',
			array( $this, 'client_id_callback' ),
			$this->plugin_name,
			'twitch_login_page_section'
		);

		add_settings_field(
			'twitch_client_secret',
			'Twitch Client Secret',
			array( $this, 'client_secret_callback' ),
			$this->plugin_name,
			'twitch_login_page_section'
		);

		add_settings_field(
			'twitch_channel_name',
			'Twitch Channel Name',
			array( $this, 'channel_name_callback' ),
			$this->plugin_name,
			'twitch_login_page_section'
		);

		add_settings_field(
			'twitch_html',
			'HTML for non subscribers',
			array( $this, 'plugin_html_field_callback' ),
			$this->plugin_name,
			'twitch_login_page_section'
		);

	}

	public function print_section_info() {
		echo '<p>Choose the page that you want to require Twitch login for.</p>';
	}

	public function twitch_login_page_id_callback() {
		$pages           = get_pages();
		$current_page_id = $this->page_id ? $this->page_id : '';
		echo '<select id="twitch_login_page_id" name="twitch_login_page_id" required>';
		echo '<option value="">-- Select a page --</option>';
		foreach ( $pages as $page ) {
			$selected = ( $current_page_id == $page->ID ) ? 'selected' : '';
			echo '<option value="' . $page->ID . '" ' . $selected . '>' . $page->post_title . '</option>';
		}
		echo '</select>';
	}

	public function client_id_callback() {
		printf(
			'<input type="text" id="twitch_client_id" name="twitch_client_id" value="%s" required/>',
			isset( $this->twitch_client_id ) ? esc_attr( $this->twitch_client_id ) : ''
		);
	}

	public function client_secret_callback() {
		printf(
			'<input type="password" id="twitch_client_secret" name="twitch_client_secret" value="%s" required/>',
			isset( $this->twitch_client_secret ) ? esc_attr( $this->twitch_client_secret ) : ''
		);
	}

	public function channel_name_callback() {
		printf(
			'<input type="text" id="twitch_channel_name" name="twitch_channel_name" value="%s" required/>',
			isset( $this->twitch_channel_name ) ? esc_attr( $this->twitch_channel_name ) : ''
		);
	}

	function plugin_html_field_callback() {
		wp_editor( $this->html, 'twitch_html', [
			'textarea_name' => 'twitch_html', // nome dell'input
		] );
	}

	public function add_twitch_login_button( $content ) {

		if ( get_the_ID() != $this->page_id ) {
			return $content;
		}

		if ( isset( $_COOKIE['twitch_access_token'] ) ) {
			$access_token = $_COOKIE['twitch_access_token'];
			// Verifica se l'utente è iscritto al tuo canale
			$user_id = $this->get_twitch_user_id( $access_token );
			if ( ! $user_id || ! $this->is_user_subscribed( $user_id, $access_token ) ) {
				return $this->html;
			}

			return $content;
		} else {
			return '<p>Missing Twitch access token - TODO this part</p>';
		}
	}

	public function twitch_login_page_redirect() {
		if ( get_the_ID() != $this->page_id ) {
			return;
		}
		$this->get_twitch_access_token();
	}

	private function get_twitch_access_token() {

		if ( isset( $_COOKIE['twitch_access_token'] ) ) {
			return $_COOKIE['twitch_access_token'];
		}

		// Verifica se l'utente sta tentando di effettuare il login con Twitch
		if ( isset( $_GET['code'] ) ) {
			$code = $_GET['code'];

			// Richiesta di access token
			$url  = 'https://id.twitch.tv/oauth2/token';
			$data = array(
				'client_id'     => $this->twitch_client_id,
				'client_secret' => $this->twitch_client_secret,
				'code'          => $code,
				'grant_type'    => 'authorization_code',
				'redirect_uri'  => get_page_link( $this->page_id )
			);

			$response = wp_remote_post( $url, array(
				'body' => $data
			) );
			if ( ! is_wp_error( $response ) ) {
				$body         = json_decode( $response['body'], true );
				$access_token = $body['access_token'];
				setcookie( 'twitch_access_token', $access_token, time() + 3600, '/' );
				wp_redirect( get_page_link( $this->page_id ) );
				exit;
//				return $access_token;
			}
		}

		// Redirect all'URL di autenticazione di Twitch
		$url    = 'https://id.twitch.tv/oauth2/authorize';
		$params = array(
			'client_id'     => $this->twitch_client_id,
			'redirect_uri'  => get_page_link( $this->page_id ),
			'response_type' => 'code',
			'scope'         => 'user:read:subscriptions'
		);
		$url    .= '?' . http_build_query( $params );
		wp_redirect( $url );
		exit;
	}

	private function get_twitch_user_id( $access_token ) {
		// Richiesta dei dati dell'utente
		$url      = 'https://api.twitch.tv/helix/users';
		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Client-ID'     => $this->twitch_client_id,
				'Authorization' => 'Bearer ' . $access_token
			)
		) );

		if ( ! is_wp_error( $response ) ) {
			$body    = json_decode( $response['body'], true );
			$user_id = $body['data'][0]['id'];

			return $user_id;
		}

		return false;
	}

	private function is_user_subscribed( $user_id, $access_token ) {
		if ( ! $access_token || ! $user_id ) {
			return false;
		}

		// Verifica se l'utente è iscritto al tuo canale
		$twitch_channel_id = $this->get_twitch_channel_id( $access_token, $this->twitch_channel_name );
		$url               = 'https://api.twitch.tv/helix/subscriptions/user';
		$url               .= '?broadcaster_id=' . $twitch_channel_id;
		$url               .= '&user_id=' . $user_id;
		$response          = wp_remote_get( $url, array(
			'headers' => array(
				'Client-ID'     => $this->twitch_client_id,
				'Authorization' => 'Bearer ' . $access_token
			)
		) );

		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( $response['body'], true );
			write_log( $response['body'] );

			return isset( $body['data'] ) && count( $body['data'] ) > 0;
		}

		return false;
	}

	private function get_twitch_channel_id( $access_token, $channel_name ) {
		$url = 'https://api.twitch.tv/helix/users?login=' . $channel_name;

		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Client-ID'     => $this->twitch_client_id,
				'Authorization' => 'Bearer ' . $access_token
			)
		) );

		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( $response['body'], true );

			return $body['data'][0]['id'];
		}

		return false;
	}

}

new twitch_login();