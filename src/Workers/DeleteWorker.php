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

    try {
      $result = $this->deleteOne($workload->id, $workload->post_type);
      if ($result) echo $this->getDate() . " Finished elasticsearch DELETE of post #{$workload->id}...\n";
      echo "------\n";
    } catch (\Exception $e) {
      $error = $e->getMessage();
      echo $this->getDate() . " Delete of post {$type}/{$id} FAILED. Error message: {$error}\n";
      echo "------\n";
    }
  }

}
