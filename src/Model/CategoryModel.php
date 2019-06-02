<?php

namespace Nstaeger\WpPostPushNotification\Model;

class CategoryModel
{
    public function getAll()
    {
		$cat_args = array(
			'orderby'     => 'name',
			'order'       => 'ASC',
			'hide_empty'  => 1
		  );
  
		  $cats = get_categories( $cat_args );

        return [
			'cat1',
			'cat2',
			print_r( $cats, true )
		];
    }	
}