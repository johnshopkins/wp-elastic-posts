<?php

namespace ElasticPosts\Cleaners;

class profile extends Base
{
    public function clean($post)
    {
        $post = parent::clean($post);
        $post = $this->assignDescription($post, "short_bio");
        $post = $this->assignSummary($post, null);
        $post = $this->removeUselessWpStuff($post);
        
        return $post;
    }
}
