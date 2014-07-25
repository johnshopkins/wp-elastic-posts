<?php

namespace ElasticPosts\Cleaners;

class fact extends Base
{
    public function clean($post)
    {
        $post = parent::clean($post);
        $post = $this->assignDescription($post, "fact_description");
        $post = $this->assignSummary($post, null);
        $post = $this->removeUselessWpStuff($post);
        return $post;
    }
}
