<?php

namespace ElasticPosts\Cleaners;

class block extends Base
{
    public function clean($post)
    {
        $post = parent::clean($post);
        $post = $this->assignDescription($post, "long_body");
        $post = $this->assignSummary($post, "short_body");
        $post = $this->removeUselessWpStuff($post);
        return $post;
    }
}