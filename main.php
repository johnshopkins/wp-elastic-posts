<?php
/*
Plugin Name: Elastic Posts
Description: Imports posts into elasticsearch
Author: Jen Wachter
Version: 0.1
*/

use Secrets\Secret;

/**
 * actions
 */

// whenever a post is changed (includes restoring from trash)
add_action("save_post", "elasticFields_postSaved");

// whenever a post is trashed
add_action("delete_post", "elasticFields_removeOne"); // trash not turned on
add_action("wp_trash_post", "elasticFields_removeOne"); // trash turned on

// // imports all fields (for testing)
// add_action("admin_init", "elasticFields_putAll");

// Create admin pages
add_action("wp_loaded", function () {
	new \ElasticPosts\Admin();
});


/**
 * Return a configured instance of Elasticsearch
 * @return object \elasticfields\classes\Elasticsearch
 */
function elasticFields_getElasticsearch()
{
	$secrets = Secret::get("qbox", "professor_x");

	$params = array(
		"hosts" => array($secrets->connections->https->url),
		"connectionParams" => array(
			"auth" => array(
				$secrets->username,
				$secrets->password,
				"Basic"
			)
		)
		// "logLevel" => $loglevel,
		// "logPath" => $logfile
	);

	return new \ElasticPosts\Elasticsearch($params);
}

/**
 * Analyzes $_POST and $_GET to figure out
 * what to do with the post in question.
 * @param  integer $id Post ID
 * @return array Response from elasticsearch
 */
function elasticFields_postSaved($id)
{
	$es = elasticFields_getElasticsearch();

	if (empty($es)) {
		return;
	}

	// single post status changed from post.php or edit.php
	if (!empty($_POST)) {

		if ($_POST["post_status"] !== "publish") {
			return $es->remove($id);
		} else {
			return $es->put($id);
		}

	// single post trash (post.php or edit.php), untrash
	// bulk trash, untrash, and post status updates
	} else if (!empty($_GET) && isset($_GET["post"]) && isset($_GET["action"])) {

		$action = $_GET["action"];
		$post = $_GET["post"];

		// moved to trash
		if ($action === "trash") {
			return $es->remove($post);
		}

		// restored from trash
		else if ($action === "untrash") {
			// later in the put() process, we make sure
			// this post was restored to "publish" before
			// pushing to elasticsearch
			return $es->put($post);
		}

		// bulk edit
		else if ($action === "edit") {

			if ($_GET["_status"] !== "publish") {
				return $es->remove($post);

			} else {
				return $es->put($post);
			}

		}

	}
}

/**
 * Removes a resource
 * @param  integer $id Post ID
 * @return array Response from elasticsearch
 */
function elasticFields_removeOne($id)
{
	$es = elasticFields_getElasticsearch();
	if (empty($es)) {
		return;
	}
	$es->remove($id);
}

/**
 * Puts data from all published fields
 * of study into elasticsearch
 * @param  integer $id Post ID
 * @return array Response from elasticsearch
 */
function elasticFields_putAll()
{
	$es = elasticFields_getElasticsearch();
	
	if (empty($es)) {
		return;
	}
	$es->putAll();
}