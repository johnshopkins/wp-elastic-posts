<?php

namespace ElasticPosts\PostTypes;

class field_of_study extends Post
{
	protected $acfRepeaters = array(
		"concentrations",
		"majors",
		"minors",
		"social_media",
	);
	public function clean($post)
	{
		// basis of object
		$clean = $post->meta;
		$clean["title"] = $post->post_title;
		$clean["published_date"] = $post->post_date_gmt;
		$clean["modified_date"] = $post->post_modified_gmt;

		// if an ACF form hasn't been initiated yet, these
		// fields won't exist. they need to run through
		// the cleaners because elasticsearch is expecting
		// an array.
		foreach ($this->acfRepeaters as $key) {
			if (!isset($clean[$key])) {
				$clean[$key] = array();
			}
		}
		
		return parent::runCleaners($clean, $post->ID);
	}

	/**
	 * For ACF repeater fields that only have one field
	 * per subfield, we just want to extract the value
	 * of that field and place it in an array.
	 * @param  array $value ACF repeater field value
	 * @return array
	 */
	protected function extractField($value, $field)
	{
		// one
		if (isset($value[$field])) {
			return array($value[$field]);
		}

		// many
		return array_map(function ($v) use ($field) {
			return $v[$field];
		}, $value);
	}

	protected function clean__concentrations($value, $post_id)
	{
		if (is_array($value)) {
			return $this->extractField($value, "title");
		} else {
			return array();
		}
	}

	protected function clean__majors($value, $post_id)
	{
		if (is_array($value)) {
			$this->extractField($value, "title");
		} else {
			return array();
		}
	}

	protected function clean__minors($value, $post_id)
	{
		if (is_array($value)) {
			return $this->extractField($value, "title");
		} else {
			return array();
		}
	}

	protected function clean__social_media($value, $post_id)
	{
		if (is_array($value)) {
			return $value;
		} else {
			return array();
		}
	}
}