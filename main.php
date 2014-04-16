<?php
/*
Plugin Name: Elastic Posts
Description: Imports posts into elasticsearch
Author: Jen Wachter
Version: 0.1
*/

class ElasticPostsMain
{
	protected $logger;

	public function __construct($logger)
	{
		$this->logger = $logger;

		// Create admin pages
		add_action("wp_loaded", function () {
			new \ElasticPosts\Admin();
		});

		// posts
		add_action("save_post", array($this, "postSaved"));
		add_action("delete_post", array($this, "remove")); // trash not turned on
		add_action("wp_trash_post", array($this, "remove")); // trash turned on

		// attachments
		add_action("add_attachment", array($this, "attachmentSaved"));
		add_action("edit_attachment", array($this, "attachmentSaved"));
		add_action("delete_attachment", array($this, "remove"));

		// reindex button in admin
		add_action("admin_post_wp_elastic_posts_reindex", array($this, "reindex"));
	}

	protected function getElasticsearch()
	{
		$root = dirname(dirname(dirname(dirname(__DIR__))));

		$options = array(
			"settings_directory" => $root . "/config/elasticsearch/jhuedu",
			"logger" => $this->logger
		);

		$es = new \ElasticPosts\Elasticsearch($options);

		if (!$es->init()) {
			return false;
		}

		return $es;
		
	}

	public function postSaved($id)
	{
		$es = $this->getElasticsearch();
		
		if (!$es) {
			return false;
		}

		// Post changed from post.php or from Quick Edit on edit.php
		if (!empty($_POST)) {

			if ($_POST["post_status"] !== "publish") {
				return $es->remove($id);
			} else {
				return $es->put($id);
			}

		// new post added, bulk actions from edit.php, restore from trash
		// if no action is set, this is a new post initiating
		} else if (!empty($_GET) && isset($_GET["action"])) {

			$action = $_GET["action"];
			$ids = $_GET["post"];

			if ($action == "edit") {
				if ($_GET["_status"] != "publish") {
					return $es->remove($ids);

				} else {
					return $es->put($ids);
				}
			}

			if ($action == "untrash") {
				return $es->put($ids);
			}

		// new post initating, new post inserted
		} else if ($id) {
			return $es->put($id);
		}
	}

	public function remove()
	{
		$es = $this->getElasticsearch();
		
		if (!$es) {
			return false;
		}

		$ids = $_GET["post"];
		$es->remove($ids);
	}

	public function attachmentSaved($id)
	{
		$es = $this->getElasticsearch();
		
		if (!$es) {
			return false;
		}

		return $es->put($id);
	}

	public function reindex()
	{
		$es = $this->getElasticsearch();
		
		if (!$es) {
			return false;
		}

		$es->reindex();

		$redirect = admin_url("options-general.php?page=elastic-posts");
		header("Location: {$redirect}");
	}
}

new ElasticPostsMain($wp_logger);
