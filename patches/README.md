# Patches

All modifications to contributed projects and most modifications to Drupal core must be performed via patches.

## Applying patches

Patches are declared in `composer.json` under `extra.patches`, keyed by package. [cweagans/composer-patches](https://github.com/cweagans/composer-patches) applies them on every build.

    "extra": {
      "patches": {
        "drupal/core": {
          "[2985199] Extensions in multisite directories not registered when rebuilding cache": "https://www.drupal.org/files/issues/2024-08-01/2985199-98.patch",
          "[3428235] Drupal 11 compatibility": "patches/3428235.patch"
        }
      }
    },

Pin a patched package to a specific version so a later update cannot pull in a release the patch no longer applies to.

After editing `extra.patches`, refresh the lock file. Which command depends on what changed:

- Package version changed too: `composer update drupal/core`, naming the patched package. Bare `composer update` also works but updates every dependency.
- Patches section only: `composer update --lock`. This rewrites the `content-hash` that editing `composer.json` invalidated. CI fails on a mismatch, and `composer install` will not catch it locally because the patch still applies.

Commit `composer.json` and `composer.lock` together, along with the patch file when it is a local copy.

## Storing patches

Patches come from upstream issues on drupal.org. Prefer finding or filing an issue there over writing a local-only fix.

When the issue has a patch file under `drupal.org/files/issues/`, reference that URL directly in `composer.json`. When the fix exists only as a merge request, there is no stable file to link, so download it and commit the copy to this directory.

## Gotchas

Note that Composer can only patch files that are distributed with Composer packages. This means that certain files (such as the Drupal core `.htaccess` and `robots.txt`) cannot be easily patched via Composer. These files are not included in the Drupal core Composer package (in fact Drupal Scaffold individually creates these files on updates).

In order to modify `.htaccess` and other unpatchable root files, simply modify the file in place, commit it to Git, and make the following change in `composer.json`:

    "extra": {
      "drupal-scaffold": {
        "excludes": [
          ".htaccess"
        ]
      }
    },

The downside here is that you will need to apply drupal core udpates to these excluded files on your own.

Alternatively, you could leverage the `post-drupal-scaffold-cmd` script hook to apply patches after Drupal Scaffold is finished. See [this cweagens/composer-patches issue](https://github.com/acquia/blt/issues/1135#issuecomment-285404408) for more details.



Also note that there’s currently a quirk in the Drupal packaging system that makes it difficult to patch module and theme `.info.yml` files. If you have trouble applying a patch that modifies an info file, see this issue for a description and workaround: https://www.drupal.org/node/2858245
