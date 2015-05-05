<?php 

namespace TransmotoAPI;

class TransmotoRESTAPI_Posts extends TransmotoPosts
{

	/**
	 * /tm/popular endpoint for getting popular posts	 
	 * @param  array   $filter  Additional option parameters can be passed through. Currently supporting ?filter['posts_per_page']
	 * @param  string  $context N/A
	 * @param  string  $type    N/A
	 * @param  integer $page    The page ID. Used in pagination
	 * @return string           A json data burst. Either the popular posts, or a WP_Error
	 */
	public function get_popular_posts($filter = array(), $context = 'view', $type = 'posts', $page = 1)
	{
		global $wp_json_server;

		/* run error checking and process our standard inputs */
		$user_args = $this->do_bulk_standard_processing($filter, $page);	
		
		if(is_wp_error($user_args))
		{
			return $user_args;
		}

		 /*
		  * Use WP_Query as it gives us more control than get_posts
		  */ 					 				 				 		
		 
		 $args = array(
			'post_type'        => 'post',
			'meta_key'         => 'hits',
			'orderby'          => 'meta_value_num',
			'order'            => 'DESC',
			'post_status'      => 'publish',
			'category__not_in' => array(103,104,105,106),
			'meta_query'       => array(
				   array(
					   'key' => 'hits',
					   'value' => '0',
					   'compare' => '>',
				   )
		    ),
		    'date_query' => array(
		        array(  
		            'after' => '1 month ago',    
		        ),
		    ),

		 );		 

		$args = array_merge($args, $user_args);

		/*
		 * Prepare our objects to process the posts returned from our query 
		 */
		$wp_json_posts = new \WP_JSON_Posts($wp_json_server);
		$post_query    = new \WP_Query();
		$posts_list    = $post_query->query( $args );
		$response      = $this->set_post_details(new \WP_JSON_Response(), $posts_list, $post_query);

		$response = ($posts_list) ? $this->prepare_post_response($response, $posts_list) : $response;

		return $response;		
	}

	/**
	 * /tm/rotator endpoint for getting popular posts	 
	 * @param  array   $filter  Additional option parameters can be passed through. Currently supporting ?filter['posts_per_page']
	 * @param  string  $context N/A
	 * @param  string  $type    N/A
	 * @return string           A json data burst. Either the top 5 rotator posts, or a WP_Error
	 */
	public function get_rotator_posts($filter = array(), $context = 'view', $type = 'posts')
	{
		global $wp_json_server;
		
		if(is_wp_error($user_args))
		{
			return $user_args;
		}

		 /*
		  * Use WP_Query as it gives us more control than get_posts
		  */ 					 				 				 				 
		 $args = array(
			'posts_per_page'   => 5,
			'meta_query' => array(
				array(
					'key' => '_tm_rotator',
					'value' => 'on',
				),
				array(
					'key' => '_tm_rot_start_date',
					'value' => mktime(0,0,0),
					'compare' => '<=',
					'type' => 'NUMERIC'
				),
				array(
					'key' => '_tm_rot_end_date',
					'value' => mktime(0,0,0),
					'compare' => '>=',
					'type' => 'NUMERIC'
				)
			)
		 ); 	 

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
		 * If posts empty, get the last 5 posts with a rotator value regardless of the start and end dates
		 */
		if ( ! $posts_list ) {
			 $args = array(
				'posts_per_page'   => 5,
				'meta_query' => array(
					array(
						'key' => '_tm_rotator',
						'value' => 'on',
					)
				)
			 );			

			$wp_json_posts = new \WP_JSON_Posts($wp_json_server);
			$post_query    = new \WP_Query();
			$posts_list    = $post_query->query( $args );
			$response      = new \WP_JSON_Response();
			$response->query_navigation_headers( $post_query );
		}		

		$response = ($posts_list) ? $this->prepare_post_response($response, $posts_list, false) : $response;

		return $response;	
	}

	/**
	 * /posts/:id/related endpoint for getting related posts
	 * @param  Integer   $id  The post ID we should get related content for 
	 * @return string           A json data burst. Either the related posts, or a WP_Error
	 */
	public function get_related($id)
	{
		global $wp_json_server;
		
		/* get post categories and do a search on related content */
    	$categories = get_the_category((int) $id);

    	/* include the categories */
    	$exclude = TransmotoRESTAPI::$exclude_categories;
    	$include_categories = array();

    	foreach($categories as $category)
    	{
    		if(!in_array($category->term_id, $exclude))
    		{
    			$include_categories[] = $category->term_id;
    		}
    	}

    	/* if we cannot find any categories we will return nothing */
    	if (sizeof($include_categories) == 0) {  
    		$response      = new \WP_JSON_Response();
    		$response->set_data( array() );
    		return $response;
    	}      	

		$args = array(
			'category__in' => $include_categories,
			'post__not_in' => array((int) $id),
			'posts_per_page' => 5, // Number of related posts that will be shown.
		);	

		/*
		 * Prepare our objects to process the posts returned from our query 
		 */
		$wp_json_posts = new \WP_JSON_Posts($wp_json_server);
		$post_query    = new \WP_Query();
		$posts_list    = $post_query->query( $args );
		$response      = $this->set_post_details(new \WP_JSON_Response(), $posts_list, $post_query);

		$response = ($posts_list) ? $this->prepare_post_response($response, $posts_list, false) : $response;

		return $response;			
	}

	public function popular_filter_where( $where = '' ) {
		// posts in the last 30 days
		$where .= " AND post_date > '" . date('Y-m-d', strtotime('-30 days')) . "'";
		return $where;
	}	

	/**
	 * Do our standard error checking and processing of standard inputs
	 * @param  array $filter Any URL parametres passed through using ?filter[] 
	 * @param  integer $page   The page ID. Used for pagination
	 * @return array         The formatted results
	 */
	private function do_bulk_standard_processing($filter, $page)
	{		
		$posts_per_page = (isset($filter['posts_per_page'])) ? (int) $filter['posts_per_page'] : 16;
		$search         = (isset($filter['s'])) ? wp_kses($filter['s'], array()) : false;
		
		$keys = array(
			'offset' => $posts_per_page * ($page - 1),
			'limit'  => $posts_per_page,
		);		

		if($search !== false) {
			$keys['s'] = $search;
		}

		return $keys;
	}

	/**
	 * Add words and photography credits to the post API
	 * TODO - Write Unit Tests
	 * @param Array $_post   The post API array
	 * @param Array $post    The actual post data from WP_QUERY
	 * @param String $context Whether viewing/editing/deleting
	 */
	public function add_meta_data($_post, $post, $context) {		
		if($context == 'view' && ($post['post_type'] == 'post' || $post['post_type'] == 'premium')) {
			$_post['meta']['credit']                = array();
			$_post['meta']['credit']['words']       = get_post_meta( $post['ID'], '_tm_words_credit', true);
			$_post['meta']['credit']['photography'] = get_post_meta( $post['ID'], '_tm_photo_credit', true);
		}
		return $_post;
	}

}