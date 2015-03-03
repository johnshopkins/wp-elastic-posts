<?php

namespace ElasticPosts\Cleaners;

class searchresponse extends Base
{
  public function clean($post)
  {
    // for cleaning of subobjects -- can be just an API URL
    if (!is_object($post)) return $post;
    
    $post = parent::clean($post);
    $post = $this->assignDescription($post, "response");
    $post = $this->removeUselessWpStuff($post);

    return $post;
  }
}
