<?php

namespace ElasticPosts\Cleaners;

class timeline_event extends Base
{
    public function clean($post)
    {
        $post = parent::clean($post);
        $post = $this->assignDescription($post, null);
        $post = $this->assignSummary($post, null);
        $post = $this->removeUselessWpStuff($post);
        return $post;
    }
}
