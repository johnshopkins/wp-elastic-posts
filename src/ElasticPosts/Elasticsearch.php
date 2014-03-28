<?php

namespace ElasticPosts;

use Secrets\Secret;

class Elasticsearch
{
	protected $client;
	protected $post_types;
	protected $wputils;

	public function __construct()
	{
		$this->post_types = array_keys(get_option("elastic-posts_post_types_post_types"));
		$this->index = get_option("elastic-posts_index_index");
		
		if (!$this->post_types) {
			// @log and email devs
			// die();
		}

		$this->wputils = new WordPressUtils();
		$this->client = new \Elasticsearch\Client($this->getConfig());
	}

	/**
	 * Get the config for the elasticsearch box
	 * @return array
	 */
	protected function getConfig()
	{
		$box = get_option("elastic-posts_box_box");
		$secrets = Secret::get("qbox", $box);

		return array(
			"hosts" => array($secrets->connections->https->url),
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
		$post = $this->wputils->getPost($id);

		// make sure an unpublished post doesn't sneak
		// through (for example, when a post is restored
		// from the trash, we won't know if it was a published
		// post until this point)
		if ($post->post_status !== "publish") {
			return;
		}

		// this post type shoud not be saved
		if (!in_array($post->post_type, $this->post_types)) return false;

		$cleaner = $this->getCleaner($post->post_type);
		
		$params = array(
			"index" => $this->index,
			"type" => $post->post_type,
			"id" => $id,
			"body" => $cleaner->clean($post)
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
		$post = $this->wputils->getPost($id, false);

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

		$posts = array();
		foreach ($this->post_types as $type) {
			$posts[$type] = $this->wputils->getPosts($type);
		}

		// put posts in elasticsearch
		foreach ($posts as $type => $posts) {

			$cleaner = $this->getCleaner($type);

			foreach ($posts as $post) {
				$params = array(
					"index" => $this->index,
					"type" => $type,
					"id" => $post->ID,
					"body" => $cleaner->clean($post)
				);

				$responses[] = $this->client->index($params);
			}
		}

		return $responses;
	}

	protected function getCleaner($type)
	{
		$cleanerName = "\\ElasticPosts\\PostTypes\\{$type}";
		return new $cleanerName();
	}

	/**
	 * If the post is a revision, use the ID of
	 * the parent post.
	 * @param  integer $id Post ID
	 * @return ingeter
	 */
	protected function getTrueId($id)
	{
		$revision = $this->wputils->wp_is_post_revision($id); // returns parent post ID if this post is a revision
		return $revision === false ? $id : $revision;
	}
}