<?php

namespace ElasticPosts;

class Admin
{
    protected $wordpress;

    protected $menuPage;
    protected $postTypesSection;

    public function __construct()
    {
        $this->wordpress = isset($args["wordpress"]) ? $args["wordpress"] : new \WPUtilities\WordPressWrapper();
        $this->createMenuPage();
    }

    protected function createMenuPage()
    {
        $this->menuPage = new \WPUtilities\Admin\Settings\SubMenuPage(
            "options-general.php",
            "Elastic Posts Options",
            "Elastic Posts",
            "activate_plugins",
            "elastic-posts"
        );

        $this->createSettingsSection();
    }

    protected function createSettingsSection()
    {
        $fields = array(
            "box" => array(
                "type" => "text",
                "label" => "Which box to connect to?"
            ),
            "index" => array(
                "type" => "text",
                "label" => "Index Name"
            ),
            "post_types" => array(
                "label" => "Post types to import",
                "fields" => $this->getPostTypeFields()
            )
        );

        $this->postTypesSection = new \WPUtilities\Admin\Settings\Section(
            $this->menuPage,
            "settings",
            "Settings",
            $fields
        );
    }

    protected function getPostTypeFields()
    {
        $posttypes = $this->wordpress->get_post_types(array("public" => true), "objects");

        $fields = array();

        foreach($posttypes as $posttype) {
            $fields[$posttype->name] = array(
                "type" => "checkbox",
                "name" => $posttype->name,
                "label" => $posttype->label
            );
        }

        return $fields;
    }

}