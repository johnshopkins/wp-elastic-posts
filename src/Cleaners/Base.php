<?php

namespace ElasticPosts\Cleaners;

class Base
{
    public function clean($post)
    {
        $post = $this->extractMeta($post);
        unset($post->stitched);
        return $post;
    }

    protected function extractMeta($post)
    {   
        // attachments that aren't "real" attachments (media repeater elements)
        if (!isset($post->meta)) return $post;


        foreach ($post->meta as $k => $v) {
            $post->$k = $v;
        }

        unset($post->meta);

        return $post;
    }

    protected function assignField($post, $fieldToAssign, $currentField)
    {
        if ($currentField == $fieldToAssign) return $post;

        if (!is_null($currentField)) {
            $post->$fieldToAssign = $post->$currentField;
            unset($post->$currentField);
        } else {
            unset($post->$fieldToAssign);
        }
        
        return $post;
    }

    protected function assignDescription($post, $field = "description")
    {
        $this->assignField($post, "description", $field);
        return $post;
    }

    protected function assignSummary($post, $field = "summary")
    {
        $this->assignField($post, "summary", $field);
        return $post;
    }

    protected function removeUselessWpStuff($post)
    {
        unset($post->ID);
        unset($post->post_date);
        unset($post->post_author);
        unset($post->post_date_gmt);
        unset($post->post_status);
        unset($post->comment_status);
        unset($post->ping_status);
        unset($post->post_password);
        unset($post->post_name);
        unset($post->to_ping);
        unset($post->pinged);
        unset($post->post_modified);
        unset($post->post_modified_gmt);
        unset($post->post_content_filtered);
        unset($post->post_parent);
        unset($post->guid);
        unset($post->menu_order);
        unset($post->post_type);
        unset($post->post_mime_type);
        unset($post->comment_count);
        unset($post->filter);

        if (isset($post->post_content)) unset($post->post_content);
        if (isset($post->post_excerpt)) unset($post->post_excerpt);

        return $post;
    }

    protected function cleanMedia($post)
    {
        $attachmentCleaner = new attachment();
        foreach ($post->media as $type => &$images) {

          foreach ($images as &$image) {
            $image->file = $attachmentCleaner->clean($image->file);
          }
          
        }

        return $post;
    }
}
