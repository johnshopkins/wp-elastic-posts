<?php

namespace ElasticPosts\Workers;

use Secrets\Secret;

class PutWorker extends BaseWorker
{
    protected function addFunctions()
    {
        parent::addFunctions();
        $this->worker->addFunction("elasticsearch_put", array($this, "put"));
    }

    public function put(\GearmanJob $job)
    {
        $workload = json_decode($job->workload());
        echo $this->getDate() . " Initiating elasticsearch PUT of post #{$workload->id}...\n";
        $result = $this->putOne($workload->id, $workload->index);
        if ($result) echo $this->getDate() . " Finished elasticsearch PUT of post #{$workload->id}.\n";
    }

    /**
     * Put all fields of study present in
     * the WordPress database into elasticsearch
     * @return array $response Responses from elasticsearch
     */
    public function putAll($index = null)
    {
        $responses = array();
        $data = array();

        foreach ($this->post_types as $type) {

            if ($type == "attachment") {
                $status = "inherit";
            } else if ($type == "instagram_media") {
                $status = array("publish", "always_keep");
            } else {
                $status = "publish";
            }

            $result = $this->wordpress_query->run(array(
              "post_type" => $type,
              "post_status" => $status,
              "paged" => $paged,
              "fields" => "ids"
            ));

            $ids = $result->posts;

            foreach ($ids as $id) {
              $responses[] = $this->putOne($id, $index);
              echo $this->getDate() . " Put of post {$type}/{$id} complete.\n";
            }
        }

        return $responses;
    }

    /**
     * Puts one field of study in the WordPress
     * database into elasticsearch
     * @param  integer $id Post ID
     * @return array Response from elasticsearch
     */
    public function putOne($id, $index = null)
    {
        $post = $this->getPostFromApi($id);

        if (!$post) {
            echo $this->getDate() . " Post # {$id} is either an autosave or revision. Skipping.\n";
            return false;
        }

        $params = array(
            "index" => !is_null($index) ? $index : $this->index,
            "type" => $post->post_type,
            "id" => $id,
            "body" => $this->cleanPost($post)
        );

        return $this->elasticsearchClient->index($params);
    }

    /**
     * Get the post from the API and figure out
     * if it should be sent to elasticsearch
     * @param  integer $id Post ID
     * @return mixed Post object or FALSE (should not be sent to elasticsearch)
     */
    protected function getPostFromApi($id)
    {
        $params = array(
            "clear_cache" => true,      // get changes
            "status" => "any",          // post may have been a draft
            "returnEmbedded" => false   // do not return embedded objects
        );

        $post = $this->api->get($id, $params)->data;

        if (!$post) return false; // autosave

        // make sure an unpublished post doesn't sneak
        // through (for example, when a post is restored
        // from the trash, we won't know if it was a published
        // post until this point)
        if ($post->post_type != "attachment" && $post->post_status !== "publish") return false;

        // this post type shoud not be saved
        if (!in_array($post->post_type, $this->post_types)) return false;

        return $post;
    }

    /**
     * Get the post data ready for elasticsearch
     * @param  object $post Post object
     * @return object Cleaned post
     */
    protected function cleanPost($post)
    {
        $condensedClass = str_replace("_", "", $post->post_type);
        $cleanerClass = "\\ElasticPosts\\Cleaners\\{$condensedClass}";
        if (!class_exists($cleanerClass)) {
            $cleanerClass = "\\ElasticPosts\\Cleaners\\Base";
        }

        $cleaner = new $cleanerClass();
        return $cleaner->clean($post);
    }
}
