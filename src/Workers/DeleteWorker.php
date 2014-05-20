<?php

namespace ElasticPosts\Workers;

use Secrets\Secret;

class DeleteWorker extends BaseWorker
{
    protected function addFunctions()
    {
        parent::addFunctions();
        $this->worker->addFunction("elasticsearch_delete", array($this, "delete"));
    }
    
    public function delete(\GearmanJob $job)
    {
        $workload = json_decode($job->workload());
        echo $this->getDate() . " Initiating elasticsearch DELETE of post #{$workload->id}...\n";
        $result = $this->deleteOne($workload->id, $workload->post_type);

        if ($result) echo $this->getDate() . " Finished elasticsearch DELETE of post #{$workload->id}...\n";
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
