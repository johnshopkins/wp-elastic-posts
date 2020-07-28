<?php
/*
Plugin Name: Elastic Posts
Description: Imports posts into elasticsearch
Author: Jen Wachter
Version: 0.1
*/

class ElasticPosts
{
  protected $director;

  public function __construct($logger)
	{
    $this->logger = $logger;
    $this->setDirector();

    // Create admin pages
    add_action("wp_loaded", function () { new \ElasticPosts\Admin(); });

    // posts
    add_action("save_post", function ($id) {
      $this->director->saved($id, get_post_type($id));
    });

    // if trash is turned off, add a hook to take care of deleted posts.
    // Otherwise, deleted posts are treated with save_post as a status change
    if (defined("EMPTY_TRASH_DAYS") && EMPTY_TRASH_DAYS == 0) {

      add_action("deleted_post", function ($ids) {
        $ids = !is_null($ids) ? (array) $ids : (array) $_GET["post"];
        foreach ($ids as $id) {
          $this->director->remove($id, get_post_type($id));
        }
      });
    }

    // reindex button in admin
    add_action("admin_post_wp_elastic_posts_reindex", function () {
      $this->director->reindex();
      $redirect = admin_url("tools.php?page=elastic-posts");
      header("Location: {$redirect}");
    });
	}

  protected function setDirector()
  {
    $options = array(
      "logger" => $this->logger,
      "namespace" => "jhu",
      "saveTest" => function ($id) {
        // function to run in order to determine if a post should be saved
        $post = get_post($id);
        return $post->post_status == "publish";
      },
      "getAllOfType" => function ($type) {
        $query = new \WP_Query(array(
          "post_type" => $type,
          "post_status" => "publish",
          "posts_per_page" => -1,
          "fields" => "ids"
        ));
        return $query->posts;
      },
      "servers" => Secrets\Secret::get("jhu", ENV, "servers"),
      "types" => array("field_of_study", "search_response")
    );

    $this->director = new \GearmanWorkers\Elasticsearch\Director($options);
  }
}
