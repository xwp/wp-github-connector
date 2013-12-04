<?php
/**
 * Receiver class
 * Handles parsing and storage of information received via GitHub Webhook calls
 *
 * @author X-Team <x-team.com>
 * @author Shady Sharaf <shady@x-team.com>
 */
class GitHubConnector_Receiver {

	/**
	 * Imported objects ( repos / commits / comments )
	 * @var array
	 */
	public static $imported = array();

	/**
	 * Public constructor
	 */
	public function __construct() {
		// Import repo information into separate post objects
		add_action( 'github_connector_webhook', array( $this, 'import' ) );
	}

	/**
	 * Parse webhook payload
	 * 
	 * @see  https://help.github.com/articles/post-receive-hooks
	 * @throws GitHubConnectorException
	 * @return void
	 */
	public function receive() {
		$payload = json_decode( filter_input( INPUT_POST, 'payload', FILTER_SANITIZE_STRING ), false );
		if ( empty( $payload ) ) {
			throw new GitHubConnectorException( 'Missing payload' );
		}
		if ( ! is_object( $payload ) ) {
			throw new GitHubConnectorException( 'Payload is not an object' );
		}
		do_action( 'github_connector_webhook', $payload );
	}

	/**
	 * Translate github username to wordpress user
	 * 
	 * @param  string $username GitHub username
	 * @return WP_User          Matching User
	 */
	public function github_user_to_wp( $username ) {
		$user  = null;
		$query = new WP_User_Query(
			array(
			'meta_key'   => 'github_username',
			'meta_value' => $username,
			)
			);
		if ( $users = $query->results ) {
			$user = $users[0];
			self::$imported['users'][$username] = $user;
		}
		return $user;
	}

	public function import( $payload ) {
		global $wpdb;
		if ( empty( $payload->repository->owner->name ) ) {
			throw new GitHubConnectorException( 'Missing payload->repository->owner->name' );
		}
		if ( empty( $payload->repository->name ) ) {
			throw new GitHubConnectorException( 'Missing payload->repository->name' );
		}
		$repo_id = $payload->repository->owner->name . '/' . $payload->repository->name;

		// Get repo details, insert/update post object
		$repo_core = array(
			'post_name' => $payload->repository->name,
			'post_title' => $repo_id,
			'post_content' => $payload->repository->description,
			'post_type' => GitHubConnector_Settings::$options['post_settings_post_type_repo'],
			'post_status' => ( GitHubConnector_Settings::$options['post_settings_private_repo'] && $payload->repository->private ) ? 'private' : 'publish',
			);

		$hash = md5( json_encode( $repo_core ) );
		
		// Get corresponding post id, if not found, create a new post for the repo
		if ( $post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s", $repo_id ) ) ) { // db call okay, cache okay
			// Changed ?
			if ( $hash != get_post_field( 'post_content_filtered', $post_id ) ) {
				wp_update_post( $repo_core + array( 'ID' => $post_id, 'post_content_filtered' => $hash ) );
			}
		} else {
			$post_id = wp_insert_post( $repo_core + array( 'post_content_filtered' => $hash ) );
		}

		$this->import_commits( $payload, $post_id, $repo_core );
	}

	public function import_commits( $payload, $repo_id, $repo ) {
		$commits = $payload->commits;

		if ( empty( $commits ) ) {
			return;
		}

		foreach ( $commits as $commit ) {
			$user = $this->github_user_to_wp( $commit->committer->username );

			$data = apply_filters(
				'github_commit_data',
				array(
					'post_type' => GitHubConnector_Settings::$options['post_settings_post_type'],
					'post_author' => $user ? $user->ID : 'null',
					'post_title' => $commit->id,
					'post_content' => $commit->message,
					'post_date_gmt' => $commit->timestamp,
					'post_status' => $repo['post_status'],
					'post_parent' => $repo_id,
					'post_meta' => array(
						'_paths_added' => $commit->added,
						'_paths_removed' => $commit->removed,
						'_paths_modified' => $commit->modified,
						'_github_url' => $commit->url,
						'_github_author' => $commit->committer->username,
						)
					)
				);

			$post_id = wp_insert_post( $data );
			foreach ( $data['post_meta'] as $meta_key => $meta_values ) {
				foreach ( (array) $meta_values as $meta_value ) {
					add_post_meta( $post_id, $meta_key, $meta_value );
				}
			}

			do_action( 'github_commit_saved', $post_id, $data );
		}

	}

}
