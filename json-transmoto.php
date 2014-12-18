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
	public static function init()
	{
		$plugin = new self();

		require_once(plugin_dir_path(__FILE__) . 'endpoints/class-trader.php');
		require_once(plugin_dir_path(__FILE__) . 'endpoints/class-premium.php');

		add_action( 'json_endpoints', array($plugin, 'register_endpoints') );
	}

	public function register_endpoints($routes)
	{

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

		$routes['/trader/search/regions'] = array(
			array( array( $trader, 'get_regions'), \WP_JSON_Server::READABLE ),			
		);			

		/*
		 * Add Premium Content Routes 
		 */	
		$premium = new \TransmotoAPI\TransmotoRESTAPI_Premium;

		$routes['/premium/cat'] = array(
			array( array( $premium, 'get_categories'), \WP_JSON_Server::READABLE ),		
		);

		$routes['/premium/cat/(?P<id>\d+)'] = array(
			array( array( $premium, 'get_category_posts'), \WP_JSON_Server::READABLE ),		
		);		

		$routes['/premium/(?P<id>\d+)'] = array(
			array( array( $premium, 'get_post'), \WP_JSON_Server::READABLE ),		
		);					


		return $routes;
	}


}

\TransmotoAPI\TransmotoRESTAPI::init();