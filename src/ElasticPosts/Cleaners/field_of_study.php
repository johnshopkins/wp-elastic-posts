<?php

namespace ElasticPosts\Cleaners;

class field_of_study
{
    public function __construct()
    {
        die("constructed");
    }

    public function clean($post)
    {
        print_r($post);
        die();
    }
}