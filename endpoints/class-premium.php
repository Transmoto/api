<?php 

namespace TransmotoAPI;

class TransmotoRESTAPI_Premium extends TransmotoPosts
{
	protected $category_name = 'premium_category';
	protected $post_name     = 'premium';

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
				'meta_value_num' => 'DESC',
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
}