<?php 

namespace TransmotoAPI;

class TransmotoRESTAPI_Premium extends TransmotoPosts
{
	protected $category_name = 'premium_category';
	protected $post_name     = 'premium';
	protected $itunes_url    = 'https://sandbox.itunes.apple.com/verifyReceipt';

	/**
	 * Check if user has access to premium content 
	 * We'll first check the transient cache and then validate to iTunes if needed
	 * @param  Integer $post_id The article ID we want to access 
	 * @param  Array $data      If POSTing the $data variable will contain the POST information
	 * @return Boolean/Object   Return TRUE on successful validation and WP_Error() object on failure 
	 */
	private function check_premium_content_access($itunes_id, $data) {
		/* get receipt ID */

		/**
		 * Get receipt data and receipt ID
		 * @var string
		 */
		$receipt_data = $_POST['receipt_data'];     /* this might be $data['receipt'] / $data */
		$receipt_id   = md5($_POST['receipt_data']); /* SET THE RECEIPT ID  */
		
		if($_POST['production'] === 'yes'){
			$this->itunes_url= 'https://buy.itunes.apple.com/verifyReceipt';
		}

		/* to prevent multiple lookups to iTunes we're storing verification in database for 365 days */
		$is_cached = $this->check_premium_cache($receipt_data, $itunes_id);

		/* found problem during verification */
		if(is_wp_error($is_cached)) {
			return $is_cached; /* return WP_Error */
		}

		/* found in cache and verified */
		if($is_cached === true) {
			return true; /* mark as passed */
		}

		/* receipt ID / post ID need to be validated with iTunes */
		$itunes_validation = $this->validate_with_itunes($receipt_data, $itunes_id);

		/* problem while querying iTunes, or iTunes provided an error */
		if(is_wp_error($itunes_validation)) {
			return $itunes_validation; /* return WP_Error */
		}

		/* successful itunes validation. Cache results */
		$this->add_premium_cache($receipt_id, $itunes_id);

		return true; /* mark as passed */

	}

	/**
	 * Validate receipt ID and Post ID with iTunes 
	 * @param  String $receipt_id The receipt ID to verify
	 * @param  Integer $post_id    The article ID
	 * @return Boolean/Object  Whether the object is cached, authorised or there was an error
	 */
	private function validate_with_itunes($receipt_data, $itunes_id) {

		/*
		 * Make API call to iTunes to validate receipt 
		 * Instead of using cURL or streams to POST data to iTunes
		 * WordPress uses the wp_remote_get() and wp_remote_post() wrapper functionality 
		 *
		 * https://codex.wordpress.org/Function_Reference/wp_remote_get
		 * https://codex.wordpress.org/Function_Reference/wp_remote_post
		 */		
		
		/* add any header information to API call */
		$headers = array(

		);

		/* add body information to API call */
		$body = array(
			'receipt-data' => $receipt_data
		);
		
		$body = json_encode($body);
		
		/* make the call to iTunes */
		$response = wp_remote_post($this->itunes_url, array(
			'method'  => 'POST',
			'timeout' => 25,
			'headers' => $headers,
			'body'    => $body,
		));

		/*		
		 * Parse $response 
		 */
		if(is_wp_error( $response )) {
			return $response;
		}

		if(isset($response['body']) && $return_data['status'] == 0) {
			$return_data = json_decode($response['body'], true);

			foreach($return_data['receipt']['in_app'] as $in_app) {
				if($in_app['product_id'] == $itunes_id) {
					return true; /* valid purchased product */
				}
			} 					
		}
		
		/* getting hear signifies the receipt was invalid */
		return false;
	}

	/**
	 * Check if we have cached the receipt ID verification in database and return appropriate value
	 * @param  String $receipt_id The receipt ID to verify
	 * @param  Integer $post_id    The article ID
	 * @return Boolean/Object  Whether the object is cached, authorised or there was an error
	 */
	private function check_premium_cache($receipt_id, $itunes_id) {
		$transient_name = esc_sql('tm_premium_' . $receipt_id);
		$cache_results  = get_transient($transient_name);

		if($cache_results === false) {
			/* not found, mark as needing a lookup */
			return false;
		} else {
			/* check $cache_results equals the post ID */
			if($cache_results !== $itunes_id) {
				/* incorrect article */
				return new \WP_Error( 'json_post_invalid_id', __( 'The iTunes SKU doesn\'t match the receipt\'s purchased article.' ), array( 'status' => 400 ) );
			}
		}
		/* cached result found and authorised */
		return true;
	}

	/**
	 * Store receipt/post ID in our transient cache. Prevents another iTunes look up for 365 days.
	 * @param  String $receipt_id The receipt ID to verify
	 * @param  Integer $post_id    The article ID
	 */
	private function add_premium_cache($receipt_id, $itunes_id) {
		$transient_name = esc_sql('tm_premium_' . $receipt_id);
		$expiration = 60 * 60 * 24 * 365; /* expires in 365 days */

		/* add to our cache */
		set_transient($transient_name, $itunes_id, $expiration);
	}

	/**
	 * /premium/(?P<id>\d+) endpoint for getting a single post
	 * @param  Integer $id      The post ID
	 * @param  string $context N/A
	 * @return string           A json data burst. Either the ads, or a WP_Error
	 */
	public function get_post($itunes_id, $context = 'view', $data = array())
	{
		/* run error checking and process our standard inputs */
		$valid = $this->do_standard_processing();	

		/* get our post information */
		$args = array(
		    'meta_query' => array(
		        array(
					'key'   => 'itunes_sku',
					'value' => $itunes_id
		        )
		    ),
			'post_type'      => 'premium',	
			'posts_per_page' => 1,	    
		);		
		$posts = get_posts( $args );

		if(!isset($posts[0])) {
			return new \WP_Error( 'json_post_invalid_id', __( 'Invalid iTunes SKU.' ), array( 'status' => 404 ) );
		}

		/* assign found post to $post */
		$post = (array) $posts[0];

		/* ensure this is actually a 'premium' custom post type */
		if ( $post['post_type'] !== 'premium' ) {
			return new \WP_Error( 'json_user_cannot_read', __( 'Post not marked as "premium" content. Use /wp-json/posts/{id} endpoint instead.' ), array( 'status' => 400 ) );
		}

		/* Prepare our post structure */
		$post = $this->prepare_post( $post, $context );

		/*
	     * Check if user is is authenticated to view this article 
		 */
		if($post['free'] === false)
		{					
			$access = $this->check_premium_content_access($itunes_id, $data);

			/* use does not have access */
			if(is_wp_error($access)) {
				/* set up error handling now */
				return $access;
			}
		}

		/*
		 * Increment our post views counter 
		 */
		$this->increment_hit_counter($post['ID']);

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
	 * @return string           A json data burst. Either the posts, or a WP_Error
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
				'modified' => 'DESC'
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

		return $this->display_stripped_down_posts($args);
	}		

	/**
	 * /premium/cat/(?P<id>\d+)/popular/ endpoint for getting a list of the most viewed premium content assigned to the category
	 * @param  Integer $id      The category ID
	 * @param  string $context N/A
	 * @return string           A json data burst. Either the posts, or a WP_Error
	 */
	public function get_popular_category_posts($filter = array(), $id, $context = 'view', $page = 1)
	{		
		/* run error checking and process our standard inputs */
		$user_args = $this->do_bulk_standard_processing($filter, $page);	
		
		if(is_wp_error($user_args))
		{
			return $user_args;
		}

		$args = array(
			'post_type' => $this->post_name, 
			'meta_key' => 'hits',
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
				'modified' => 'DESC'
			),
			'meta_query' => array(
				'relation' => 'AND',
				array(
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
				array(
					'key' => 'hits',
					'value' => '0',
					'compare' => '>',
				)

			),

		);
				   
		$args = array_merge($args, $user_args);

		return $this->display_stripped_down_posts($args);
	}	

	/**
	 * /premium/ endpoint where client passes in a list of iTunes_SKU IDs that they have purchased and we'll return the results to be listed
	 * @param  array  $filter contains the 'itunes_sku' option
	 * @return string           A json data burst. Either the posts, or a WP_Error
	 */
	public function get_purchases($filter = array()) {
		/* check for any problems before processing */
		$process = $this->do_standard_processing();

		if(is_wp_error($process))
		{
			return $process;
		}

		if(empty($filter['itunes_sku'])) {
			return new \WP_Error( 'json_premium_missing_params', __( "You need to provide the filter[itunes_sku] option" ), array( 'status' => 400 ) );
		}

		/* grab our SKUs */
		$sku = explode(',', $filter['itunes_sku']);

		/*
		 * Parse the passed in SKUs
		 */
		array_walk($sku, function(&$value, $key) {
			$value = trim($value);			
		});

		$sku = array_filter($sku);

		if(sizeof($sku) == 0) {
			/* nothing found */	
			return new \WP_Error( 'json_premium_missing_params', __( "You need to provide the filter[itunes_sku] option" ), array( 'status' => 400 ) );
		}

		$args = array(
			'post_type' => $this->post_name, 	
			'posts_per_page' => -1,
			'orderby' => array(				
				'modified' => 'DESC'
			),
			'meta_query' => array(
				array(
					'key' => 'itunes_sku',
					'value' => $sku,
					'compare' => 'IN',
				)

			),

		);

		/* return results */
		return $this->display_stripped_down_posts($args);

	}

	/**
	 * Parse the passed arguments, strip down the premium posts for a sample and return the results
	 * @param  Array $args A WP_Query Argument
	 * @return JSON/Object       The JSON response or a WP_Error
	 */
	private function display_stripped_down_posts($args) {
		
		global $wp_json_server;

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
			return new \WP_Error( 'json_premium_content_disabled', __( "Sorry, Transmoto's premium content is currently unavailable." ), array( 'status' => 503 ) );
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
	 * Increment the hit counter which we'll use for 'popular'
	 * @param  integer $post_id The post ID to increment	 
	 */
	private function increment_hit_counter($post_id) {
		/*
		 * Only 'sample' the hit rate to prevent database overload
		 */		
		$sample_rate = 10;
		if(mt_rand(1, $sample_rate) != 1)
		{
		    return;
		}		

		$current_hits = get_post_meta( $post_id, 'hits', true );

		if( empty($current_hits) ) 
		{
		   $current_hits = 0;
		}

		$current_hits += $sample_rate;
		    
		update_post_meta( $post_id, 'hits', $current_hits );			
	}
}
