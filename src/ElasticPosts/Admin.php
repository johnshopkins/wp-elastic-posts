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

        $this->createBoxSection();
        $this->createIndexSection();
        $this->createPostTypesSection();
    }

    protected function createIndexSection()
    {
        $this->postTypesSection = new \WPUtilities\Admin\Settings\Section(
            $this->menuPage,
            "index",
            "Index"
        );

        $fields = array(
            "index" => array(
                "type" => "text",
                "label" => "Index Name"
            )
        );
        $this->createFields($fields);
    }

    protected function createBoxSection()
    {
        $this->postTypesSection = new \WPUtilities\Admin\Settings\Section(
            $this->menuPage,
            "box",
            "Box"
        );

        $fields = array(
            "box" => array(
                "type" => "text",
                "label" => "Which box to connect to?"
            )
        );
        $this->createFields($fields);
    }

    protected function createPostTypesSection()
    {
        $this->postTypesSection = new \WPUtilities\Admin\Settings\Section(
            $this->menuPage,
            "post_types",
            "Post types in import"
        );

        $fields = $this->getPostTypeFields();
        $this->createFields($fields);
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

        return array(

            "post_types" => array(
                "label" => "Post types to import",
                "fields" => $fields
            )
        );
    }

    protected function createFields($fields)
    {
        foreach ($fields as $machinename => $details) {

            $validation = isset($details["validation"]) ? $details["validation"] : null;

            // this field has subfields
            if (isset($details["fields"])) {
                new \WPUtilities\Admin\Settings\FieldGroup(
                    $machinename,
                    $details["label"],
                    $details["fields"],
                    $this->menuPage,
                    $this->postTypesSection,
                    $validation
                );
            } else {
                $default = isset($details["default"]) ? $details["default"] : null;
                new \WPUtilities\Admin\Settings\Field(
                    $details["type"],
                    $machinename,
                    $details["label"],
                    $default,
                    $this->menuPage,
                    $this->postTypesSection,
                    $validation
                );
            }
        }
    }
}