<?php

namespace ElasticPosts\Cleaners;

class quote extends Base
{
  public function clean($post)
  {
    // for cleaning of subobjects -- can be just an API URL
    if (!is_object($post)) return $post;
    
    $post = parent::clean($post);
    $post = $this->assignDescription($post, "quote");
    $post = $this->assignSummary($post, null);
    $post = $this->removeUselessWpStuff($post);

    // clean person
    $personCleaner = new person();
    $post->citation = $personCleaner->clean($post->citation);

    return $post;
  }
}
