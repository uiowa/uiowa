<?php

/**
 * @file
 * Default theme implementation for APR publications.
 */
?>

<div id="apr-publication-service">
    <!-- To-do need to expose selected departments configuration -->
    <apr-publications
        api-key="<?php print($api_key);?>"
        profile-path="/<?php print($directory_path);?>"
        :page-size="<?php print($page_size);?>"
        <?php if(!empty($publications_depts)): ?>
        :departments="<?php print($publications_depts); ?>"
        <?php endif; ?>
    >

    </apr-publications>
</div>
<script src="<?php print($service_url);?>/aprPublications.js"></script>

<?php foreach($custom_styles as $custom_style):  ?>
    <link rel="stylesheet" href="<?php print($custom_style)?>">
<?php endforeach; ?>
