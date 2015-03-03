<?php

namespace ElasticPosts\Cleaners;

class division extends Base
{
  public function clean($post)
  {
    // for cleaning of subobjects -- can be just an API URL
    if (!is_object($post)) return $post;
    
    $post = parent::clean($post);
    $post = $this->assignDescription($post);
    $post = $this->assignSummary($post);
    $post = $this->removeUselessWpStuff($post);

    // clean media
    $post = $this->cleanMedia($post);

    return $post;
  }
}