<?php
/**
 * Users class
 * Handles management of WP Users against GitHub Users
 *
 * @author XWP <xwp.co>
 * @author Shady Sharaf <shady@xwp.co>
 */
class GitHubConnector_Users {

	public function __construct() {

		// Add fields to associate GitHub usernames to a user
		add_action( 'show_user_profile', array( $this, 'render_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'render_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_fields' ) );
	}

	public function get_fields() {
		return array(
			'github_connector' => array(
				'title' => __( 'GitHub Connector', 'github_connector' ),
				'fields' => array(
					array(
						'name' => 'github_username',
						'title' => __( 'GitHub Usernames', 'github_connector' ),
						'type' => 'text',
						'desc' => __( 'Separate usernames by colon', 'github_connector' ),
						),
					),
				),
			);
	}
	
	public function render_fields( $user ) {
		$fields = $this->get_fields();

		$out = '';

		foreach ( $fields as $section_name => $section ) {
			$out .= sprintf( '<h3>%s</h3>', $section['title'] );
			
			$out .= '<table class=form-table><tbody>';
			foreach ( $section['fields'] as $field ) {
				$rendered = ( method_exists( $this, 'render_field_' . $field['name'] ) )
					? call_user_func( array( $this, 'render_field_' . $field['name'] ), $field, $user )
					: $this->render_field( $field, $user );
				$out     .= sprintf(
					'<tr><th scope=row><label for="%s">%s</label></th><td>%s</td></tr>',
					esc_attr( $field['name'] ),
					esc_attr( $field['title'] ),
					$rendered
					);
			}
			$out .= '</tbody></table>';
		}

		echo $out; //xss okay
	}

	public function render_field( $field, $user ) {
		$user_id = $user->ID;
		switch ( $field['type'] ) {
			case 'text':
				$output = sprintf(
					'<input type="text" name="%s" id="%s" size="50" value="%s" class="regular-text" />',
					esc_attr( $field['name'] ),
					esc_attr( $field['name'] ),
					get_user_meta( $user_id, $field['name'], true )
					);
				break;
			case 'checkbox':
				$output = sprintf(
					'<input type="checkbox" name="%s" value="1" %s />',
					esc_attr( $field['name'] ),
					checked( get_user_meta( $user_id, $field['name'], true ), 1, false )
					);
				break;
		}
		if ( isset( $field['desc'] ) ) {
			$output .= sprintf( '<br/><span class="description">%s</span>', $field['desc'] );
		}
		return $output;
	}

	public function render_field_github_username( $field, $user ) {
		$user_id = $user->ID;
		$output  = sprintf(
			'<input type="text" name="%s" id="%s" size="50" value="%s" class="regular-text" />',
			esc_attr( $field['name'] ),
			esc_attr( $field['name'] ),
			implode( ', ', (array) get_user_meta( $user_id, $field['name'] ) )
			);
		$output .= sprintf( '<br/><span class="description">%s</span>', $field['desc'] );
		return $output;
	}

	public function save_fields( $user_id ) {
		$fields = $this->get_fields();

		foreach ( $fields as $section_name => $section ) {
			foreach ( $section['fields'] as $field ) {
				if ( method_exists( $this, 'save_field_' . $field['name'] ) ) {
					call_user_func( array( $this, 'save_field_' . $field['name'] ), $field, $user_id );
				}
				else {
					$this->save_field( $field, $user_id );
				}
			}
		}
	}

	public function save_field( $field, $user_id ) {
		update_user_meta(
			$user_id,
			$field['name'],
			filter_input( INPUT_POST, $field['name'] )
			);
	}

	public function save_field_github_username( $field, $user_id ) {
		$values = explode( ',', filter_input( INPUT_POST, $field['name'] ) );
		delete_user_meta( $user_id, $field['name'] );
		foreach ( $values as $value ) {
			add_user_meta(
				$user_id,
				$field['name'],
				trim( $value )
				);
		}
	}
}
