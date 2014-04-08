<?php

namespace ElasticPosts\Cleaners;

class fieldofstudy extends Base
{
    public function clean($post)
    {
        $post = parent::clean($post);
        $post = $this->assignDescription($post, "description");
        $post = $this->assignSummary($post, "summary");
        
        // remove *_import fields
        foreach ($post as $key => $value) {
            $keyLength = strlen($key);
            if (substr($key, $keyLength - 7) == "_import") {
                unset ($post->$key);
            }
        }

        return $post;
    }
}