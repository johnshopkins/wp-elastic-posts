<?php
/*
Plugin Name: Elastic Posts
Description: Imports posts into elasticsearch
Author: Jen Wachter
Version: 0.1
*/

class ElasticPosts
{
	protected $director;

	public function __construct()
	{
		$this->setDirector();

		// Create admin pages
		add_action("wp_loaded", function () {
			new \ElasticPosts\Admin();
		});

		// posts
		add_action("save_post", array($this->director, "put"));

		// if trash is turned off, add a hook to take care of deleted
    // posts. Otherwise, deleted posts are treated with save_post
    // as a status change
    if (defined("EMPTY_TRASH_DAYS") && EMPTY_TRASH_DAYS == 0) {
        add_action("deleted_post", array($this->director, "remove"));
    }

		// attachments
		add_action("add_attachment", array($this->director, "put"));
		add_action("edit_attachment", array($this->director, "put"));
		// add_action("delete_attachment", array($this->director, "remove"));

		// reindex button in admin
		add_action("admin_post_wp_elastic_posts_reindex", array($this, "reindex"));
	}

	protected function setDirector()
	{
		$root = dirname(dirname(dirname(dirname(__DIR__))));

		$options = array(
			"settingsDirectory" => $root . "/config/elasticsearch/jhuedu"
		);

		$this->director = new \ElasticPosts\Director($options);
		
	}

	public function reindex()
	{
		$this->director->reindex();

		$redirect = admin_url("options-general.php?page=elastic-posts");
		header("Location: {$redirect}");
	}
}

new ElasticPosts();
