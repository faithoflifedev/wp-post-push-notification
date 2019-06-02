<?php

namespace Nstaeger\WpPostPushNotification;

use Nstaeger\CmsPluginFramework\Configuration;
use Nstaeger\CmsPluginFramework\Creator\Creator;
use Nstaeger\CmsPluginFramework\Plugin;
use Nstaeger\WpPostPushNotification\Model\CategoryModel;
use Nstaeger\WpPostPushNotification\Model\JobModel;
use Nstaeger\WpPostPushNotification\Model\Option;
use Nstaeger\WpPostPushNotification\Model\SubscriberModel;

class WpPostPushNotificationPlugin extends Plugin
{
    function __construct(Configuration $configuration, Creator $creator)
    {
        parent::__construct($configuration, $creator);

        $this->menu()->registerAdminMenuItem('WP Post Email Notification')
             ->withAction('AdminPageController@optionsPage')
             ->withAsset('js/bundle/admin-options.js');


        $this->ajax()->get('category')->resolveWith('AdminCategoryController@get')->onlyWithPermission('can_manage');

        $this->ajax()->delete('job')->resolveWith('AdminJobController@delete')->onlyWithPermission('can_manage');
        $this->ajax()->get('job')->resolveWith('AdminJobController@get')->onlyWithPermission('can_manage');
        $this->ajax()->get('option')->resolveWith('AdminOptionController@get')->onlyWithPermission('can_manage');
        $this->ajax()->put('option')->resolveWith('AdminOptionController@update')->onlyWithPermission('can_manage');

        $this->events()->on('loaded', array($this, 'sendNotifications'));
        $this->events()->on('post-published', array($this, 'postPublished'));
        $this->events()->on('post-unpublished', array($this, 'postUnpublished'));
    }

    public function activate()
    {
        $this->job()->createTable();
        $this->subscriber()->createTable();
        $this->option()->createDefaults();
    }

    /**
     * @return CategoryModel
     */
    public function category()
    {
        return $this->make('Nstaeger\WpPostPushNotification\Model\CategoryModel');
    }

    /**
     * @return JobModel
     */
    public function job()
    {
        return $this->make('Nstaeger\WpPostPushNotification\Model\JobModel');
    }

    /**
     * @return Option
     */
    public function option()
    {
        return $this->make('Nstaeger\WpPostPushNotification\Model\Option');
    }

    public function postPublished($id)
    {
        $this->job()->createNewJob($id);
    }

    public function postUnpublished($id)
    {
        $this->job()->removeJobsFor($id);
    }

    public function sendNotifications()
    {
        $jobs = $this->job()->getNextJob();

        if (empty($jobs)) {
            return;
        }

        foreach ($jobs as $job):
			$this->job()->completeJob($job['id']);
			
            $post = get_post($job['post_id'] );
			
			$arr_postTags = wp_get_post_tags( $post->ID, array( 'fields' => 'names') );
			
			if ( in_array( 'push', $arr_postTags ) ):
                $blogName = get_bloginfo('name');
                $postAuthorName = get_the_author_meta( 'display_name', $post->post_author );
                $postLink = get_permalink( $post->ID );
                $postTitle = $post->post_title;
				$postCats = implode( ',', wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) ) );
				$postTags = implode( ',', $arr_postTags );
				$postContent = get_post_field( 'post_content', $post->ID );

                $rep_search = ['@@blog.name', '@@post.author.name', '@@post.link', '@@post.title', '@@post.categories', '@@post.tags', '@@post.content'];
                $rep_replace = [$blogName, $postAuthorName, $postLink, $postTitle, $postCats, $postTags, $postContent];

                $subject = $this->option()->getEmailSubject();
                $subject = str_replace($rep_search, $rep_replace, $subject);

                $message = $this->option()->getEmailBody();
                $message = str_replace($rep_search, $rep_replace, $message);

                $auth_key = $this->option()->getNumberOfEmailsSendPerRequest();

                $headers[] = '';
				
				$data = array(
					"notification" => array(
						"title" => $postTitle, 
						"body" => $subject, 
						"badge" => "1", 
						"sound" => "default"
					),
					"data" => array(
						"id" => $post->ID,
						"title" => $postTitle,
						"body" => $message
					),
					"priority" => "High", 
					"to" => "/topics/janaza"
				);
				
				$data_string = json_encode( $data );
				
				$ch = curl_init( 'https://fcm.googleapis.com/fcm/send' );      
				
				curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );                                                                     
				curl_setopt( $ch, CURLOPT_POSTFIELDS, $data_string );                                                                  
				curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );                                                                      
				curl_setopt( 
					$ch, 
					CURLOPT_HTTPHEADER, 
					array(                                                                          
						'Authorization: key=' . $auth_key,
    					'Content-Type: application/json',
    					'Content-Length: ' . strlen( $data_string ) 
					)                                                                  
				);                                                                                                 
				curl_exec( $ch );
            endif;
        endforeach;
    }

    /**
     * @return SubscriberModel
     */
    public function subscriber()
    {
        return $this->make('Nstaeger\WpPostPushNotification\Model\SubscriberModel');
    }
}