<?php

namespace ElasticPosts\Workers;

use Secrets\Secret;

class ReindexWorker extends PutWorker
{
    /**
     * Location of elasticsearch settings
     * @var string
     */
    protected $settingsDirectory;

    /**
     * Indexes already assigned to jhu
     * @var array
     */
    protected $existingIndexes = array();


    protected function addFunctions()
    {
        parent::addFunctions();
        $this->worker->addFunction("elasticsearch_reindex", array($this, "reindex"));
    }

    public function reindex(\GearmanJob $job)
    {
        $workload = json_decode($job->workload());
        $this->settingsDirectory = $workload->settingsDirectory;

        // create new index
        $newIndex = $this->createIndex();

        // put data into new index
        $this->putAll($newIndex);

        // assign new index to alias
        $alias = get_option("elastic-posts_settings_index");
        $this->clearAndAssignAlias($newIndex, $alias);

        // delete old index
        foreach ($this->existingIndexes as $index) {
            $this->deleteIndex($index);
        }
    }

    /**
     * Get index settings
     * http://www.elasticsearch.org/guide/en/elasticsearch/client/php-api/current/_index_operations.html
     * @param  string $index Name of index to get settings for
     * @return array 
     */
    public function getSettingsForIndex($index)
    {
        try {
            return $this->elasticsearchClient->indices()->getSettings(array("index" => $index));
        } catch (\Exception $e) {
            return array();
        }
    }


    /**
     * Get existing indexes associated with an alias
     * @param  string $alias Alias name
     * @return array
     */
    public function getIndexesForAlias($alias)
    {
        $index = $this->getSettingsForIndex($alias);
        
        if (empty($index) || !is_array($index)) {
            return array();
        }

        return array_keys($index);
    }


    /**
     * Update index aliases
     *
     * http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/indices-aliases.html
     * http://www.elasticsearch.org/guide/en/elasticsearch/client/php-api/current/_namespaces.html
     * 
     * $changes should look like:
     * 
     * array(
     *     'add' => array(
     *         'index' => 'myindex',
     *         'alias' => 'myalias'
     *     ),
     *     'add' => array(
     *     
     *     ),
     *     'remove' => array(
     *     
     *     )
     * )
     * 
     * @param  [array] $changes see above
     * @return 
     */
    public function updateAliases($changes)
    {
        $params = array('body' => array(
            'actions' => $changes
        ));

        return $this->elasticsearchClient->indices()->updateAliases($params);
    }


    /**
     * Get existing indexes attached to an alias, clear them,
     * and then assign alias to the passed in index
     *
     * http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/indices-aliases.html
     * 
     * @param  string $newIndex Name of the new index
     * @param  string $alias    Name of the alias to be assigned
     * @return
     */
    public function clearAndAssignAlias($newIndex, $alias)
    {
        // Get existing indexes attached to 'jhu' alias
        $this->existingIndexes = $this->getIndexesForAlias($alias);

        // Create remove list to later remove these indices
        $changes = array();
        $changes = array_map(function ($index) use ($alias) {
            return array("remove" => array(
                "index" => $index,
                "alias" => $alias
            ));
        }, $this->existingIndexes);

        // Add jhu alias to new index
        $changes[] = array("add" => array("index" => $newIndex, "alias" => $alias));

        return $this->updateAliases($changes);
    }


    /**
     * Create new index with settings in $this->settingsDirectory
     * @return string Name of the new index (useful for aliasing)
     */
    public function createIndex()
    {
        $newIndex = "jhu_" . time();
        $indexParams = array(
            "index" => $newIndex,
            "body" => json_decode(file_get_contents($this->settingsDirectory . "/settings.json"), true)
        );

        $mappings = $this->settingsDirectory . "/mappings";

        $files = array_diff(scandir($mappings), array("..", ".", ".DS_Store"));
        foreach ($files as $file) {
            $indexParams["body"]["mappings"][str_replace(".json", "", $file)] = json_decode(file_get_contents($mappings . "/" . $file), true);
        }

        $this->elasticsearchClient->indices()->create($indexParams);

        return $newIndex;
    }

    public function deleteIndex($index)
    {
        return $this->elasticsearchClient->indices()->delete(array(
            "index" => $index
        ));
    }
}