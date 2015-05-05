<?php
/**
 * Plugin Name: Transmoto JSON REST API
 * Description: Custom Routes for JSON-based REST API for WordPress
 * Author: Blue Liquid Designs
 * Author URI: http://blueliquiddesigns.com.au/
 * Version: 0.1
 */

namespace TransmotoAPI;

class TransmotoRESTAPI
{
	static $exclude_categories = array(103,104,105,106);

	public static function init()
	{
		$plugin = new self();

		require_once(plugin_dir_path(__FILE__) . 'core/class-posts-abstract.php');

		require_once(plugin_dir_path(__FILE__) . 'endpoints/class-posts.php');
		require_once(plugin_dir_path(__FILE__) . 'endpoints/class-trader.php');
		require_once(plugin_dir_path(__FILE__) . 'endpoints/class-premium.php');

		add_action( 'json_endpoints', array($plugin, 'register_endpoints') );		
	}

	public function register_endpoints($routes)
	{

		/*
		 * Extend standard posts route and run queries the basics don't get		 
		 */
		$tm = new \TransmotoAPI\TransmotoRESTAPI_Posts;

		$routes['/tm/popular'] = array(
			array( array( $tm, 'get_popular_posts'), \WP_JSON_Server::READABLE ),			
		);

		$routes['/tm/rotator'] = array(
			array( array( $tm, 'get_rotator_posts'), \WP_JSON_Server::READABLE ),			
		);

		/*
		 * Extend the /posts/:id/ endpoint with one for related content 
		 */
		$routes['/posts/(?P<id>\d+)/related'] = array(
				array( array( $tm, 'get_related' ), \WP_JSON_Server::READABLE ),
		);

		add_action( 'json_prepare_post', array($tm, 'add_meta_data'), 10, 3);		


		/*
		 * Add Trader Routes 
		 */
		$trader = new \TransmotoAPI\TransmotoRESTAPI_Trader;		

		$routes['/trader'] = array(
			array( array( $trader, 'get_all_ads'), \WP_JSON_Server::READABLE ),			
		);

		$routes['/trader/dealer'] = array(
			array( array( $trader, 'get_dealer_ads'), \WP_JSON_Server::READABLE ),			
		);		

		$routes['/trader/private'] = array(
			array( array( $trader, 'get_private_ads'), \WP_JSON_Server::READABLE ),			
		);				

		$routes['/trader/(?P<id>\d+)'] = array(
			array( array( $trader, 'get_ad'), \WP_JSON_Server::READABLE ),			
		);		

		$routes['/trader/search'] = array(
			array( array( $trader, 'search_ads'), \WP_JSON_Server::READABLE ),			
		);		

		$routes['/trader/search/options'] = array(
			array( array( $trader, 'get_search_options'), \WP_JSON_Server::READABLE ),			
		);			

		/*
		 * Add Premium Content Routes 
		 */	
		$premium = new \TransmotoAPI\TransmotoRESTAPI_Premium;

		$routes['/premium'] = array(
			array( array( $premium, 'get_purchases'), \WP_JSON_Server::READABLE ),		
		);

		$routes['/premium/cat'] = array(
			array( array( $premium, 'get_categories'), \WP_JSON_Server::READABLE ),		
		);

		$routes['/premium/cat/(?P<id>\d+)'] = array(
			array( array( $premium, 'get_category_posts'), \WP_JSON_Server::READABLE ),		
		);		

		$routes['/premium/cat/(?P<id>\d+)/popular'] = array(
			array( array( $premium, 'get_popular_category_posts'), \WP_JSON_Server::READABLE ),		
		);		

		$routes['/premium/(?P<itunes_id>[\w-]+)'] = array(
			array( array( $premium, 'get_post'), \WP_JSON_Server::READABLE | \WP_JSON_SERVER::CREATABLE | \WP_JSON_Server::ACCEPT_JSON ),		
		);		


		return $routes;
	}


}

\TransmotoAPI\TransmotoRESTAPI::init();