This directory serves as a workaround for the issue documented
here https://github.com/cweagans/composer-patches/issues/178.

Since drupal/core is a subtree split of drupal/drupal the git diff
patches do not apply correctly. Simply replace any occurence of "a/core"
and "b/core" within the patch file to just "a/" and "b/", respectively.