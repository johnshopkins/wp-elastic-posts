<?php

namespace ElasticPosts;

use Secrets\Secret;

class Elasticsearch
{
	protected $settings_directory;

	/**
	 * WordPress wrapper
	 * @var object
	 */
	protected $wordpress;

	protected $client;
	protected $httpEngine;
	protected $apiBase;
	protected $post_types;

	public function __construct($options = array())
	{
		$this->settings_directory = $options["settings_directory"];

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

		// make sure an unpublished post doesn't sneak
		// through (for example, when a post is restored
		// from the trash, we won't know if it was a published
		// post until this point)
		if ($post->post_type != "attachment" && $post->post_status !== "publish") {
			return;
		}

		// this post type shoud not be saved
		if (!in_array($post->post_type, $this->post_types)) return false;

		$params = array(
			"index" => $this->index,
			"type" => $post->post_type,
			"id" => $id,
			"body" => $this->removeUselessWpStuff($post)
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
		$post = $this->httpEngine->get("{$this->apiBase}/{$id}", array("clear_cache" => true))->getBody()->data;

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


	/**
	 * Get index settings
	 * http://www.elasticsearch.org/guide/en/elasticsearch/client/php-api/current/_index_operations.html
	 * @param  string $index Name of index to get settings for
	 * @return array 
	 */
	public function getSettingsForIndex($index)
	{
		return $this->client->indices()->getSettings(array("index" => $index));
	}


	/**
	 * Get existing indexes associated with an alias
	 * @param  string $alias Alias name
	 * @return array
	 */
	public function getIndexesForAlias($alias)
	{
		$index = $this->getSettingsForIndex($alias);
		
		if (empty($index) || !is_array($index)) {
			return array();
		}

		return array_keys($index);
	}


	/**
	 * Update index aliases
	 *
	 * http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/indices-aliases.html
	 * http://www.elasticsearch.org/guide/en/elasticsearch/client/php-api/current/_namespaces.html
	 * 
	 * $changes should look like:
	 * 
	 * array(
	 *     'add' => array(
	 *         'index' => 'myindex',
	 *         'alias' => 'myalias'
	 *     ),
	 *     'add' => array(
	 *     
	 *     ),
	 *     'remove' => array(
	 *     
	 *     )
	 * )
	 * 
	 * @param  [array] $changes see above
	 * @return 
	 */
	public function updateAliases($changes)
	{
		$params = array('body' => array(
		    'actions' => $changes
		));

		return $this->client->indices()->updateAliases($params);
	}


	/**
	 * Get existing indexes attached to an alias, clear them,
	 * and then assign alias to the passed in index
	 *
	 * http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/indices-aliases.html
	 * 
	 * @param  string $newIndex Name of the new index
	 * @param  string $alias    Name of the alias to be assigned
	 * @return
	 */
	public function clearAndAssignAlias($newIndex, $alias)
	{
		// Get existing indexes attached to 'jhu' alias
		$existing = $this->getIndexesForAlias($alias);

		// Create remove list to later remove these indices
		$changes = array();
		$changes = array_map(function ($index) {
			return array("remove" => array(
				"index" => $index,
				"alias" => $alias
			));
		}, $existing);

		// Add jhu alias to new index
		$changes[] = array("add" => array("index" => $newIndex, "alias" => $alias));

		return $this->updateAliases($changes);
	}


	/**
	 * Create new index with settings in $this->settings_directory
	 * @return string Name of the new index (useful for aliasing)
	 */
	public function createIndex()
	{
		$newIndex = "jhu_" . time();
		$indexParams = array(
			"index" => $newIndex,
			"body" => json_decode(file_get_contents($this->settings_directory . "/settings.json"), true)
		);

		$mappings = $this->settings_directory . "/mappings";

		$files = array_diff(scandir($mappings), array("..", ".", ".DS_Store"));
		foreach ($files as $file) {
		    $indexParams["body"]["mappings"][str_replace(".json", "", $file)] = json_decode(file_get_contents($mappings . "/" . $file), true);
		}

		$this->client->indices()->create($indexParams);

		return $newIndex;
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
