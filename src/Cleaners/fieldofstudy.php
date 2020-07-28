<?php

namespace ElasticPosts\Cleaners;

class fieldofstudy
{
  public function clean($post)
  {
    $cleaned = new \StdClass();

    $cleaned->id = $post->ID;
    $cleaned->post_title = $post->post_title;
    $cleaned->tags = [];

    // add degree type to tags
    foreach ($post->filtering['programlevel'] as $degree) {
      $cleaned->tags[] = $degree->name;
    }

    // add online (if present) to tags
    foreach ($post->filtering['format'] as $format) {
      if ($format->slug === 'online') {
        $cleaned->tags[] = $format->name;
      }
    }

    foreach ($post->meta["keywords"] as $keyword) {
      $cleaned->tags[] = $keyword;
    }

    $cleaned->description = $post->meta["description"];
    $cleaned->summary = $post->meta["summary"];
    $cleaned->concentrations = $post->meta["concentrations"];
    $cleaned->what_you_can_do = $post->meta["what_you_can_do"];

    return $cleaned;
  }
}
