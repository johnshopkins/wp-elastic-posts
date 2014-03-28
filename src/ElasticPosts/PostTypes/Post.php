<?php

namespace ElasticPosts\PostTypes;

class post
{
	public function clean($post)
	{
		// basis of object
		$clean = $post->meta;
		$clean["title"] = $post->post_title;
		$clean["content"] = $post->post_content;
		$clean["published_date"] = $post->post_date_gmt;
		$clean["modified_date"] = $post->post_modified_gmt;

		return $this->runCleaners($clean, $post->ID);
	}

	protected function runCleaners($post, $id)
	{
		foreach ($post as $k => $v) {
			$cleanerMethod = "clean__{$k}";
			if (method_exists($this, $cleanerMethod)) {
				$v = $this->$cleanerMethod($v, $id);
			}

			$post[$k] = $v;
		}

		return $post;
	}
}