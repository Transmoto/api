<?php 

namespace TransmotoAPI;

class TransmotoRESTAPI_Trader
{


	/**
	 * /trader/ endpoint for getting all ads 	 
	 * @param  array   $filter  Additional option parameters can be passed through. Currently supporting ?filter['ads_per_page']
	 * @param  string  $context N/A
	 * @param  string  $type    N/A
	 * @param  integer $page    The page ID. Used in pagination
	 * @return string           A json data burst. Either all the ads, or a WP_Error
	 */
	public function get_all_ads($filter = array(), $context = 'view', $type = 'trader', $page = 1)
	{
		/* run error checking and process our standard inputs */
		$args = $this->do_bulk_standard_processing($filter, $page);

		if(is_wp_error($args))
		{
			return $args;
		}

		/* get our ads */
		$ads = $this->clean_ads($this->get_enabled_ads($args));

		print json_encode($ads);
		exit;
	}

	/**
	 * /trader/private/ endpoint for only getting ads that are from private sellers
	 * @param  array   $filter  The available GET url parameters
	 * @param  string  $context N/A
	 * @param  string  $type    N/A
	 * @param  integer $page    The page ID. Used in pagination
	 * @return string           A json data burst. Either the private ads, or a WP_Error
	 */
	public function get_private_ads($filter = array(), $context = 'view', $type = 'trader', $page = 1)
	{
		/* run error checking and process our standard inputs */
		$args = $this->do_bulk_standard_processing($filter, $page);		

		if(is_wp_error($args))
		{
			return $args;
		}

		/* get our ads */
		$ads = $this->clean_ads($this->get_enabled_ads($args, array("listing_type = 'Private'")));

		print json_encode($ads);
		exit;
	}

	/**
	 * /trader/dealer/ endpoint for only getting ads that are from dealers
	 * @param  array   $filter  The available GET url parameters
	 * @param  string  $context N/A
	 * @param  string  $type    N/A
	 * @param  integer $page    The page ID. Used in pagination
	 * @return string           A json data burst. Either the dealer ads, or a WP_Error
	 */
	public function get_dealer_ads($filter = array(), $context = 'view', $type = 'trader', $page = 1)
	{
		/* run error checking and process our standard inputs */
		$args = $this->do_bulk_standard_processing($filter, $page);		

		if(is_wp_error($args))
		{
			return $args;
		}

		/* get our ads */
		$ads = $this->clean_ads($this->get_enabled_ads($args, array("listing_type = 'Dealer'")));

		print json_encode($ads);
		exit;
	}	


	/**
	 * /trader/(?P<id>\d+) endpoint for getting a single ad
	 * @param  Integer $id      The ad ID
	 * @param  string $context N/A
	 * @return string           A json data burst. Either the ads, or a WP_Error
	 */
	public function get_ad($id, $context = 'view')
	{
		/* run error checking and process our standard inputs */
		$valid = $this->do_standard_processing();	
		
		if(is_wp_error($valid))
		{
			return $valid;
		}

		/* get our ads */
		$ad = $this->clean_ads($this->get_enabled_ads(array(), array('ad_id = ' . (int) $id)));

		print json_encode($ad[0]);
		exit;		
	}

	/**
	 * /trader/search endpoint for searching through the Trader
	 * @param  array   $filter  The available GET url parameters
	 * @param  string  $context N/A
	 * @param  string  $type    N/A
	 * @param  integer $page    The page ID. Used in pagination
	 * @return string           A json data burst. Either the search results, or a WP_Error
	 */
	public function search_ads($filter = array(), $context = 'view', $type = 'trader', $page = 1)
	{
		/* run error checking and process our standard inputs */
		$args = $this->do_bulk_standard_processing($filter, $page);		

		if(is_wp_error($args))
		{
			return $args;
		}

		$error = $this->validate_search_parameters($filter);		

		if(is_wp_error($error))
		{
			return $error;
		}

		$do_search = $this->do_search($filter, $args);

		print json_encode($do_search);
		exit;			
	}

	/**
	 * /trader/search/regions endpoint
	 * @return string A json data burst. Either the available regions, or a WP_Error
	 */
	public function get_regions()
	{
		/* run error checking and process our standard inputs */
		$valid = $this->do_standard_processing();	

		$api = awpcp_regions_api();

		$data = $this->get_regions_array();

		echo json_encode($data);
		exit;
	}

	/**
	 * Query the database for active Trader country and states
	 * @return array The processed array
	 */
	private function get_regions_array()
	{
		global $wpdb;

		$countries = $wpdb->get_results('SELECT region_id, region_name FROM ' . AWPCP_TABLE_REGIONS .' WHERE region_state = 1 AND region_type = 2 ORDER BY region_name');

		$data = array();
		foreach ($countries as $key => $c)
		{
			$data[$key]['name'] = $c->region_name;

			$states = $wpdb->get_results('SELECT region_name FROM ' . AWPCP_TABLE_REGIONS .' WHERE region_state = 1 AND region_parent = '. $c->region_id .' ORDER BY region_name');

			foreach($states as $s)
			{
				$data[$key]['states'][] = $s->region_name;
			}
		}

		return $data;		
	}


	/**
	 * Build our SQL query based on the submitted arguments and return the cleaned ad results
	 * @param  Array $filter The search parameters
	 * @param  Array $args   Additional arguments to pass to the query statement
	 * @return Array 		 The search results
	 */	
	private function do_search($filter, $args)
	{
		global $wpdb;

        $conditions = array('1=1');

        /* todo, add own region controler */
        remove_filter('awpcp-ad-where-statement', array(awpcp_regions(), 'get_ads_where_conditions'));

        if (!empty($filter['category'])) {

        	/*
        	 * Get the category ID from the category name 
        	 */
        	$cat_id = $this->get_trader_category_id($filter['category']);        	
            $sql = '(ad_category_id = %1$d OR ad_category_parent_id = %1$d)';
            $conditions[] = $wpdb->prepare($sql, $cat_id);
        }

        if ( isset($filter['min_price']) && strlen( $filter['min_price'] ) > 0 ) {
            $price = $filter['min_price'] * 100;
            $conditions[] = $wpdb->prepare('ad_item_price >= %d', $price);
        }

        if ( isset($filter[ 'max_price' ]) && strlen( $filter[ 'max_price' ] ) > 0 ) {
            $price = $filter['max_price'] * 100;
            $conditions[] = $wpdb->prepare('ad_item_price <= %d', $price);
        }

       // $conditions = array_merge( $conditions, awpcp_regions_search_conditions( $filter[ 'regions' ] ) );
        $where = join(' AND ', $conditions);

        /* include extra fields in search */
		$extra_search_conditions = awpcp_get_extra_fields_conditions( array(
			'hide_private' => true,
			'context' => 'search'
		) );

        $where .= $this->extra_fields_search_where(awpcp_get_extra_fields('WHERE ' . join( ' AND ', $extra_search_conditions)), $filter);

        /* add region where conditions */
        if(isset($filter['state']))
        {
        	$where .= $wpdb->prepare(" AND `ad_id` IN ( SELECT DISTINCT ad_id FROM wp_awpcp_ad_regions WHERE state = %s )", $filter['state']);
        }
        elseif(isset($filter['country']))
        {
        	$where .= $wpdb->prepare(" AND `ad_id` IN ( SELECT DISTINCT ad_id FROM wp_awpcp_ad_regions WHERE country = %s )", $filter['country']);
        }

        return $this->clean_ads($this->get_enabled_ads($args, array($where)));
	}

	/**
	 * Valid the submitted search options before passing to the query generator
	 * @param  Array $data The search options
	 * @return Object      Either WP_Error or null
	 */
	private function validate_search_parameters($data)
	{		
        if (!empty($data['s']) && strlen($data['s']) < 3) {
            return new \WP_Error( 'json_invalid_trader_search', __( "Invalid 's'. Must be blank or be three characters or greater" ), array( 'status' => 400 ) );
        }

 		$category = $this->get_trader_categories();		 		
 		if(!empty($data['category']) && !in_array($data['category'], $category))
 		{
 			return new \WP_Error( 'json_invalid_trader_search', __( 'Invalid \'category\' passed. Valid types include: ' . implode(', ', $category) ), array( 'status' => 400 ) );
 		}        

        if (!empty($data['min_price']) && !is_numeric($data['min_price'])) {
        	return new \WP_Error( 'json_invalid_trader_search', __( 'Invalid min_price. Make sure your price contains numbers only (without currency symbols)' ), array( 'status' => 400 ) );            
        }

        if (!empty($data['max_price']) && !is_numeric($data['max_price'])) {
            return new \WP_Error( 'json_invalid_trader_search', __( 'Invalid max_price. Make sure your price contains numbers only (without currency symbols)' ), array( 'status' => 400 ) );
        }		

        if (!empty($data['postcode']) && !is_numeric($data['postcode'])) {
            return new \WP_Error( 'json_invalid_trader_search', __( 'Invalid \'postcode\'. Make sure it only contains numbers' ), array( 'status' => 400 ) );
        }	        
     
 		$type = array('Dealer', 'Private');
 		if(!empty($data['listing_type']) && !in_array($data['listing_type'], $type))
 		{
 			return new \WP_Error( 'json_invalid_trader_search', __( 'Invalid listing_type passed. Valid types include: ' . implode(', ', $type) ), array( 'status' => 400 ) );
 		}
 		
 		$bike_type = awpcp_get_extra_field(7); 		
 		if(!empty($data['bike_type']) && !in_array($data['bike_type'], $bike_type->field_options))
 		{
 			return new \WP_Error( 'json_invalid_trader_search', __( 'Invalid bike_type passed. Valid types include: ' . implode(', ', $bike_type->field_options) ), array( 'status' => 400 ) );
 		}

 		$bike_make = awpcp_get_extra_field(9); 		
 		if(!empty($data['make']) && !in_array($data['make'], $bike_make->field_options))
 		{
 			return new \WP_Error( 'json_invalid_trader_search', __( 'Invalid \'make\' passed. Valid types include: ' . implode(', ', $bike_make->field_options) ), array( 'status' => 400 ) );
 		} 		

 		if(!empty($data['manufacture_year']) && (!is_numeric($data['manufacture_year']) || strlen($data['manufacture_year']) !== 4))
 		{
 			return new \WP_Error( 'json_invalid_trader_search', __( 'Invalid \'manufacture_year\'. Ensure a valid 4-digit year.' ), array( 'status' => 400 ) );            	
 		}

 		$registered = awpcp_get_extra_field(8); 	

 		if(!empty($data['currently_registered']) && !in_array($data['currently_registered'], $registered->field_options))
 		{
 			return new \WP_Error( 'json_invalid_trader_search', __( 'Invalid \'currently_registered\' value passed. Valid types include: ' . implode(', ', $registered->field_options) ), array( 'status' => 400 ) );
 		} 	 

 		$condition = awpcp_get_extra_field(14); 
		
 		if(!empty($data['condition']) && !in_array($data['condition'], $condition->field_options))
 		{
 			return new \WP_Error( 'json_invalid_trader_search', __( 'Invalid \'condition\'. Valid types include: ' . implode(', ', $condition->field_options) ), array( 'status' => 400 ) );
 		} 	

 		if(empty($data['country'])&& isset($data['state']))  			
 		{
 			return new \WP_Error( 'json_invalid_trader_search', __( "The 'state' parameter cannot be passed without the 'country' parameter." ), array( 'status' => 400 ) );
 		}

 		if(isset($data['country']) || isset($data['state']))
 		{
 			$regions = $this->get_regions_array();

 			$country = $state = false; 			
 			foreach($regions as $r)
 			{ 				
 				if(isset($data['country']))
 				{
 					if($data['country'] == $r['name'])
 					{
 						$country = true;
 					} 					
 				}

 				if(isset($data['state']) && $data['country'] == $r['name'])
 				{
 					foreach($r['states'] as $s)
 					{
 						if($data['state'] == $s)
 						{
 							$state = true;
 						}
 					}
 				}
 			}

 			if($country === false )
 			{
 				return new \WP_Error( 'json_invalid_trader_search', __( 'Invalid \'country\'.' ), array( 'status' => 400 ) );
 			}

 			if(isset($data['state']) && $state === false )
 			{
 				return new \WP_Error( 'json_invalid_trader_search', __( 'Invalid \'state\'.' ), array( 'status' => 400 ) );
 			} 			
 		}
			
	}

	/**
	 * Include the extra-field option SQL generator (a premium add-on of the AWPCP plugin)
	 * @param  array  $fields The extra field names to include in the search option
	 * @param  array $data   The current submitted filters
	 * @return string        An additional 'where' clause for the SQL query
	 */
	private function extra_fields_search_where( $fields=array(), $data) {
		global $wpdb;
		
		$keywordphrase = (isset($data['s'])) ? $data['s'] : '';

		if ( ! is_array( $fields ) && ! empty( $fields ) ) {
			$fields = array( $fields );
		}

		if ( empty( $fields ) && empty( $keywordphrase ) ) {
			return '';
		}

		$no_setted_fields_where = array();

		if ( ! empty( $keywordphrase ) ) {
			$no_setted_fields_where[] = sprintf( "ad_title LIKE '%%%s%%' OR ad_details LIKE '%%%s%%'", $keywordphrase, $keywordphrase );
		}

		$where = '';
		foreach ($fields as $field) {
			$value = awpcp_array_data( $field->field_name, '', $data );
			$field_name = "`{$field->field_name}`";

			$value = preg_replace( '/\*+$/', '', preg_replace( '/^\*+/', '', $value ) );

			if ( is_array( $value ) && ( isset( $value['min'] ) || isset( $value['max'] ) ) ) {
				$conditions = array();

				if ( strlen( $value['min'] ) > 0 ) {
					$conditions[] = $wpdb->prepare( "$field_name >= %f", $value['min'] );
				}
				if ( strlen( $value['max'] ) > 0 ) {
					$conditions[] = $wpdb->prepare( "$field_name <= %f", $value['max'] );
				}
				if ( !empty( $conditions ) ) {
					$where .= ' AND (' . implode( ' AND ', $conditions ) . ') ';
				}

			} else if (is_array($value))  {
				$sets = array();
				foreach ($value as $key => $val) {
					// adds slashes
					$val = clean_field(trim($val));
					$sets[] = sprintf(" $field_name LIKE '%%%s%%' ", $val);
				}
				$where .= ' AND (' . implode(' OR ', $sets) . ') ';

			} else if ('' != $value) {

				$sql = '';
				if($field->field_input_type == 'Input Box')
				{
					/*
					 * Explode value into multiple words and do a LIKE-based search 
					 */
					$words = explode(' ', $value);
					
					if(sizeof($words) > 0)
					{
						$sql .= " AND (";
					}

					foreach($words as $word)
					{
						$sql .= sprintf("$field_name LIKE '%%%s%%' OR ", esc_sql($word));
					}

					$sql = substr($sql, 0, -4) . ')';
				}
				else
				{
					$sql = sprintf(" AND $field_name LIKE '%%%s%%' ", esc_sql( $value ));
				}
				$where .= $sql;
			} else if ('' == $value && '' != $keywordphrase) {
				$no_setted_fields_where[] = sprintf(" $field_name LIKE '%%%s%%' ", esc_sql( $keywordphrase ));
			}
		}

		if ( count( $no_setted_fields_where ) > 0 && $where != '' ) {
			$where = sprintf(" AND ( ( %s ) %s )", implode(' OR ', $no_setted_fields_where), $where);
		} elseif ( count( $no_setted_fields_where ) > 0 && $where == '' ) {
			$where = sprintf(" AND ( %s )", implode(' OR ', $no_setted_fields_where));
		}

		return $where;
	}	

	/**
	 * Get current Trader categories
	 * @return array An ID => Name array mapping of the categories
	 */
	private function get_trader_categories()
	{
		$categories = \AWPCP_Category::query();

		$cats = array();

		foreach($categories as $c)
		{
			$cats[$c->id] = $c->name;
		}

		return $cats;
	}

	/**
	 * Get the category ID from the category name
	 * @param  string $name The category name to look up
	 * @return integer/boolean       The category ID, or false.
	 */
	private function get_trader_category_id($name)
	{
		$categories = \AWPCP_Category::query();

		$cats = array();

		foreach($categories as $c)
		{
			if($name == $c->name)
			{
				return $c->id;
			}			
		}

		return false;
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

		$ads_per_page = (isset($filter['ads_per_page'])) ? (int) $filter['ads_per_page'] : 16;
		
		return array(
			'offset' => $ads_per_page * ($page - 1),
			'limit' => $ads_per_page,
		);		
	}

	/**
	 * Do our standard error checking and processing of standard inputs
	 * @param  array $filter Any URL parametres passed through using ?filter[] 
	 * @param  integer $page   The page ID. Used for pagination
	 * @return array         The formatted results
	 */
	private function do_standard_processing()
	{		
		if(!class_exists('AWPCP_Ad'))		
		{
			return new \WP_Error( 'json_trader_disabled', __( 'Sorry, the Transmoto Trader is currently offline.' ), array( 'status' => 503 ) );
		}	
	}

	/**
	 * Get all enabled ads back in RAW format
	 * @param  array  $args       Any arguments that should be applies
	 * @param  array  $conditions Any conditions
	 * @return array             The ads data array
	 */
	private function get_enabled_ads($args=array(), $conditions=array()) {
        $conditions = \AWPCP_Ad::get_where_conditions($conditions);

        return \AWPCP_Ad::query(array_merge($args, array('where' => join(' AND ', $conditions))), 'raw');
	}

	/**
	 * Only send the end user public data
	 * @param  Array $ads The array of ads
	 * @return Array      The cleaned ads array
	 */
	private function clean_ads($ads)
	{		
		$valid_ids = array(
		 	'ad_id',   		    		    
		    'ad_title',
		    'ad_item_price',
		    'ad_details',
		    'ad_contact_name',
		    'ad_contact_phone',
		    'ad_contact_email',
		    'ad_postdate',
		    'ad_last_updated',
		    'listing_type',
		    'is_featured_ad',
            'bike_type', 
            'currently_registered', 
            'make', 
            'model', 
            'manufacture_year', 
            'odometer_run_time',
            'postcode', 
            'condition',
            'poa', 	
            'country',
            'state'	    
		);

		$cleaned_ads = array();
		
		foreach($ads as $key => $ad)
		{
			$ad->country = bld_awpcp_location_display($ad, 'tm_country');
			$ad->state   = bld_awpcp_location_display($ad, 'tm_state_display');

			foreach($valid_ids as $id)
			{
				if(isset($ad->$id))
				{
					if($id == 'ad_item_price')
					{
						$cleaned_ads[$key]['ad_clean_item_price'] = 0;
						if($ad->$id != 0)
						{
							$cleaned_ads[$key]['ad_clean_item_price'] = ($ad->$id / 100);
							$ad->$id                                  = '$' . number_format($ad->$id / 100, 2);						
						}
					}
					$cleaned_ads[$key][$id] = $ad->$id;
				}
			}

			$cleaned_ads[$key]['category_name'] = get_adparentcatname($ad->ad_category_id);
		}

		return $cleaned_ads;
	}

}