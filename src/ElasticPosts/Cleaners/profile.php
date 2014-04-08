<?php

namespace ElasticPosts\Cleaners;

class profile extends Base
{
    public function clean($post)
    {
        $post = parent::clean($post);
        $post = $this->assignDescription($post, "blurb");
        $post = $this->assignSummary($post, null);
        
        return $post;
    }
}