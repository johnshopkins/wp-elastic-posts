<?php

namespace ElasticPosts\Cleaners;

class fact extends Base
{
  public function clean($post)
  {
    // for cleaning of subobjects -- can be just an API URL
    if (!is_object($post)) return $post;

    $post = parent::clean($post);
    $post = $this->assignDescription($post, "fact_description");
    $post = $this->assignSummary($post, null);
    $post = $this->removeUselessWpStuff($post);

    // clean icon image
    $attachmentCleaner = new attachment();

    if (isset($post->icon)) {
      $post->icon = $attachmentCleaner->clean($post->icon);
    }
    
    return $post;
  }
}
