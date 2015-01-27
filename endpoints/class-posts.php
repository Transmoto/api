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
	 * @return string           A json data burst. Either all the ads, or a WP_Error
	 */
	public function get_popular_posts($filter = array(), $context = 'view', $type = 'trader', $page = 1)
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
		
		return array(
			'offset' => $posts_per_page * ($page - 1),
			'limit' => $posts_per_page,
		);		
	}	
}