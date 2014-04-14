<?php
/*
Plugin Name: Elastic Posts
Description: Imports posts into elasticsearch
Author: Jen Wachter
Version: 0.1
*/

function elasticFields_getOptions()
{
	$root = dirname(dirname(dirname(dirname(__DIR__))));
	return array(
		"settings_directory" => $root . "/config/elasticsearch/jhuedu"
	);
}


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
	$es = new \ElasticPosts\Elasticsearch(elasticFields_getOptions());
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
	$es = new \ElasticPosts\Elasticsearch(elasticFields_getOptions());
	$es->put($id);
}


/**
 * Analyzes $_POST and $_GET to figure out
 * what to do with the post in question.
 *
 * post_saved is triggered when a post is
 * created, updated, or restored from the trash
 * 
 * @param  integer $id Post ID
 * @return array Response from elasticsearch
 */
function elasticFields_postSaved($id)
{
	$es = new \ElasticPosts\Elasticsearch(elasticFields_getOptions());

	// echo "post saved";
	// print_r($_POST);
	// print_r($_GET);
	// die();

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
	}
}

/**
 * Removes a resource
 * @param  integer $id Post ID
 * @return array Response from elasticsearch
 */
function elasticFields_removeOne($id)
{
	$es = new \ElasticPosts\Elasticsearch(elasticFields_getOptions());

	$ids = $_GET["post"];
	$es->remove($ids);
}