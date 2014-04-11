<?php

ini_set('display_errors',1); 
error_reporting(E_ALL);

$root = dirname(dirname(dirname(dirname(__DIR__))));

// require $root . "/vendor/autoload.php";

// fire up the beast
define("WP_USE_THEMES", false);
require $root . "/vendor/wordpress/wordpress/wp-blog-header.php";

$es = new \ElasticPosts\Elasticsearch(array(
	"settings_directory" => $root . "/config/elasticsearch/jhuedu"
));

// $newIndex = $es->createIndex();
// $es->clearAndAssignAlias($newIndex, "jhu");