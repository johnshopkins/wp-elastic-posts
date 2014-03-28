<?php

namespace ElasticPosts;

class WordPressUtils
{
	public function __call($name, $arguments)
    {
        return call_user_func_array($name, $arguments);
    }

    /**
     * Get the post and its meta data
     * @param  integer $id      Post ID
     * @param  boolean #getMeta Whether to retrieve meta or not
     * @return object
     */
    public function getPost($id, $getMeta = true)
    {
    	$post = get_post($id);
    	if ($getMeta) {
    		$post->meta = $this->getMeta($id);
    	}

    	return $post;
    }

    /**
     * Get all posts of a given post type
     * @param  string $type Post type
     * @param  boolean #getMeta Whether to retrieve meta or not
     * @return array
     */
	public function getPosts($type, $getMeta = true)
	{
		$posts = get_posts(array(
			"posts_per_page" => -1,
			"post_type" => $type,
			"post_status" => "publish"
		));

		if ($getMeta) {
			foreach ($posts as $post) {
		    	$post->meta = $this->getMeta($post->ID);
			}
		}
		
		return $posts;
	}

	public function getMeta($id)
	{
		/**
		 * Advanced Custom Fields support
		 * 
		 * ACF post metadata is not created until the post is viewed
		 * and saved in the WP admin and saved. This causes an imported
		 * post's metadata retrieval to be different before its been
		 * viewed and saved in the admin (WP function) and after (ACF function)
		 */
		$meta = get_post_meta($id);

		if (function_exists("get_fields")) {

			if ($this->containesAcf($meta)) {
				// if ACF has initiated this form already
				$meta = get_fields($id);
			} else {
				// if ACF has not initaited this form already, we
				// need to try and detect repeater fields (the only ACF
				// field type that doesn't parse easily)
				$meta = $this->detectRepeater($meta);
			}

		}

		$cleaned = array();

		if (!empty($meta)) {

			foreach($meta as $k => &$v) {
				// ignore hidden meta and _import meta
				if (preg_match("/^_/", $k)) {
					continue;
				}

				if (is_array($v) && count($v) === 1 && !is_array($v[0])) {
					$v = $v[0];
				}

				$cleanerMethod = "clean__{$k}";
				if (method_exists($this, $cleanerMethod)) {
					$v = $this->$cleanerMethod($v);
				}

				$cleaned[$k] = $v;
			}

		}

		return $cleaned;
	}

	/**
	 * Checks to see if the set of meta contains
	 * tell-tale signs of ACF (keys with underscores
	 * and values with field_xxxxxx)
	 * 
	 * @param  integer $id Post ID
	 * @return array Meta
	 */
	protected function containesAcf($meta)
	{
		$containsAcf = false;

		foreach ($meta as $k => $v) {
			if (!preg_match("/^_/", $k)) {
				continue;
			}

			if (is_string($v) && preg_match("/^field_[A-Za-z0-9]./", $v)) {
				$containsAcf = true;
				break;
			}
		}

		return $containsAcf;
	}

	/**
	 * Find keys of a meta array whose value
	 * is an integer.
	 * @param  array $array
	 * @return array of keys
	 */
	protected function findIntegerValues($array)
	{
		$potentials = array();

		foreach ($array as $k => $v) {

			// don't look at hidden meta
			if (preg_match("/^_/", $k)) {
				continue;
			}

			$v = array_shift($v);
			if (is_numeric($v)) {
				$potentials[] = $k;
			}
		}

		return $potentials;
	}

	/**
	 * Find repeater fields and assign each subfield
	 * to the main repeater field. This will NOT find repeater
	 * fields that do not have any subfields filled in.
	 * @param  array $potentials Array of keys that coorespond to potential
	 *                           repeaters in $meta
	 * @param  array $meta       Meta data
	 * @return array Repeater fields ("field" => array("title", "link"))
	 */
	protected function findRepeaters($potentials, $meta)
	{
		$repeaters = array();

		foreach ($potentials as $key) {

			// loop through all fields and look for repeater-like keys
			foreach ($meta as $k => $v) {

				// look for meta with repeater-keys (like "field_0_link")
				$regex = "/^" . $key . "_\d+_(\w+)/";
				if (preg_match($regex, $k, $matches)) {

					// add the subfield to this key
					$repeaters[$key][] = $matches[1];
				}
			}

			// get rid of duplicate subfields
			if (!empty($repeaters[$key])) {
				$repeaters[$key] = array_unique($repeaters[$key]);
			}
			
		}

		return $repeaters;
	}

	/**
	 * Finds and formats ACF repeater fields without
	 * ACF's help. This function is run only if ACF
	 * hasn't initiated the post yet (imported
	 * fields of study)
	 *
	 * One major flaw: this function cannot detect
	 * repeater fields that have not had any subfields
	 * applied to them! This must be taken care of in
	 * the cleaner methods.
	 * 
	 * @param  array $meta
	 * @return array
	 */
	protected function detectRepeater($meta)
	{
		// find items that have an integer as the value
		$potentials = $this->findIntegerValues($meta);
		
		// find repeater subfields
		$repeaters = $this->findRepeaters($potentials, $meta);

		// craft an array that replicates what we would get
		// back from ACF's get_field()
		if (!empty($repeaters)) {

			foreach ($repeaters as $key => $subfields) {

				// total items in repeater
				$count = array_shift($meta[$key]);

				for ($i = 0; $i < $count; $i++) {
					
					$item = array();

					// gather subfields for this repeater items
					foreach ($subfields as $subfield) {
						$item[$subfield] = array_shift($meta["{$key}_{$i}_{$subfield}"]);
						unset($meta["{$key}_{$i}_{$subfield}"]);
					}

					$meta[$key][] = $item;

				}

			}

		}

		return $meta;
	}
}