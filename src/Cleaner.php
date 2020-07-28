<?php

namespace ElasticPosts;

class Cleaner
{
  public function clean($item, $type)
  {
    $condensedClass = str_replace("_", "", $type);
    $cleanerClass = "\\ElasticPosts\\Cleaners\\{$condensedClass}";
    if (!class_exists($cleanerClass)) {
      $cleanerClass = "\\ElasticPosts\\Cleaners\\Base";
    }

    $cleaner = new $cleanerClass();
    return $cleaner->clean($item);
  }
}
