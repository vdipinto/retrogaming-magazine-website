<?php
return [
  'title'       => __( 'Top Stories Layout – 3 Columns', 'top-games' ),
  'slug'        => 'top-stories-3col',
  'description' => __( 'Large feature left, two stacked center, sidebar right.', 'top-games' ),
  'categories'  => [ 'top-games', 'featured' ],
  'content'     => trim(<<<'HTML'
<!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide">

  <!-- wp:column {"width":"50%"} -->
  <div class="wp-block-column" style="flex-basis:50%">
    <!-- wp:top-games/top-games {"showThumbs":true,"items":1,"titleSize":"xl"} /-->
  </div>
  <!-- /wp:column -->

  <!-- wp:column {"width":"25%"} -->
  <div class="wp-block-column" style="flex-basis:25%">
    <!-- wp:top-games/top-games {"showThumbs":true,"items":2} /-->
  </div>
  <!-- /wp:column -->

  <!-- wp:column {"width":"25%"} -->
  <div class="wp-block-column" style="flex-basis:25%">
    <!-- wp:top-games/top-games {"showThumbs":false,"items":3,"headingText":"Top Stories"} /-->
  </div>
  <!-- /wp:column -->

</div>
<!-- /wp:columns -->
HTML),
];
