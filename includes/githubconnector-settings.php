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
		// Register settings page
		add_action( 'admin_menu', array( $this, 'register_menu' ) );

		// Register settings, and fields
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Parse field information gathering default values
		$defaults = $this->get_defaults();

		// Generate a new auth key filter
		add_filter( 'github_connector_options', array( $this, 'filter_generate_auth_key' ) );

		// Get options from DB
		self::$options = apply_filters(
			'github_connector_options',
			wp_parse_args(
				(array) get_option( self::KEY, array() ),
				$defaults
			)
		);
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
	 * Render settings page
	 * 
	 * @return void
	 */
	public function render_page() {
		?>
		<div class="wrap">
			<?php screen_icon( 'tools' ); ?>
			<h2><?php _e( 'GitHubConnector Options', 'github_connector' ) ?></h2>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::KEY );
				do_settings_sections( self::KEY );
				submit_button();
				?>
			</form>
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
						'default' => apply_filters( 'gc_register_post_type_slug', 'github_commit' ),
						),
					array(
						'name'    => 'post_type_repo',
						'default' => apply_filters( 'gc_register_post_type_repo_slug', 'github_repo' ),
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
	

}
