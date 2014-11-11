<?php
/**
 * Plugin Name: GitHub Connector
 * Plugin URI: https://xwp.co
 * Description: Imports GitHub commits/comments as posts
 * Version: 0.1
 * Author: XWP, Shady Sharaf
 * Author URI: https://xwp.co/
 * License: GPLv2+
 * Text Domain: github_connector
 * Domain Path: /languages
 */

/**
 * Copyright (c) 2013 XWP (https://xwp.co/)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

class GitHubConnector {

	/**
	 * Class name in lowercase
	 *
	 * @var string
	 * @access private
	 */
	private static $class_name;

	/**
	 * Constructor | Add required hooks
	 *
	 * @access public
	 *
	 * @return \GitHubConnector
	 */
	public function __construct() {
		self::$class_name = strtolower( __CLASS__ );

		add_action( 'init', array( $this, 'define_constants' ), 1 );

		add_action( 'init', array( $this, 'setup' ), 1 );

		add_action( 'init', array( $this, 'register_post_type' ) );

		add_action( 'plugins_loaded', array( $this, 'i18n' ), 2 );

		add_action( 'wp_ajax_nopriv_github_webhook', array( $this, 'webhook_receive' ) );
		
	}

	/**
	 * Setup settings and required classes
	 *
	 * @access public
	 * @action init
	 * @return void
	 */
	public function setup() {
		// Register settings
		require_once( GITHUB_CONNECTOR_INCLUDES_DIR . '/' . self::$class_name . '-settings.php' );
		$settings = new GitHubConnector_Settings;

		// User profile fields
		require_once( GITHUB_CONNECTOR_INCLUDES_DIR . '/' . self::$class_name . '-users.php' );
		$user = new GitHubConnector_Users;

		// Widgets
		require_once( GITHUB_CONNECTOR_INCLUDES_DIR . '/widgets/' . self::$class_name . '-widget-recent-commits.php' );
		add_action( 'widgets_init', array( 'GitHubConnector_Widget_RecentCommits', 'register' ) );
	}

	/**
	* Registers required post types and taxonomies
	*
	* @action init
	* @return void
	*/
	public function register_post_type() {

		$singular = __( 'Commit', 'github_connector' );
		$plural   = __( 'GitHub Commits', 'github_connector' );

		$labels = array(
			'name'                => $plural,
			'singular_name'       => $singular,
			'add_new'             => sprintf( __( 'Add New %s', 'github_connector' ), $singular ),
			'add_new_item'        => sprintf( __( 'Add New %s', 'github_connector' ), $singular ),
			'edit_item'           => sprintf( __( 'Edit %s', 'github_connector' ), $singular ),
			'new_item'            => sprintf( __( 'New %s', 'github_connector' ), $singular ),
			'view_item'           => sprintf( __( 'View %s', 'github_connector' ), $singular ),
			'search_items'        => sprintf( __( 'Search %s', 'github_connector' ), $plural ),
			'not_found'           => sprintf( __( 'No %s found', 'github_connector' ), $plural ),
			'not_found_in_trash'  => sprintf( __( 'No %s found in Trash', 'github_connector' ), $plural ),
			'parent_item_colon'   => sprintf( __( 'Parent %s:', 'github_connector' ), $singular ),
			'menu_name'           => $plural,
		);

		$args = array(
			'labels'              => $labels,
			'hierarchical'        => false,
			'description'         => 'description',
			'taxonomies'          => array(),
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_admin_bar'   => true,
			'menu_position'       => null,
			'menu_icon'           => null,
			'show_in_nav_menus'   => true,
			'publicly_queryable'  => true,
			'exclude_from_search' => false,
			'has_archive'         => true,
			'query_var'           => true,
			'can_export'          => true,
			'rewrite'             => true,
			'capability_type'     => 'post',
			'supports'            => array(
									'title', 'editor', 'author', 'comments',
									)
		);

		register_post_type(
			GitHubConnector_Settings::$options['post_settings_post_type'],
			apply_filters( 'github_register_post_type_args', $args )
			);
	}

	/**
	 * Define constants used by the plugin.
	 *
	 * @access public
	 * @action plugins_loaded
	 * @return void
	 */
	public function define_constants() {
		define( 'GITHUB_CONNECTOR_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
		define( 'GITHUB_CONNECTOR_INCLUDES_DIR', GITHUB_CONNECTOR_DIR . trailingslashit( 'includes' ) );
		define( 'GITHUB_CONNECTOR_URL', trailingslashit( plugin_dir_url( '' ) ) . basename( dirname( __FILE__ ) ) );
	}

	/**
	 * Loads the translation files.
	 *
	 * @access public
	 * @action plugins_loaded
	 * @return void
	 */
	public function i18n() {
		load_plugin_textdomain( 'github_connector', false,  dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Handle webhook updates from GitHub, authenticates request against local authentication key
	 * @access public
	 * @action wp_ajax_github_webhook
	 * @return void
	 */
	public function webhook_receive() {
		try {
			// Authenticate request against local_auth
			if ( filter_input( INPUT_GET, 'auth' ) !== GitHubConnector_Settings::$options['security_local_auth'] ) {
				throw new GitHubConnectorException( 'Wrong authentication key', 403 );
			}

			require_once( GITHUB_CONNECTOR_INCLUDES_DIR . '/' . self::$class_name . '-receiver.php' );
			$receiver = new GitHubConnector_Receiver();
			$receiver->receive();
			delete_option( 'github_connector_error' );
			wp_send_json_success();
		}
		catch( Exception $e ) {
			$code    = 500;
			$message = 'Internal Server Error';
			if ( $e instanceof GitHubConnectorException ) {
				$message = $e->getMessage();
				if ( $e->getCode() >= 400 && $e->getCode() < 600 ) {
					$code = $e->getCode();
				}
			}
			else {
				error_log( __FUNCTION__ . ': ' . $e->getMessage() );
			}
			update_option( 'github_connector_error', array(
				'message' => $e->getMessage(),
				'code' => $e->getCode(),
				'query' => wp_unslash( $_REQUEST ),
			) );
			status_header( $code );
			wp_send_json_error( $message );
		}
	}

}

class GitHubConnectorException extends Exception {}

// Register global plugin controller
$GLOBALS['github_connector'] = new GitHubConnector();
