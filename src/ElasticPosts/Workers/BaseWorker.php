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
     * HTTP engine
     * @var object
     */
    protected $httpEngine;

    /**
     * Elasticsearch client
     * @var object
     */
    protected $elasticsearchClient;

    public function __construct($settings = array(), $injection = array())
    {
        $this->worker = $settings["worker"];
        $this->logger = $settings["logger"];

        $this->wordpress = isset($injection["wordpress"]) ? $injection["wordpress"] : new \WPUtilities\WordPressWrapper();
        $this->httpEngine = isset($injection["httpEngine"]) ? $injection["httpEngine"] : new \HttpExchange\Adapters\Resty(new \Resty());
        
        $this->setupVars();

        $this->elasticsearchClient = isset($injection["elasticsearch"]) ? $injection["elasticsearch"] : new \Elasticsearch\Client($this->getElasticsearchConfig());
        
        $this->addFunctions();
    }

    protected function setupVars()
    {
        $this->post_types = array_keys($this->wordpress->get_option("elastic-posts_settings_post_types"));
        $this->index = $this->wordpress->get_option("elastic-posts_settings_index");
        $this->apiBase = \WPUtilities\API::getApiBase();

        if (!$this->post_types) {
            $this->logger->addWarning("Elastic Posts plugin :: There are no post types selected to import into elasticsearch.");
            return false;
        }

        if (!$this->index) {
            $this->logger->addWarning("Elastic Posts plugin :: An index has not been set.");
            return false;
        }

        return true;
    }

    /**
     * Get the config for the elasticsearch box
     * @return array
     */
    protected function getElasticsearchConfig()
    {
        $box = $this->wordpress->get_option("elastic-posts_settings_box");

        if (!$box) {
            $this->logger->addWarning("Elastic Posts plugin :: An elasticsearch box has not been set.");
            return false;
        }

        $secrets = Secret::get("qbox", $box);

        return array(
            "hosts" => array($secrets->url . ":80"),
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
     * @param  integer $id Post ID
     * @return boolan
     */
    protected function isRevision($id)
    {
        return $this->wordpress->wp_is_post_revision($id);
    }
}