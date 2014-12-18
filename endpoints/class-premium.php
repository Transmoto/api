<?php 

namespace TransmotoAPI;

class TransmotoRESTAPI_Premium
{
	private $category_name = 'premium_category';
	private $post_name     = 'premium';

	/**
	 * /premium/cat/ endpoint for getting all premium top-level categories
	 * @return string     A json data burst. Either all the categories, or a WP_Error
	 */
	public function get_categories()
	{
		/* run error checking and process our standard inputs */
		$args = $this->do_standard_processing();

		if(is_wp_error($args))
		{
			return $args;
		}

		$args = array(
			'taxonomy' => $this->category_name,
			'hierarchical' => false,
		);

		$categories = get_categories( $args );

		echo json_encode($this->clean_categories($categories));
		exit;
	}

	/**
	 * /premium/cat/(?P<id>\d+) endpoint for getting a list of premium content assigned to the category
	 * @param  Integer $id      The category ID
	 * @param  string $context N/A
	 * @return string           A json data burst. Either the ads, or a WP_Error
	 */
	public function get_category_posts($filter = array(), $id, $context = 'view', $page = 1)
	{
		global $wp_json_server;

		/* run error checking and process our standard inputs */
		$user_args = $this->do_bulk_standard_processing($filter, $page);	
		
		if(is_wp_error($user_args))
		{
			return $user_args;
		}

		$args = array(
			'post_type' => $this->post_name, 
			'tax_query' => array(
				array(
					'taxonomy'         => $this->category_name,
					'field'            => 'term_id',
					'terms'            => (int) $id,
					'include_children' => false,
				),
			),	
			'orderby' => array(
				'meta_value' => 'DESC',
				'date' => 'DESC'
			),
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key' => 'freebie',
					'compare' => 'EXISTS'		
				),
				array(
					'key' => 'freebie',
					'compare' => 'NOT EXISTS'
				)

			),

		);

		$args = array_merge($args, $user_args);

		/*
		 * Prepare our objects to process the posts returned from our query 
		 */
		$wp_json_posts = new \WP_JSON_Posts($wp_json_server);
		$post_query    = new \WP_Query();
		$posts_list    = $post_query->query( $args );
		$response      = new \WP_JSON_Response();


		/*
		 * Set up our JSON return headers 
		 */
		$response->query_navigation_headers( $post_query );

		/*
		 * If posts empty, return early.
		 */
		if ( ! $posts_list ) {
			$response->set_data( array() );
			return $response;
		}

		/* holds all the posts data */
		$struct = array();

		/* Add to response header */
		$response->header( 'Last-Modified', mysql2date( 'D, d M Y H:i:s', get_lastpostmodified( 'GMT' ), 0 ).' GMT' );

		/* Loop through posts and return post object */
		foreach ( $posts_list as $post ) {
			$post = get_object_vars( $post );

			/* process post data */
			$post_data = $this->prepare_post( $post );
			if ( is_wp_error( $post_data ) ) {
				continue;
			}

			/* assign cleaned post data to the returning data array */
			$struct[] = $this->clean($post_data);
		}

		/* set and return the array */
		$response->set_data( $struct );		
		return $response;	
	}	

	/**
	 * /premium/(?P<id>\d+) endpoint for getting a single post
	 * @param  Integer $id      The post ID
	 * @param  string $context N/A
	 * @return string           A json data burst. Either the ads, or a WP_Error
	 */
	public function get_post($id, $context = 'view')
	{
		/* run error checking and process our standard inputs */
		$valid = $this->do_standard_processing();	
		
		if(is_wp_error($valid))
		{
			return $valid;
		}

		/* get our post information */
		$post = get_post( $id, ARRAY_A );

		/* ensure the IDs are valid */
		if ( empty( $id ) || empty( $post['ID'] ) ) {
			return new \WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		/* ensure this is actually a 'premium' custom post type */
		if ( $post['post_type'] !== 'premium' ) {
			return new \WP_Error( 'json_user_cannot_read', __( 'Post not marked as "premium" content. Use /wp-json/posts/{id} endpoint instead.' ), array( 'status' => 400 ) );
		}

		/* Prepare our post structure */
		$post = $this->prepare_post( $post, $context );

		/*
		 * TODO
		 * Ensure the user requesting this content has ACTUALLY paid for it
		 * Need to see how mobile app devs want this functionality to process. 
		 * Knowning the quality of developers local to their area, most likely nothing... 
		 *
		 * It should validate against APP Store or PLAY store that a payment has been made for this user...
		 */
		if($post['free'] === false)
		{
			/* set up error handling now */
			return new \WP_Error( 'json_user_cannot_read', __( 'Authentication required to view this article.' ), array( 'status' => 401 ) );
		}


		/*
		 * Do our response
		 */
		$response = new \WP_JSON_Response();
		$response->header( 'Last-Modified', mysql2date( 'D, d M Y H:i:s', $post['post_modified_gmt'] ) . 'GMT' );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		/*
		 * Set and return data 
		 */
		$response->set_data( $post );
		return $response;		
	}	

	/**
	 * Do our standard error checking and processing of standard inputs
	 * @param  array $filter Any URL parametres passed through using ?filter[] 
	 * @param  integer $page   The page ID. Used for pagination
	 * @return array         The formatted results
	 */
	private function do_standard_processing()
	{	
		/*
		 * Check if our dependancies are available
		 */	
		if(!taxonomy_exists( $this->category_name ) || !post_type_exists( $this->post_name ) || !function_exists('pods')	)
		{
			return new \WP_Error( 'json_trader_disabled', __( "Sorry, Transmoto's premium content is currently unavailable." ), array( 'status' => 503 ) );
		}
	}

	/**
	 * Do our standard error checking and processing of standard inputs
	 * @param  array $filter Any URL parametres passed through using ?filter[] 
	 * @param  integer $page   The page ID. Used for pagination
	 * @return array         The formatted results
	 */
	private function do_bulk_standard_processing($filter, $page)
	{		
		$process = $this->do_standard_processing();
		if(is_wp_error($process))
		{
			return $process;
		}

		$posts_per_page = (isset($filter['posts_per_page'])) ? (int) $filter['posts_per_page'] : 16;
		
		return array(
			'offset' => $posts_per_page * ($page - 1),
			'posts_per_page' => $posts_per_page,
		);		
	}	

	/**
	 * Ensure we only pass certain information back to the user 
	 * so as to not give away our premium content
	 * @param  Array $post The full post array 
	 * @return Array       The cleaned post array 
	 */
	private function clean($post)
	{
		$valid_keys = array(
			'ID',
			'title',
			'date',
			'date_gmt',
			'featured_image',
			'excerpt',
			'free',
		);

		$cleaned_post = array();

		foreach($post as $key => $value)
		{
			if(in_array($key, $valid_keys))
			{
				$cleaned_post[$key] = $value;
			}
		}

		return $cleaned_post;
	}

	/**
	 * Clean up the returned category data so as to not pass any unneeded information 	
	 * @param  Array $categories The categories array
	 * @return array             The cleaned and processed category array
	 */
	private function clean_categories($categories)
	{
		$allowed_keys = array(
			'term_id', 
			'name', 
			'description', 
			'count'
		);

		/* will contain our cleaned array data */
		$cleaned = array();

		/*
		 * Generate our pods asset so we can get extra fields 
		 */
		$pod = pods( $this->category_name );				

		/* Loop through the categories */
		foreach($categories as $id => $cat)
		{
			/* loop through the allowed keys */
			foreach($allowed_keys as $k)
			{
				/* if key is found in the category data include it */
				if(isset($cat->$k))
				{
					$cleaned[$id][$k] = $cat->$k;
				}
			}

			/*
			 * Add featured image to our category list 
			 * This is an extension of the PODS custom taxonomy functionality
			 */
			$pod->fetch($cat->term_id);
			
			/* Get the image object */
			$image       = false;
			$imageObject = $pod->field('cover_image');
						
			/* If the image object exists include it */			
			if(isset($imageObject['ID']))
			{			
				/* TODO: get the correct image specification */
				$image = wp_get_attachment_image_src($imageObject['ID'], 'full');
			}
			
			$cleaned[$id]['featured_image'] = $image;			
		}

		/* return the cleaned array */
		return $cleaned;
	}

	/**
	 * Prepare the $post array with all the necessary information for the API. 
	 * Modelled on the prepare_post() function in the class-wp-json-posts.php file, 
	 * however the method was protected and we needed to modify the security 
	 * 
	 * @param  array $post The standard $post data 
	 * @return array       The extended $post data
	 */
	private function prepare_post($post)
	{	
		// Holds the data for this post.
		$_post = array( 'ID' => (int) $post['ID'] );

		$post_type = get_post_type_object( $post['post_type'] );

		$previous_post = null;
		if ( ! empty( $GLOBALS['post'] ) ) {
			$previous_post = $GLOBALS['post'];
		}
		$post_obj = get_post( $post['ID'] );

		$GLOBALS['post'] = $post_obj;
		setup_postdata( $post_obj );

		// prepare common post fields
		$post_fields = array(
			'title'           => get_the_title( $post['ID'] ), // $post['post_title'],
			'status'          => $post['post_status'],
			'type'            => $post['post_type'],
			'author'          => (int) $post['post_author'],
			'content'         => apply_filters( 'the_content', $post['post_content'] ),
			'parent'          => (int) $post['post_parent'],
			#'post_mime_type' => $post['post_mime_type'],
			'link'            => get_permalink( $post['ID'] ),
		);

		$post_fields_extended = array(
			'slug'           => $post['post_name'],
			'guid'           => apply_filters( 'get_the_guid', $post['guid'] ),
			'excerpt'        => $this->prepare_excerpt( $post['post_excerpt'] ),
			'menu_order'     => (int) $post['menu_order'],
			'comment_status' => $post['comment_status'],
			'ping_status'    => $post['ping_status'],
			'sticky'         => ( $post['post_type'] === 'post' && is_sticky( $post['ID'] ) ),
		);

		$post_fields_raw = array(
			'title_raw'   => $post['post_title'],
			'content_raw' => $post['post_content'],
			'excerpt_raw' => $post['post_excerpt'],
			'guid_raw'    => $post['guid'],
		);

		// Dates
		if ( $post['post_date_gmt'] === '0000-00-00 00:00:00' ) {
			$post_fields['date'] = null;
			$post_fields_extended['date_gmt'] = null;
		}
		else {
			$post_fields['date']              = json_mysql_to_rfc3339( $post['post_date'] );
			$post_fields_extended['date_gmt'] = json_mysql_to_rfc3339( $post['post_date_gmt'] );
		}

		if ( $post['post_modified_gmt'] === '0000-00-00 00:00:00' ) {
			$post_fields['modified'] = null;
			$post_fields_extended['modified_gmt'] = null;
		}
		else {
			$post_fields['modified']              = json_mysql_to_rfc3339( $post['post_modified'] );
			$post_fields_extended['modified_gmt'] = json_mysql_to_rfc3339( $post['post_modified_gmt'] );
		}

		// Consider future posts as published
		if ( $post_fields['status'] === 'future' ) {
			$post_fields['status'] = 'publish';
		}

		// Merge requested $post_fields fields into $_post
		$_post = array_merge( $_post, $post_fields );

		// Include extended fields. We might come back to this.
		$_post = array_merge( $_post, $post_fields_extended );

		// Entity meta
		$links = array(
			'self'       => json_url( '/posts/' . $post['ID'] ),
			'author'     => json_url( '/users/' . $post['post_author'] ),
			'collection' => json_url( '/posts' ),
		);

		if ( 'view-revision' != $context ) {
			$links['replies'] = json_url( '/posts/' . $post['ID'] . '/comments' );
			$links['version-history'] = json_url( '/posts/' . $post['ID'] . '/revisions' );
		}

		$_post['meta'] = array( 'links' => $links );

		if ( ! empty( $post['post_parent'] ) ) {
			$_post['meta']['links']['up'] = json_url( '/posts/' . (int) $post['post_parent'] );
		}		

		/* include whether the post is a freebee, or locked down */
		$free          = get_post_meta( (int) $post['ID'], 'freebie', true);
		$_post['free'] = ($free == 1) ? true : false;		

		$GLOBALS['post'] = $previous_post;
		if ( $previous_post ) {
			setup_postdata( $previous_post );
		}

		return apply_filters( 'json_prepare_post', $_post, $post, 'view' );		
	}

	/**
	 * Retrieve the post excerpt.
	 *
	 * @return string
	 */
	protected function prepare_excerpt( $excerpt ) {
		if ( post_password_required() ) {
			return __( 'There is no excerpt because this is a protected post.' );
		}

		$excerpt = apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', $excerpt ) );

		if ( empty( $excerpt ) ) {
			return null;
		}

		return $excerpt;
	}


}