<?php
/**
 * Recent GitHub Commits
 *
 * @author XWP <xwp.co>
 * @author Shady Sharaf <shady@xwp.co>
 */
class GitHubConnector_Widget_RecentCommits extends WP_Widget {

	/**
	 * Register widget
	 */
	function __construct() {
		parent::__construct(
			'ghw_recent',
			__( 'GitHub Recent Commits', 'github_connector' ),
			array(
				'classname' => 'ghw_recent',
				'description' => __( 'Add a custom menu, or part of one, as a widget' )
			)
		);
	}

	public function defaults() {
		return array(
			'title' => '',
			'repo' => 'all',
			'author' => 'all',
			'format' => ':author_alias commited :hash_short to :repo_full',
			);
	}

	public function widget( $args, $instance ) {
		$title = apply_filters( 'widget_title', $instance['title'] );

		$out = $args['before_widget'];
		if ( ! empty( $title ) )
			$out .= $args['before_title'] . $title . $args['after_title'];

		$args = array(
			'post_type' => GitHubConnector_Settings::$options['post_settings_post_type'],
			'meta_query' => array(),
			);

		if ( $instance['author'] == 'context' ) {
			if ( is_author() ) {
				$author_id = get_queried_object()->ID;
			}
			else {
				global $post;
				$author_id = $post->post_author;
			}
			if ( $author_id ) {
				$args['author'] = $author_id;
			}
		}

		// For future post_type=github_repo
		if ( $instance['repo'] == 'context' ) {
			global $post;
			if ( $post->post_type == GitHubConnector_Settings::$options['post_settings_post_type_repo'] ) {
				$args['meta_query'][] = array(
					'key' => '_github_repo',
					'value' => $post->ID,
					);
			}
			elseif ( $repo_id = get_post_meta( get_the_ID(), '_github_repo', true ) ) {
				$args['meta_query'][] = array(
					'key' => '_github_repo',
					'value' => $repo_id,
					);
			}
		}

		$out  .= '<ul>';
		$query = new WP_Query( $args );
		while ( $query->have_posts() ) {
			global $post;
			$query->the_post();
			$out .= '<li>';
			$repo = get_post( $post->post_parent );

			$replacements = array(
				'#:hash_short#'   => substr( $post->post_title, 0, 5 ),
				'#:hash#'         => $post->post_title,
				'#:repo_full#'    => $repo->post_title,
				'#:repo#'         => $repo->post_name,
				'#:author_id#'    => $post->post_author,
				'#:author_alias#' => $post->_github_author,
				);

			$row = preg_replace(
				array_keys( $replacements ),
				$replacements,
				$instance['format']
				);

			$row = sprintf(
				'<a href="%s">%s</a>',
				$post->_github_url,
				$row
				);

			$out .= apply_filters( 'github_connector_widget_item', $row );
			$out .= '</li>';
		}
		wp_reset_postdata();
		$out .= '</ul>';
		
		if ( isset( $args['after_widget'] ) ) {
			$out .= $args['after_widget'];
		}
		echo $out; // xss okay
	}

	public function form( $instance ) {
		$instance = wp_parse_args( $instance, $this->defaults() );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php _e( 'Title:' ); ?></label> 
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" type="text"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" 
				value="<?php echo esc_attr( $instance['title'] ); ?>" /> 
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'repo' ) ); ?>"><?php _e( 'Repository:' ); ?></label>
			<select name="<?php echo esc_attr( $this->get_field_name( 'repo' ) ); ?>" 
				id="<?php echo esc_attr( $this->get_field_id( 'repo' ) ); ?>">
				<option value="all" <?php selected( $instance['repo'], 'all' ) ?>><?php _e( 'All', 'github_connector' ) ?></option>
				<option value="context" <?php selected( $instance['repo'], 'context' ) ?>><?php _e( 'Page Context', 'github_connector' ) ?></option>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'Author' ) ); ?>"><?php _e( 'Author:' ); ?></label> 
			<select name="<?php echo esc_attr( $this->get_field_name( 'author' ) ); ?>" 
				id="<?php echo esc_attr( $this->get_field_id( 'author' ) ); ?>">
				<option value="all" <?php selected( $instance['author'], 'all' ) ?>><?php _e( 'All', 'github_connector' ) ?></option>
				<option value="context" <?php selected( $instance['author'], 'context' ) ?>><?php _e( 'Page Context', 'github_connector' ) ?></option>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'format' ) ); ?>"><?php _e( 'Format:' ); ?></label> 
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'format' ) ); ?>" type="text"
				name="<?php echo esc_attr( $this->get_field_name( 'format' ) ); ?>" 
				value="<?php echo esc_attr( $instance['format'] ); ?>" /> 
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		return $new_instance;
	}

	/**
	 * Register Widget
	 * @return void
	 */
	public static function register() {
		return register_widget( 'GitHubConnector_Widget_RecentCommits' );
	}

}
