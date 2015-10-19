<?php

namespace ElasticPosts\Cleaners;

class fieldofstudy extends Base
{
  public function clean($post)
  {
    // for cleaning of subobjects -- can be just an API URL
    if (!is_object($post)) return $post;

    $post = parent::clean($post);
    $post = $this->assignDescription($post);
    $post = $this->assignSummary($post);
    $post = $this->removeUselessWpStuff($post);

    // clean division
    $divisonCleaner = new division();
    foreach ($post->division as &$division) {
      $division = $divisonCleaner->clean($division);
    }

    foreach ($post->degrees as &$degree) {
      if (!$degree->parent) {
        unset($degree->parent);
      }
    }

    return $post;
  }
}
