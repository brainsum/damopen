# DAMopen 4.0.x on Drupal 11

This branch requires Drupal 11.3 or later and PHP 8.3 or later. The local
upgrade was tested with Drupal 11.4.7, MariaDB 10.11, and Drush 13.
Upgrade existing sites from Drupal 10.3 or later with all database updates
completed. Back up both the database and uploaded/private files first.

## Project Composer configuration

The application root must require these versions (and update the lock file):

- `brainsum/damopen`: `4.0.x-dev`
- `drupal/core-recommended`, `drupal/core-composer-scaffold`, and development
  dependency `drupal/core-dev`: `^11.3`
- `drupal/video_embed_field`: `^3.0` (remove the previous 2.5.0 pin)
- `drupal/adminimal_admin_toolbar`: `^2.0`, if used
- `drupal/devel_entity_updates`: `^5.0`, if used

Three dependencies still declare only older Drupal core versions. This branch
provides patches for their Drupal 11 metadata and identified runtime problems.
Configure the application root before resolving the upgrade:

```json
{
  "config": {
    "allow-plugins": {
      "mglaman/composer-drupal-lenient": true,
      "symfony/runtime": true
    }
  },
  "extra": {
    "enable-patching": true,
    "composer-exit-on-patch-failure": true,
    "drupal-lenient": {
      "allowed-list": [
        "brainsum/damo_theme",
        "drupal/php_ffmpeg",
        "drupal/switch_page_theme"
      ]
    }
  }
}
```

Merge these keys into the existing configuration; retain the other approved
Composer plugins. Install `mglaman/composer-drupal-lenient:^1.0` on Drupal 10
before changing the core constraints so it is active during dependency resolution.
The allow-list is deliberately limited to the three patched packages.

The patches in `composer.json` assume the standard project layout:
`web/profiles/contrib/damopen`. Applications with a different installer path must
adjust these paths in their root patch configuration. Keep patch failures fatal.

Run from the application directory:

```sh
composer update --with-all-dependencies
vendor/bin/drush updatedb -y
vendor/bin/drush cache:rebuild
vendor/bin/drush updatedb:status
composer check-platform-reqs
composer audit
```

The profile carries a rebased details-element patch for Drupal 11.3+'s
`FormPreprocess` class. The media normalizer uses Drupal 11's `array` return
contract and `getSupportedTypes()` to avoid intercepting unrelated entities.
The theme patch removes dynamic properties on Drupal's `Attribute` object;
the theme-switching patch injects typed configuration and replaces the removed
`user_role_names()` function.

## Development checkout

The local project uses a Composer path repository pointing at `packages/damopen`,
with `symlink: true`, `reference: config`, and version `4.0.x-dev`. Make profile
changes in that Git checkout on branch `4.0.x`. Run
`composer update brainsum/damopen` after changing its Composer metadata.
After changing a patch's contents, reinstall its target package with
`composer reinstall <package>` so the new patch is applied.

## Tests

From the application's Composer root:

```sh
vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/profiles/contrib/damopen/modules/damopen_assets_api/tests/src/Unit/MediaEntityNormalizerTest.php
SIMPLETEST_BASE_URL=http://localhost SIMPLETEST_DB=mysql://db:db@db/db \
  BROWSERTEST_OUTPUT_DIRECTORY=/tmp vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/profiles/contrib/damopen/modules/media_collection/tests/src/Functional/LoadTest.php
```

Use a local test database. BrowserTestBase creates isolated prefixed test tables.
The functional test installs the complete DAMopen profile and renders its home
page with the Damo theme, creates an image asset, renders it, and verifies its
private download byte for byte as an administrator. It does not enforce full configuration schemas because
existing contributed settings, including media_upload and Bootstrap, lack schema
coverage. Schema completeness and Drupal 12 deprecation cleanup remain separate
follow-ups; they are not evidence of tested Drupal 12 compatibility.

## Verified result (2026-09-16)

- Existing site upgraded from Drupal 10.6.17 to 11.4.7; no pending database updates.
- Normalizer unit test: 1 test, 4 assertions passed.
- Fresh-profile functional test: 1 test, 12 assertions passed; 191 deprecations
  reported across core/contributed code. Full config schema checking is excluded
  as described above.
- Twelve authenticated HTTP checks returned 200, covering assets, collections,
  upload and media creation forms, settings, and JSON:API. A subsequent JavaScript browser test covers a two-image upload (see below).
  Non-administrator permission combinations were not exhaustively tested.
- Composer validation and platform requirements passed. Audit reported no
  vulnerability advisories, but exits nonzero because existing packages
  `drupal-composer/drupal-security-advisories` and
  `oomphinc/composer-installers-extender` are marked abandoned.

References: [Drupal 10 to 11 upgrade guide](https://www.drupal.org/docs/upgrading-drupal/upgrading-drupal/how-to-upgrade-from-drupal-10-to-drupal-11)
and [scoped Composer leniency](https://www.drupal.org/docs/develop/using-composer/using-the-drupal-lenient-composer-plugin).

## Populated-library regression fix

After multiple images were uploaded, the library failed because Damo's icon-only
edit/delete links passed `NULL` as link text. Drupal 11's link generator requires
a string. The theme compatibility patch now uses an empty string and adds explicit
accessible labels while retaining the icons and access checks. The functional
test renders two media-library cards: it reproduced the exact `Html::escape()`
TypeError before the fix and passes afterward. The local populated library also
returned HTTP 200 with edit/delete controls for all three stored images.

## Real-browser upload coverage

`BulkImageUploadTest` now covers actual Dropzone requests and DAMopen's bulk-image
form, saved media metadata and private file contents, and the populated library.
It passes in headless Chrome: 1 test, 20 assertions, with existing contributed
code deprecations reported. The test also exposed and fixed the custom form
factory returning the contributed base form. Run `ddev test-damopen` from the
project root; see [tests/README.md](tests/README.md) for details.
