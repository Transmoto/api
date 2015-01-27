<?php

class TestTraderAPI extends WP_UnitTestCase
{

	private $endpoint = 'https://transmoto.com.au/wp-json/trader/';
	
	static $ad_id 	  = false;
	static $params    = false;


    public function setUp()
    {
    	if(self::$params === false)
    	{
    		$resp = wp_remote_get($this->endpoint . 'search/options');

    		$this->assertEquals(200, $resp['response']['code']);
    		self::$params = json_decode($resp['body'], true);
    	}
    }

    public function testListAllAds()
    {
        $resp = wp_remote_get($this->endpoint);
        $body = json_decode($resp['body'], true);

        $this->assertEquals(200, $resp['response']['code']);
        $this->assertEquals(16, sizeof($body));

        $this->do_test_trader_response_body($body[0]);
        $this->do_test_trader_response_body($body[1]);
        $this->do_test_trader_response_body($body[2]);
    }

    public function do_test_trader_response_body($tm_ad)
    {
        /*
    	 * Test all the correcy 'keys' exist
    	 */
        $this->assertArrayHasKey('ad_id', $tm_ad);
        $this->assertArrayHasKey('ad_title', $tm_ad);
        $this->assertArrayHasKey('ad_details', $tm_ad);
        $this->assertArrayHasKey('ad_item_price', $tm_ad);
        $this->assertArrayHasKey('ad_clean_item_price', $tm_ad);
        $this->assertArrayHasKey('poa', $tm_ad);
        $this->assertArrayHasKey('ad_contact_name', $tm_ad);
        $this->assertArrayHasKey('ad_contact_phone', $tm_ad);
        $this->assertArrayHasKey('ad_contact_email', $tm_ad);
        $this->assertArrayHasKey('listing_type', $tm_ad);
        $this->assertArrayHasKey('is_featured_ad', $tm_ad);
        $this->assertArrayHasKey('ad_postdate', $tm_ad);
        $this->assertArrayHasKey('ad_last_updated', $tm_ad);
        $this->assertArrayHasKey('category_name', $tm_ad);
        $this->assertArrayHasKey('country', $tm_ad);
        $this->assertArrayHasKey('state', $tm_ad);
        $this->assertArrayHasKey('postcode', $tm_ad);

        /*
         * Do more indepth tests against the returned data
         */
        
        /* Test the date field */
        $this->assertEquals(10, strlen($tm_ad['ad_postdate']));
        $this->assertEquals(10, strlen($tm_ad['ad_last_updated']));

        $post_date    = explode('-', $tm_ad['ad_postdate']);
        $updated_date = explode('-', $tm_ad['ad_last_updated']);

        $this->assertEquals(3, sizeof($post_date));
        $this->assertEquals(3, sizeof($updated_date));

        /* test email address */
        $this->assertEquals($tm_ad['ad_contact_email'], is_email($tm_ad['ad_contact_email']));

        /* test dynamic fields */
        $params = self::$params;

        $this->assertTrue(in_array($tm_ad['category_name'], $params['categories']));

        if($tm_ad['category_name'] == 'Bikes')
        {
        	$this->assertTrue(in_array($tm_ad['listing_type'], $params['listing_type']));
        	$this->assertTrue(in_array($tm_ad['bike_type'], $params['bike_type']));
        	$this->assertTrue(in_array($tm_ad['make'], $params['bike_make']));
        	$this->assertEquals(4, strlen($tm_ad['manufacture_year']));
        	$this->assertTrue(in_array($tm_ad['currently_registered'], $params['bike_registered']));			
        }

        if($tm_ad['category_name'] == 'Accessories')
        {
        	$this->assertTrue(in_array($tm_ad['condition'], $params['accessories_condition']));
        }

		$region_found = $state_found = false;	
    	foreach($params['regions'] as $region)
    	{
    		foreach($region['states'] as $state)
    		{
    			if($tm_ad['state'] == $state)
    			{
    				$state_found = true;
    				/* found state so exist loop */
    				break;
    			}
    		}

    		if($tm_ad['country'] == $region['name'])
    		{
    			$region_found = true;
    			/* found country so exist loop */
    			break;
    		}        		
    	}

    	$this->assertTrue($region_found);
    	$this->assertTrue($state_found);        
    }

    public function test_trader_pages()
    {
    	$resp = wp_remote_get($this->endpoint);
        $page1 = json_decode($resp['body'], true);

        $resp = wp_remote_get($this->endpoint . '?page=2');
        $page2 = json_decode($resp['body'], true);

        $this->assertNotEquals($page1[0]['ad_id'], $page2[0]['ad_id']);   	
    }

    public function test_trader_per_page()
    {
    	$resp = wp_remote_get($this->endpoint . '?filter[ads_per_page]=5');
        $body = json_decode($resp['body'], true);

        $this->assertEquals(5, sizeof($body));   	
    }    

    public function test_dealer_only()
    {
        $resp = wp_remote_get($this->endpoint . 'dealer/');
        $body = json_decode($resp['body'], true);

        $this->assertEquals(200, $resp['response']['code']);
        $this->assertEquals(16, sizeof($body));

        /* set an ad ID we can use in later tests */
        self::$ad_id = $body[0]['ad_id'];

        $this->do_test_trader_response_body($body[0]);
        $this->do_test_trader_response_body($body[1]);
        $this->do_test_trader_response_body($body[2]);    	

        /* ensure all ads are 'dealer' only */
        foreach($body as $ad)
        {
        	$this->assertEquals('Dealer', $ad['listing_type']);
        }
    }

    public function test_private_only()
    {
        $resp = wp_remote_get($this->endpoint . 'private/');
        $body = json_decode($resp['body'], true);

        $this->assertEquals(200, $resp['response']['code']);

        $this->do_test_trader_response_body($body[0]);
        $this->do_test_trader_response_body($body[1]);
        $this->do_test_trader_response_body($body[2]);    	

        /* ensure all ads are 'dealer' only */
        foreach($body as $ad)
        {
        	$this->assertEquals('Private', $ad['listing_type']);
        }
    }    

    public function test_individual_ad()
    {
        $resp = wp_remote_get($this->endpoint . self::$ad_id);
        $body = json_decode($resp['body'], true);        

        $this->assertEquals(200, $resp['response']['code']);

        $this->do_test_trader_response_body($body);
    }

    public function test_search_ads()
    {
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }
}
