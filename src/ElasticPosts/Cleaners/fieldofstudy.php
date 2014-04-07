<?php

namespace ElasticPosts\Cleaners;

class fieldofstudy
{
    public function clean($post)
    {
        foreach ($post->meta as $key => $value) {
            $keyLength = strlen($key);
            if (substr($key, $keyLength - 7) == "_import") {
                unset ($post->meta->$key);
            }
        }

        return $post;
    }
}