<?php

namespace ElasticPosts;

use Secrets\Secret;

class Elasticsearch
{
	protected $settings_directory;
	protected $logger;

	/**
	 * Indexes already assigned to jhu
	 * @var array
	 */
	protected $existingIndexes = array();

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
		$this->logger = $options["logger"];

		// allow for dependency injection (testing)
        $args = func_get_args();
        $args = array_shift($args);

        $this->wordpress = isset($args["wordpress"]) ? $args["wordpress"] : new \WPUtilities\WordPressWrapper();
        $this->httpEngine = isset($args["httpEngine"]) ? $args["httpEngine"] : new \HttpExchange\Adapters\Resty(new \Resty());
	}

	public function init()
	{
		if (!$this->setupVars()) {
			return false;
		}

        $this->client = isset($args["elasticsearch"]) ? $args["elasticsearch"] : new \Elasticsearch\Client($this->config);
        return true;
	}

	protected function setupVars()
	{
		$this->post_types = array_keys($this->wordpress->get_option("elastic-posts_settings_post_types"));
		$this->index = $this->wordpress->get_option("elastic-posts_settings_index");
		$this->config = $this->getConfig();
		$this->apiBase = \WPUtilities\API::getApiBase();

		if (!$this->post_types) {
			$this->logger->addWarning("Elastic Posts plugin :: There are no post types selected to import into elasticsearch.");
			return false;
		}

		if (!$this->index) {
			$this->logger->addWarning("Elastic Posts plugin :: An index has not been set.");
			return false;
		}

		if (!$this->config) {
			return false;
		}

		return true;
	}

	/**
	 * Get the config for the elasticsearch box
	 * @return array
	 */
	protected function getConfig()
	{
		$box = $this->wordpress->get_option("elastic-posts_settings_box");

		if (!$box) {
			$this->logger->addWarning("Elastic Posts plugin :: An elasticsearch box has not been set.");
			return false;
		}

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
	public function putOne($id, $index = null)
	{
		$id = $this->getTrueId($id);

		$params = array(
			// get changes
			"clear_cache" => true,
			// post may have been a draft
			"status" => "any"
		);
		$post = $this->httpEngine->get("{$this->apiBase}/{$id}", $params)->getBody()->data;

		// new post initiated (autosave)
		if (!$post) {
			return false;
		}

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
			"index" => !is_null($index) ? $index : $this->index,
			"type" => $type,
			"id" => $id,
			"body" => $post
		);

		return $this->client->index($params);
	}


	public function put($ids, $index = null)
	{
		$ids = (array) $ids;
		foreach ($ids as $id) {
			$this->putOne($id, $index);
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
	public function putAll($index = null)
	{
		$responses = array();
		$data = array();

		foreach ($this->post_types as $type) {
			$data[$type] = $this->httpEngine->get("{$this->apiBase}/{$type}", array("per_page" => -1, "clear_cache" => true))->getBody()->data;
		}

		// put posts in elasticsearch
		foreach ($data as $type => $posts) {
			foreach ($posts as $post) {
				$responses[] = $this->putOne($post->ID, $index);
			}
		}

		return $responses;
	}

	public function reindex()
	{
		// create new index
		$newIndex = $this->createIndex();

		// put data into new index
		$this->putAll($newIndex);

		// assign new index to alias
		$alias = get_option("elastic-posts_settings_index");
		$this->clearAndAssignAlias($newIndex, $alias);

		// delete old index
		foreach ($this->existingIndexes as $index) {
			$this->deleteIndex($index);
		}
	}


	/**
	 * Get index settings
	 * http://www.elasticsearch.org/guide/en/elasticsearch/client/php-api/current/_index_operations.html
	 * @param  string $index Name of index to get settings for
	 * @return array 
	 */
	public function getSettingsForIndex($index)
	{
		try {
			return $this->client->indices()->getSettings(array("index" => $index));
		} catch (\Exception $e) {
			return array();
		}
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
		$this->existingIndexes = $this->getIndexesForAlias($alias);

		// Create remove list to later remove these indices
		$changes = array();
		$changes = array_map(function ($index) use ($alias) {
			return array("remove" => array(
				"index" => $index,
				"alias" => $alias
			));
		}, $this->existingIndexes);

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

	public function deleteIndex($index)
	{
		return $this->client->indices()->delete(array(
			"index" => $index
		));
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
