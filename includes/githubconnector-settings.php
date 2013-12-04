<?php
/**
 * Settings class
 *
 * @author X-Team <x-team.com>
 * @author Shady Sharaf <shady@x-team.com>
 */
class GitHubConnector_Settings {

	/**
	 * Settings key/identifier
	 */
	const KEY = 'github_connector';

	const ADMIN_PAGE_SLUG   = 'github_connector';
	const ADMIN_PARENT_PAGE = 'options-general.php';

	/**
	 * Plugin settings
	 * 
	 * @var array
	 */
	public static $options = array();

	/**
	 * Menu page screen id
	 * 
	 * @var string
	 */
	public static $screen_id;

	/**
	 * Public constructor
	 *
	 * @return \GitHubConnector_Settings
	 */
	public function __construct() {

		// Parse field information gathering default values
		$defaults = $this->get_defaults();

		// Get options from DB
		self::$options = apply_filters(
			'github_connector_options',
			wp_parse_args(
				(array) get_option( self::KEY, array() ),
				$defaults
			)
		);

		if ( is_admin() ) {
			// Register settings page
			add_action( 'admin_menu', array( $this, 'register_menu' ) );

			// Generate a new auth key
			add_filter( 'github_connector_options', array( $this, 'filter_generate_auth_key' ) );

			// Register settings, and fields
			add_action( 'admin_init', array( $this, 'register_settings' ) );

			// Scripts and styles for admin page
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

			// Plugin action links
			add_filter( 'plugin_action_links', array( $this, 'admin_plugin_action_links' ), 10, 2 );
		}
	}

	public function is_front_end() {
		return ! (
			is_admin()
			||
			in_array( $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-register.php' ) )
		);
	}

	/**
	 * @filter plugin_action_links
	 */
	public static function admin_plugin_action_links( $links, $file ) {
		if ( plugin_basename( __FILE__ ) === $file ) {
			$admin_page_url  = admin_url( sprintf( '%s?page=%s', self::ADMIN_PARENT_PAGE, self::ADMIN_PAGE_SLUG ) );
			$admin_page_link = sprintf( '<a href="%s">%s</a>', esc_url( $admin_page_url ), esc_html__( 'Settings', 'dependency-minification' ) );
			array_push( $links, $admin_page_link );
		}
		return $links;
	}

	/**
	 * Register menu page
	 *
	 * @action admin_menu
	 * @return void
	 */
	public function register_menu() {
		if ( current_user_can( 'manage_options' ) ) {
			self::$screen_id = add_options_page(
				__( 'GitHubConnector', 'github_connector' ),
				__( 'GitHubConnector', 'github_connector' ),
				'manage_options',
				self::KEY,
				array( $this, 'render_page' )
			);
		}
	}

	/**
	 * @action admin_enqueue_scripts
	 *
	 * @return void
	 */
	static function admin_enqueue_scripts( $hook ) {
		if ( $hook !== self::$screen_id ) {
			return;
		}
		wp_enqueue_script( 'githubconnector-admin', plugins_url( 'ui/admin.js' , dirname( __FILE__ ) ), array( 'jquery' ) );
		wp_enqueue_style( 'githubconnector-admin', plugins_url( 'ui/admin.js' , dirname( __FILE__ ) ), array() );
	}

	/**
	 * Render settings page
	 * 
	 * @return void
	 */
	public function render_page() {

		if ( filter_input( INPUT_GET, 'remap' ) && $_POST ) {
			$message = $this->remap_users(
				filter_input( INPUT_POST, 'github_author', FILTER_SANITIZE_STRING ),
				filter_input( INPUT_POST, 'wp_user', FILTER_SANITIZE_NUMBER_INT )
				);
		}

		?>
		<div class="wrap">
			<?php screen_icon( 'tools' ); ?>
			<h2><?php _e( 'GitHubConnector Options', 'github_connector' ) ?></h2>

			<?php if ( isset( $message ) ) : ?>
			<div class="updated">
				<p><?php echo $message // xss okay ?></p>
			</div>
			<?php endif ?>

			<h2 class="nav-tab-wrapper">
				<a href="#tab-settings" class="nav-tab">
					<?php esc_html_e( 'Settings', 'github_connector' ) ?>
				</a>
				<a href="#tab-remap" class="nav-tab">
					<?php esc_html_e( 'Remapping', 'github_connector' ) ?>
				</a>
			</h2>
			<div class="nav-tab-content" id="tab-content-settings">
				<form method="post" action="options.php">
					<?php
					settings_fields( self::KEY );
					do_settings_sections( self::KEY );
					submit_button();
					?>
				</form>
			</div>
			<div class="nav-tab-content" id="tab-content-remap">
				<form method="post" action="<?php echo esc_url_raw( add_query_arg( 'remap', 1 ) ) ?>#tab-remap" id="remap-form">
					<h3><?php _e( 'Remap GitHub Users', 'github_connector' ) ?></h3>
					
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row">
									<label for="github_author"><?php _e( 'GitHub Author', 'github_connector' ) ?></label>
								</th>
								<td>
									<select name="github_author" id="github_author">
										<option></option>
										<?php foreach ( $this->get_github_authors() as $author ): ?>
										<option value="<?php echo esc_html( $author ) ?>"><?php echo esc_html( $author ) ?></option>
										<?php endforeach ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="wp_user"><?php _e( 'WordPress User', 'github_connector' ) ?></label>
								</th>
								<td>
									<select name="wp_user" id="wp_user">
										<option value=""></option>
										<?php foreach ( get_users() as $user ): ?>
										<option value="<?php echo intval( $user->ID ) ?>"><?php echo esc_html( $user->display_name ) ?></option>
										<?php endforeach ?>
									</select>
								</td>
							</tr>
						</tbody>
					</table>
					
					<?php wp_nonce_field( 'github_connector' ); ?>
					<?php submit_button(); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Return settings fields
	 * 
	 * @return array Multidimensional array of fields
	 */
	public function get_fields() {
		return array(
			'security' => array(
				'title'  => __( 'Security Settings', 'github_connector' ),
				'fields' => array(
					array(
						'name'    => 'local_auth',
						'title'   => __( 'Local Authentication Key', 'github_connector' ),
						'type'    => 'text',
						'desc'    => __( 'Authentication key required locally to authenticate Github webhook requests', 'github_connector' ),
						'default' => '',
						),
					),
				),
			'post_settings' => array(
				'title'  => __( 'Post Settings', 'github_connector' ),
				'fields' => array(
					array(
						'name' => 'private_repo',
						'title' => __( 'Mark Private Commits as private', 'github_connector' ),
						'type' => 'checkbox',
						'default' => 0,
						),
					array(
						'name'    => 'post_type',
						'default' => apply_filters( 'github_register_post_type_slug', 'github_commit' ),
						),
					array(
						'name'    => 'post_type_repo',
						'default' => apply_filters( 'github_register_post_type_repo_slug', 'github_repo' ),
						),
					),
				),
			);
	}

	/**
	 * Iterate through registered fields and extract default values
	 * 
	 * @return array Default option values
	 */
	public function get_defaults() {
		$fields   = $this->get_fields();
		$defaults = array();
		foreach ( $fields as $section_name => $section ) {
			foreach ( $section['fields'] as $field ) {
				$defaults[$section_name.'_'.$field['name']] = isset( $field['default'] )
					? $field['default']
					: null;
			}
		}
		return $defaults;
	}

	/**
	 * Registers settings fields and sections
	 * 
	 * @return void
	 */
	public function register_settings() {

		$fields = $this->get_fields();

		register_setting( self::KEY, self::KEY );

		foreach ( $fields as $section_name => $section ) {
			add_settings_section(
				$section_name,
				$section['title'],
				'__return_false',
				self::KEY
			);

			foreach ( $section['fields'] as $field_idx => $field ) {
				if ( ! isset( $field['type'] ) ) { // No field type associated, skip, no GUI
					continue;
				}
				add_settings_field(
					$field['name'],
					$field['title'],
					( 
						isset( $field['callback'] ) 
						? $field['callback']
						: array( $this, 'output_field' )
						),
					self::KEY,
					$section_name,
					$field + array( 'section' => $section_name )
				);
			}
		}
	}

	/**
	 * Compile HTML needed for displaying the field
	 * 
	 * @param  array  $field  Field settings
	 * @return string         HTML to be displayed
	 */
	public function render_field( $field ) {

		switch ( $field['type'] ) {
			case 'text':
				$output = sprintf(
					'<input type="text" name="%s[%s_%s]" size="50" value="%s" />',
					self::KEY,
					$field['section'],
					esc_attr( $field['name'] ),
					self::$options[$field['section'].'_'.$field['name']]
					);
				break;
			case 'checkbox':
				$output = sprintf(
					'<input type="checkbox" name="%s[%s_%s]" value="1" %s />',
					self::KEY,
					$field['section'],
					esc_attr( $field['name'] ),
					checked( self::$options[$field['section'].'_'.$field['name']], 1, false )
					);
				break;
		}

		if ( isset( $field['desc'] ) ) {
			$output .= sprintf(
				'<p class="description">%s</p>',
				$field[ 'desc' ]
			);
		}

		return $output;
	}

	/**
	 * Render Callback for post_types field
	 * 
	 * @param $args
	 * @return void
	 */
	public function output_field( $field ) {
		
		if ( method_exists( $this, 'output_' . $field['name'] ) ) {
			return call_user_func( array( $this, 'output_' . $field['name'] ), $field );
		}

		$output = $this->render_field( $field );
		echo $output; // xss okay
	}

	/**
	 * Callback for local_auth field
	 * 
	 * @param  array  $field Field attributes
	 * @return void
	 */
	public function output_local_auth( $field ) {
		if ( empty( self::$options['security_local_auth'] ) ) {
			$field['desc'] .= sprintf(
				' <a href="%s">%s</a>',
				add_query_arg( 'action', 'generate_auth_key' ),
				__( 'Generate one now', 'github_connector' )
				);
		}
		$html = $this->render_field( $field );
		echo $html; // xss okay
	}

	/**
	 * Action to generate random auth key for webhook requests authentication
	 *
	 * @filter github_connector_options
	 * @return void
	 */
	public function filter_generate_auth_key( $options ) {
		if ( 
			filter_input( INPUT_GET, 'action' ) == 'generate_auth_key'
			&&
			empty( $options['security_local_auth'] )
			) {
			$options['security_local_auth'] = md5( time() . mt_rand() );
			update_option( self::KEY, $options );
		}
		return $options;
	}

	/**
	 * Get all registered GitHub usernames stored in DB
	 * @return array  Array of usernames ( strings )
	 */
	public function get_github_authors() {
		global $wpdb;
		$query     = "SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = %s";
		$usernames = $wpdb->get_col( $wpdb->prepare( $query, '_github_author' ) );
		return $usernames;
	}

	public function remap_users( $gh_author, $wp_user ) {
		if ( empty ( $gh_author ) || empty( $wp_user ) ) {
			return __( 'You must select both GitHub Author and WordPress User to execute the remapping process.', 'github_connector' );
		}

		// Remove this alias from current holding user
		$users = get_users(
			array(
				'meta_key'    => '_github_username',
				'meta_value'  => $gh_author,
				'count_total' => false,
				'fields'      => 'ID',
				)
			);
		if ( $users ) {
			foreach ( $users as $user_id ) {
				delete_user_meta( $user_id, '_github_author', $gh_author );
			}
		}

		// Add GH alias to this user
		add_user_meta( $wp_user, '_github_author', $gh_author );

		// Find older posts
		$post_ids = get_posts(
			array(
				'post_type'   => self::$options['post_settings_post_type'],
				'meta_key'    => '_github_author',
				'meta_value'  => $gh_author,
				'fields'      => 'ids',
				'post_status' => 'any',
				)
			);
		
		// Change author of those found posts
		global $wpdb;
		$ids    = implode( ',', $post_ids );
		$query  = "UPDATE $wpdb->posts SET post_author = %d WHERE ID IN ( $ids ) ";
		$result = $wpdb->query( $wpdb->prepare( $query, $wp_user ) );
		
		$wp_user_data = get_userdata( $wp_user );
		return sprintf(
			__( 'Remapping of GitHub username <strong>%s</strong> to WordPress user <strong>%s</strong> has been successfully finished.', 'github_connector' ),
			$gh_author,
			$wp_user_data->display_name
			);
	}
	

}
