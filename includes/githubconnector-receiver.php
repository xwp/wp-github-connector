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

		#debug
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			add_action(
				'github_connector_webhook',
				function(){
					update_option( 'gcw_log', var_export( $_REQUEST, 1 ) );
				}
				);
		}

		// Import repo information into separate post objects
		add_action( 'github_connector_webhook', array( $this, 'import' ) );
	}

	/**
	 * Parse webhook payload
	 * 
	 * @see  https://help.github.com/articles/post-receive-hooks
	 * @return void
	 */
	public function receive() {
		global $wpdb;

		# debug, should be removed
		// $payload = json_decode( $_REQUEST['payload'] );
		$payload = json_decode(
			'{
		   "after":"1481a2de7b2a7d02428ad93446ab166be7793fbb",
		   "before":"17c497ccc7cca9c2f735aa07e9e3813060ce9a6a",
		   "commits":[
		      {
		         "added":[
		 
		         ],
		         "author":{
		            "email":"lolwut@noway.biz",
		            "name":"Garen Torikian",
		            "username":"octokitty"
		         },
		         "committer":{
		            "email":"lolwut@noway.biz",
		            "name":"Garen Torikian",
		            "username":"octokitty"
		         },
		         "distinct":true,
		         "id":"c441029cf673f84c8b7db52d0a5944ee5c52ff89",
		         "message":"Test",
		         "modified":[
		            "README.md"
		         ],
		         "removed":[
		 
		         ],
		         "timestamp":"2013-02-22T13:50:07-08:00",
		         "url":"https://github.com/octokitty/testing/commit/c441029cf673f84c8b7db52d0a5944ee5c52ff89"
		      },
		      {
		         "added":[
		 
		         ],
		         "author":{
		            "email":"lolwut@noway.biz",
		            "name":"Garen Torikian",
		            "username":"octokitty"
		         },
		         "committer":{
		            "email":"lolwut@noway.biz",
		            "name":"Garen Torikian",
		            "username":"octokitty"
		         },
		         "distinct":true,
		         "id":"36c5f2243ed24de58284a96f2a643bed8c028658",
		         "message":"This is me testing the windows client.",
		         "modified":[
		            "README.md"
		         ],
		         "removed":[
		 
		         ],
		         "timestamp":"2013-02-22T14:07:13-08:00",
		         "url":"https://github.com/octokitty/testing/commit/36c5f2243ed24de58284a96f2a643bed8c028658"
		      },
		      {
		         "added":[
		            "words/madame-bovary.txt"
		         ],
		         "author":{
		            "email":"lolwut@noway.biz",
		            "name":"Garen Torikian",
		            "username":"octokitty"
		         },
		         "committer":{
		            "email":"lolwut@noway.biz",
		            "name":"Garen Torikian",
		            "username":"octokitty"
		         },
		         "distinct":true,
		         "id":"1481a2de7b2a7d02428ad93446ab166be7793fbb",
		         "message":"Rename madame-bovary.txt to words/madame-bovary.txt",
		         "modified":[
		 
		         ],
		         "removed":[
		            "madame-bovary.txt"
		         ],
		         "timestamp":"2013-03-12T08:14:29-07:00",
		         "url":"https://github.com/octokitty/testing/commit/1481a2de7b2a7d02428ad93446ab166be7793fbb"
		      }
		   ],
		   "compare":"https://github.com/octokitty/testing/compare/17c497ccc7cc...1481a2de7b2a",
		   "created":false,
		   "deleted":false,
		   "forced":false,
		   "head_commit":{
		      "added":[
		         "words/madame-bovary.txt"
		      ],
		      "author":{
		         "email":"lolwut@noway.biz",
		         "name":"Garen Torikian",
		         "username":"octokitty"
		      },
		      "committer":{
		         "email":"lolwut@noway.biz",
		         "name":"Garen Torikian",
		         "username":"octokitty"
		      },
		      "distinct":true,
		      "id":"1481a2de7b2a7d02428ad93446ab166be7793fbb",
		      "message":"Rename madame-bovary.txt to words/madame-bovary.txt",
		      "modified":[
		 
		      ],
		      "removed":[
		         "madame-bovary.txt"
		      ],
		      "timestamp":"2013-03-12T08:14:29-07:00",
		      "url":"https://github.com/octokitty/testing/commit/1481a2de7b2a7d02428ad93446ab166be7793fbb"
		   },
		   "pusher":{
		      "email":"lolwut@noway.biz",
		      "name":"Garen Torikian"
		   },
		   "ref":"refs/heads/master",
		   "repository":{
		      "created_at":1332977768,
		      "description":"",
		      "fork":false,
		      "forks":0,
		      "has_downloads":true,
		      "has_issues":true,
		      "has_wiki":true,
		      "homepage":"",
		      "id":3860742,
		      "language":"Ruby",
		      "master_branch":"master",
		      "name":"testing",
		      "open_issues":2,
		      "owner":{
		         "email":"lolwut@noway.biz",
		         "name":"octokitty"
		      },
		      "private":false,
		      "pushed_at":1363295520,
		      "size":2156,
		      "stargazers":1,
		      "url":"https://github.com/octokitty/testing",
		      "watchers":1
		   }
		}'
		);

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
				'gc_commit_data',
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

			do_action( 'gc_commit_saved', $post_id, $data );
		}

	}

}
