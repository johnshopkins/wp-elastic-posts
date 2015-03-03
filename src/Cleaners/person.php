<?php

namespace ElasticPosts\Cleaners;

class person extends Base
{
  public function clean($post)
  {
    // for cleaning of subobjects -- can be just an API URL
    if (!is_object($post)) return $post;

    $post = parent::clean($post);
    $post = $this->assignDescription($post, null);
    $post = $this->assignSummary($post, null);
    $post = $this->removeUselessWpStuff($post);

    // clean quotes
    $quoteCleaner = new quote();
    $post->featured_quote = $quoteCleaner->clean($post->featured_quote);
    $post->long_quote = $quoteCleaner->clean($post->long_quote);

    // clean majors/minors
    $fieldOfStudyCleaner = new fieldofstudy();
    foreach ($post->major as &$major) {
      $major = $fieldOfStudyCleaner->clean($major);
    }
    foreach ($post->minor as &$minor) {
      $minor = $fieldOfStudyCleaner->clean($minor);
    }

    // clean division
    $divisonCleaner = new division();
    foreach ($post->school as &$division) {
      $division = $divisonCleaner->clean($division);
    }

    // clean club
    $clubCleaner = new club();
    foreach ($post->club as &$club) {
      $club = $clubCleaner->clean($club);
    }

    // clean media
    $post = $this->cleanMedia($post);
    
    return $post;
  }
}
