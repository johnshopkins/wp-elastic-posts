<?php

namespace ElasticPosts;

use Secrets\Secret;

class Elasticsearch
{
	/**
	 * WordPress wrapper
	 * @var object
	 */
	protected $wordpress;

	protected $client;
	protected $httpEngine;
	protected $apiBase;
	protected $post_types;

	public function __construct()
	{
		// allow for dependency injection (testing)
        $args = func_get_args();
        $args = array_shift($args);

        $this->wordpress = isset($args["wordpress"]) ? $args["wordpress"] : new \WPUtilities\WordPressWrapper();
        $this->httpEngine = isset($args["httpEngine"]) ? $args["httpEngine"] : new \HttpExchange\Adapters\Resty(new \Resty());
        $this->client = isset($args["elasticsearch"]) ? $args["elasticsearch"] : new \Elasticsearch\Client($this->getConfig());

        $this->setupVars();
	}

	protected function setupVars()
	{
		$this->post_types = array_keys($this->wordpress->get_option("elastic-posts_settings_post_types"));
		$this->index = $this->wordpress->get_option("elastic-posts_settings_index");
		$this->apiBase = \WPUtilities\API::getApiBase();

		if (!$this->post_types) {
			// @log and email devs
		}

		if (!$this->index) {
			// @log and email devs
		}
	}

	/**
	 * Get the config for the elasticsearch box
	 * @return array
	 */
	protected function getConfig()
	{
		$box = $this->wordpress->get_option("elastic-posts_settings_box");
		$secrets = Secret::get("qbox", $box);

		return array(
			"hosts" => array($secrets->url . ":80"),
			"connectionParams" => array(
				"auth" => array(
					$secrets->username,
					$secrets->password,
					"Basic"
				)
			)
		);
	}

	/**
	 * Puts one field of study in the WordPress
	 * database into elasticsearch
	 * @param  integer $id Post ID
	 * @return array Response from elasticsearch
	 */
	public function putOne($id)
	{
		$id = $this->getTrueId($id);

		$params = array(
			// get changes
			"clear_cache" => true,
			// post may have been a draft
			"status" => "any"
		);
		$post = $this->httpEngine->get("{$this->apiBase}/{$id}", $params)->getBody()->data;
		$type = $post->post_type;

		// make sure an unpublished post doesn't sneak
		// through (for example, when a post is restored
		// from the trash, we won't know if it was a published
		// post until this point)
		if ($type != "attachment" && $post->post_status !== "publish") {
			return;
		}

		// this post type shoud not be saved
		if (!in_array($type, $this->post_types)) return false;


		$condensedClass = str_replace("_", "", $type);
		$cleanerClass = "\\ElasticPosts\\Cleaners\\{$condensedClass}";
		if (!class_exists($cleanerClass)) {
			$cleanerClass = "\\ElasticPosts\\Cleaners\\Base";
		}

		$cleaner = new $cleanerClass();
		$post = $cleaner->clean($post);

		$params = array(
			"index" => $this->index,
			"type" => $type,
			"id" => $id,
			"body" => $post
		);

		return $this->client->index($params);
	}


	public function put($ids)
	{
		$ids = (array) $ids;
		foreach ($ids as $id) {
			$this->putOne($id);
		}
	}



	/**
	 * Removes one field of study in elasticsearch
	 * @param  integer $id Post ID
	 * @return array Response from elasticsearch
	 */
	public function removeOne($id)
	{
		$id = $this->getTrueId($id);
		$post = $this->httpEngine->get("{$this->apiBase}/{$id}", array("clear_cache" => true, "status" => "any"))->getBody()->data;

		// if the post is not published
		if (!$post) return false;

		// this post type was never saved
		if (!in_array($post->post_type, $this->post_types)) return false;

		$params = array(
			"index" => $this->index,
			"type" => $post->post_type,
			"id" => $id
		);

		// delete actions in WP are called twice; make sure the document
		// exists in elasticsearch before deleting it
		if ($this->client->exists($params)) {
			return $this->client->delete($params);
		} else {
			return false;
		}

	}

	public function remove($ids)
	{
		$ids = (array) $ids;
		foreach ($ids as $id) {
			$this->removeOne($id);
		}
	}

	/**
	 * Put all fields of study present in
	 * the WordPress database into elasticsearch
	 * @return array $response Responses from elasticsearch
	 */
	public function putAll()
	{
		$responses = array();

		$data = array();

		foreach ($this->post_types as $type) {
			$data[$type] = $this->httpEngine->get("{$this->apiBase}/{$type}", array("per_page" => -1, "clear_cache" => true))->getBody()->data;
		}

		// put posts in elasticsearch
		foreach ($data as $type => $posts) {
			foreach ($posts as $post) {
				$responses[] = $this->putOne($post->ID);
			}
		}

		return $responses;
	}


	protected function removeUselessWpStuff($post)
	{
		unset($post->ID);
		unset($post->post_author);
		unset($post->post_date_gmt);
		unset($post->post_status);
		unset($post->comment_status);
		unset($post->ping_status);
		unset($post->post_password);
		unset($post->post_name);
		unset($post->to_ping);
		unset($post->pinged);
		unset($post->post_modified_gmt);
		unset($post->post_content_filtered);
		unset($post->post_parent);
		unset($post->guid);
		unset($post->menu_order);
		unset($post->post_type);
		unset($post->post_mime_type);
		unset($post->comment_count);
		unset($post->filter);

		return $post;
	}

	/**
	 * If the post is a revision, use the ID of
	 * the parent post.
	 * @param  integer $id Post ID
	 * @return ingeter
	 */
	protected function getTrueId($id)
	{
		$revision = $this->wordpress->wp_is_post_revision($id); // returns parent post ID if this post is a revision
		return $revision === false ? $id : $revision;
	}
}
