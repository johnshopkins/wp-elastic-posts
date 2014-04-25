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

	public function postSaved($id)
	{
		// Post changed from post.php or from Quick Edit on edit.php
		if (!empty($_POST)) {

			if ($_POST["post_status"] !== "publish") {
				return $this->remove($id);
			} else {
				return $this->put($id);
			}

		// new post added, bulk actions from edit.php, restore from trash
		// if no action is set, this is a new post initiating
		} else if (!empty($_GET) && isset($_GET["action"])) {

			$action = $_GET["action"];
			$ids = $_GET["post"];

			if ($action == "edit") {
				if ($_GET["_status"] != "publish") {
					return $this->remove($ids);

				} else {
					return $this->put($ids);
				}
			}

			if ($action == "untrash") {
				return $this->put($ids);
			}

		// new post initating, new post inserted
		} else if ($id) {
			return $this->put($id);
		}
	}

	public function attachmentSaved($id)
	{
		return $this->put($id);
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
