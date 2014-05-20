<?php

namespace ElasticPosts\Cleaners;

class quote extends Base
{
    public function clean($post)
    {
        $post = parent::clean($post);
        $post = $this->assignDescription($post, "quote");
        $post = $this->assignSummary($post, null);
        $post = $this->removeUselessWpStuff($post);
        return $post;
    }
}