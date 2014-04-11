<?php
/*
Plugin Name: Elastic Posts
Description: Imports posts into elasticsearch
Author: Jen Wachter
Version: 0.1
*/


// Create admin pages
add_action("wp_loaded", function () {
	new \ElasticPosts\Admin();
});

// whenever a post is changed (includes restoring from trash)
add_action("save_post", "elasticFields_postSaved");

// whenever a post is trashed
add_action("delete_post", "elasticFields_removeOne"); // trash not turned on
add_action("wp_trash_post", "elasticFields_removeOne"); // trash turned on

// attachments
add_action("add_attachment", "elasticFields_attachmentSaved");
add_action("edit_attachment", "elasticFields_attachmentSaved");
add_action("delete_attachment", "elasticFields_removeOne");

// reindex button in admin
add_action("admin_post_wp_elastic_posts_reindex", function ()
{
	$root = dirname(dirname(dirname(dirname(__DIR__))));
	$es = new \ElasticPosts\Elasticsearch(array(
		"settings_directory" => $root . "/config/elasticsearch/jhuedu"
	));

	$es->reindex();

	$redirect = admin_url("options-general.php?page=elastic-posts");
	header("Location: {$redirect}");
});

// // imports all fields (for testing)
// add_action("admin_init", function () {
// 	$es = new \ElasticPosts\Elasticsearch();
// 	$es->putAll();
// });



function elasticFields_attachmentSaved($id)
{
	$es = new \ElasticPosts\Elasticsearch();
	$es->put($id);
}


/**
 * Analyzes $_POST and $_GET to figure out
 * what to do with the post in question.
 * @param  integer $id Post ID
 * @return array Response from elasticsearch
 */
function elasticFields_postSaved($id)
{
	$es = new \ElasticPosts\Elasticsearch();

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
	$es = new \ElasticPosts\Elasticsearch();
	$es->remove($id);
}