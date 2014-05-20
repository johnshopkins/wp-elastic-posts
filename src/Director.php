<?php

namespace ElasticPosts;

use Secrets\Secret;

class Director
{
	/**
	 * Location of elasticsearch settings
	 * @var string
	 */
	protected $settingsDirectory;

	/**
	 * Gearman client
	 * @var object
	 */
	protected $gearmanClient;


	public function __construct($options = array(), $injection = array())
	{
		$this->settingsDirectory = $options["settingsDirectory"];

		$this->wordpress = isset($injection["wordpress"]) ? $injection["wordpress"] : new \WPUtilities\WordPressWrapper();

		$this->gearmanClient = isset($injection["gearmanClient"]) ? $injection["gearmanClient"] : new \GearmanClient();
		$this->gearmanClient->addServer("127.0.0.1");
	}

	public function put($ids, $index = null)
	{
		return array_map(function ($id) use ($index) {
			return $this->gearmanClient->doBackground("elasticsearch_put", json_encode(array(
				"id" => $id,
				"index" => $index
			)));
		}, (array) $ids);
	}

	public function remove()
	{
		return array_map(function ($id) {
			return $this->gearmanClient->doBackground("elasticsearch_delete", json_encode(array(
				"id" => $id,

				// save post type since when gearman gets around to deleting this from
				// elasticsearch, it will be delted from wordpress and this info will be lost
				"post_type" => $this->wordpress->get_post_type($id)
			)));
		}, (array) $_GET["post"]);
	}

	public function reindex()
	{
		return $this->gearmanClient->doNormal("elasticsearch_reindex", json_encode(array(
			"settingsDirectory" => $this->settingsDirectory
		)));
	}

}
