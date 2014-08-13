<?php

namespace ElasticPosts\Cleaners;

class search_response extends Base
{
    public function clean($post)
    {
        $post = parent::clean($post);
        $post = $this->removeUselessWpStuff($post);

        return $post;
    }
}
