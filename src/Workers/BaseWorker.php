<?php

namespace ElasticPosts\Workers;

use Secrets\Secret;

abstract class BaseWorker
{
    /**
     * Gearman worker
     * @var object
     */
    protected $worker;

    /**
     * Monolog
     * @var object
     */
    protected $logger;

    /**
     * WordPress wrapper
     * @var object
     */
    protected $wordpress;

    /**
     * WordPress Query wrapper
     * @var object
     */
    protected $wordpress_query;

    /**
     * API
     * @var object
     */
    protected $api;

    /**
    * Post Utility
    * @var object
    */
    protected $post_util;

    /**
     * Elasticsearch client
     * @var object
     */
    protected $elasticsearchClient;

    protected $index = "jhu";

    /**
     * Post types to sync to elasticsearch
     * @var array
     */
    protected $post_types = array(
      "club",
      "division",
      "fact",
      "field_of_study",
      "milestone",
      "person",
      "quote",
      "search_response"
    );

    public function __construct($settings = array())
    {
        $this->worker = $settings["worker"];
        $this->logger = $settings["logger"];

        $this->wordpress = new \WPUtilities\WordPressWrapper();
        $this->wordpress_query = new \WPUtilities\WPQueryWrapper();
        $this->api = new \WPUtilities\API(array(), true);
        $this->post_util = new \WPUtilities\Post();

        $this->apiBase = \WPUtilities\API::getApiBase();

        $this->elasticsearchClient = new \Elasticsearch\Client($this->getElasticsearchConfig());

        $this->addFunctions();
    }

    protected function getDate()
    {
        return date("Y-m-d H:i:s");
    }

    /**
     * Get the config for the elasticsearch box
     * @return array
     */
    protected function getElasticsearchConfig()
    {
        $secrets = Secret::get("qbox", "write", ENV);

        return array(
            "hosts" => array($secrets->url),
            "connectionParams" => array(
                "auth" => array(
                    $secrets->username,
                    $secrets->password,
                    "Basic"
                )
            )
        );
    }

    protected function addFunctions() {}

    /**
    * Find out if a post is a revision
    * @param  object $post Post object
    * @return boolan
    */
    protected function isRevision($post)
    {
        return $this->post_util->isRevision($post);
    }

    /**
     * Removes a document from elasticsearch
     * @param  integer $id Post ID
     * @return array Response from elasticsearch
     */
    protected function deleteOne($id, $postType)
    {
        $params = array(
            "index" => $this->index,
            "type" => $postType,
            "id" => $id
        );

        // delete actions in WP are called twice; make sure the document
        // exists in elasticsearch before deleting it
        if (!$this->elasticsearchClient->exists($params)) {
            echo $this->getDate() . " Post #{$id} doesn't exist in elasticsearch. Skipping.\n";
            return false;
        }

        return $this->elasticsearchClient->delete($params);
    }
}
