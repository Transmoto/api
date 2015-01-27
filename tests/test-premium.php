<?php

class TestPremiumAPI extends WP_UnitTestCase
{

    private $endpoint = 'https://transmoto.com.au/wp-json/premium/';

    public function test_diy_list()
    {
        $resp = wp_remote_get($this->endpoint . 'cat/142');
        $body = json_decode($resp['body'], true);

        $this->assertEquals(200, $resp['response']['code']);

        $this->do_category_article_list($body[0]);                   	
    }

    public function test_how_to_list()
    {
        $resp = wp_remote_get($this->endpoint . 'cat/143');
        $body = json_decode($resp['body'], true);

        $this->assertEquals(200, $resp['response']['code']);

        $this->do_category_article_list($body[0]);                   	
        $this->do_category_article_list($body[1]);
    }

    public function do_category_article_list($article)
    {
    	$this->assertArrayHasKey('ID', $article);
    	$this->assertArrayHasKey('title', $article);
    	$this->assertArrayHasKey('date', $article);
    	$this->assertArrayHasKey('excerpt', $article);
    	$this->assertArrayHasKey('date_gmt', $article);
    	$this->assertArrayHasKey('free', $article);
    	$this->assertArrayHasKey('hits', $article);
    	$this->assertArrayHasKey('featured_image', $article);
    }

    public function test_free_article()
    {
        $resp = wp_remote_get($this->endpoint . '28740');
        $body = json_decode($resp['body'], true);

        $this->assertEquals(200, $resp['response']['code']);

        /* do the basics */
        $this->do_category_article_list($body);                   	

        /* ensure the content is there */
        $this->assertArrayHasKey('content', $body);

        /* ensure this is a free article */
        $this->assertTrue($body['free']);
        
    }    
}
