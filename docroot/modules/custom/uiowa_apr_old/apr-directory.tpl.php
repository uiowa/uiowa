<?php

/**
 * @file
 * Default theme implementation for APR directory.
 */
?>

<?php if(!is_null($msg)): ?>
  <div id="apr-error-message">
    <?php print($msg); ?>
  </div>
  <script lang="js">
    setTimeout(function() {
      let element = document.getElementById('apr-error-message');
      element.style.display = 'none';
    }, 15000);
  </script>
<?php endif; ?>

<div id="apr-directory-service" role="region" aria-live="polite" aria-label="People Directory">
    <apr-directory
        api-key="<?php print($api_key); ?>"
        title="<?php print($directory_title); ?>"
        title-selector="<?php print($title_selector); ?>"
        :page-size="<?php print($page_size); ?>"
        :show-title="<?php print($show_title); ?>"
        :show-switcher="<?php print($show_switcher); ?>"
        <?php if(!is_null($slug)): ?>
        slug="<?php print($slug); ?>"
        <?php endif; ?>
        <?php if(!is_null($section)): ?>
        section="<?php print($section); ?>"
        <?php endif; ?>
    >
        <template v-slot:introduction>
            <?php print($directory_intro_filtered) ?>
        </template>

        <?php if(!is_null($slug)): ?>
        <?php print($profile_html); ?>
        <?php endif; ?>
    </apr-directory>
</div>
<script src="<?php print($service_url); ?>/aprDirectory.js"></script>

<?php foreach($custom_styles as $custom_style): ?>
    <link rel="stylesheet" href="<?php print($custom_style)?>">
<?php endforeach; ?>
