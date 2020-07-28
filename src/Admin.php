<?php

namespace ElasticPosts;

class Admin
{
  public function __construct()
  {
    add_action("admin_menu", function () {

      add_submenu_page(
        "tools.php",
        "Elastic Posts Options",
        "Elastic Posts",
        "activate_plugins",
        "elastic-posts",
        function () {

          $content = '<p>Please reindex <em>only</em> if you know what you\'re doing.';
          $content .= '<form method="post" action="' . admin_url("admin-post.php") . '">';
          $content .= '<input type="hidden" name="action" value="wp_elastic_posts_reindex">';
          $content .= '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Reindex" /></p>';
          $content .= '</form>';

          ?>

          <div class="wrap">

    	        <h2>Elastic Posts Options</h2>
    	        <?php echo $content; ?>

    	    </div>

          <?php
        }
      );

    });
  }
}
