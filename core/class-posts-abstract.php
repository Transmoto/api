<?php 

namespace TransmotoAPI;

abstract class TransmotoPosts
{

	/**
	 * Ensure we only pass certain information back to the user 
	 * so as to not give away our premium content
	 * @param  Array $post The full post array 
	 * @return Array       The cleaned post array 
	 */
	protected function clean($post)
	{
		$valid_keys = array(
			'ID',
			'title',
			'date',
			'date_gmt',
			'featured_image',
			'excerpt',
			'free',
			'hits',
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
	protected function clean_categories($categories)
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
	protected function prepare_post($post)
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

		/*
		 * Include post hit counter 
		 */		
		$_post['hits'] = get_post_meta( get_the_ID(), 'hits', true);

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