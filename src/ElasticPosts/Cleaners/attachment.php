<?php

namespace ElasticPosts\Cleaners;

class attachment extends Base
{
    public function clean($post)
    {
        $post = parent::clean($post);
        $post = $this->assignDescription($post);
        $post = $this->assignSummary($post);
        $post = $this->removeUselessWpStuff($post);

        return $post;
    }
}