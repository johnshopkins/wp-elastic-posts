<?php

namespace elasticfields\classes;

class Elasticsearch
{
	protected $client;
	protected $settings;
	protected $wputils;

	/**
	 * __construct
	 * @param array  $config  Elastic search client configuration. See:
	 *                        http://www.elasticsearch.org/guide/en/elasticsearch/client/php-api/current/_configuration.html
	 * @param object $wputils WordPressUtils object
	 */
	public function __construct($config, $settings, $wputils)
	{
		$this->client = new \Elasticsearch\Client($config);
		$this->settings = $settings;
		$this->wputils = $wputils;
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
		// through (for example, like when a post is restored
		// from the trash, we won't know if it was a published
		// post until this point)
		if ($post->post_status !== "publish") {
			return;
		}

		// this post type is not being saved
		if (!isset($this->settings[$post->post_type])) return false;

		$settings = $this->settings[$post->post_type];
		
		$params = array(
			"index" => $settings["index"],
			"type" => $settings["type"],
			"id" => $id,
			"body" => $settings["cleaner"]->clean($post)
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
		if (!isset($this->settings[$post->post_type])) return false;

		$settings = $this->settings[$post->post_type];

		$params = array(
			"index" => $settings["index"],
			"type" => $settings["type"],
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

		// get posts by type
		$post_types = array_keys($this->settings);
		$posts = array();
		foreach ($post_types as $type) {
			$posts[$type] = $this->wputils->getPosts($type);
		}

		// put posts in elasticsearch
		foreach ($posts as $type => $posts) {

			$settings = $this->settings[$type];

			foreach ($posts as $post) {
				$params = array(
					"index" => $settings["index"],
					"type" => $settings["type"],
					"id" => $post->ID,
					"body" => $settings["cleaner"]->clean($post)
				);

				$responses[] = $this->client->index($params);
			}
		}

		return $responses;
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