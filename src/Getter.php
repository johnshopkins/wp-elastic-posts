<?php

namespace ElasticPosts;

class Getter
{
  public function __construct($dependencies)
  {
    $this->modelFetcher = $dependencies["model_fetcher"];
    $this->modelFetcher->clearAllCache();
    $this->logger = $dependencies["logger_wp"];
  }

  public function get($id, $type)
  {
    wp_cache_flush();
    $post = $this->modelFetcher->fetchOne($id);

    if (!$post || $post->post_status !== "publish") {
      return false; // autosave
    }

    return $post;
  }
}
