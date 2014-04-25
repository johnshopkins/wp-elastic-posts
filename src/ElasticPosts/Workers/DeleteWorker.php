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
        return json_encode($this->deleteOne($workload->id, $workload->post_type));
    }

    /**
     * Removes a document from elasticsearch
     * @param  integer $id Post ID
     * @return array Response from elasticsearch
     */
    protected function deleteOne($id, $postType)
    {
        if ($this->isRevision($id)) return;

        $params = array(
            "index" => $this->index,
            "type" => $postType,
            "id" => $id
        );

        // delete actions in WP are called twice; make sure the document
        // exists in elasticsearch before deleting it
        return $this->elasticsearchClient->exists($params) ? $this->elasticsearchClient->delete($params) : false;
    }

}