<?php

namespace ElasticPosts\Cleaners;

class searchresponse
{
  public function clean($post)
  {
    $cleaned = new \StdClass();

    $cleaned->id = $post->ID;
    $cleaned->post_title = $post->post_title;
    $cleaned->description = $post->meta["response"];
    $cleaned->tags = $post->meta["keywords"];

    return $cleaned;
  }
}
