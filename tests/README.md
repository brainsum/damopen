# Automated tests

The project has five tests:

- `MediaEntityNormalizerTest` (unit): media-only serialization support and return
  contract under Drupal 11.
- `LoadTest` (functional): fresh profile installation, image creation, multiple
  library cards with edit/delete links, and byte-for-byte private downloads.
- `BulkImageUploadTest` (JavaScript/browser): the real multi-image upload flow.
- `AssetRejectionTest` (kernel): rejecting assets, the uploader's email,
  clearing a rejection on approval or resubmission, and reviewer-only access.
- `AssetRejectionWorkflowTest` (JavaScript/browser): declining with a reason in
  the approval widget, the uploader's view of it, and resubmitting. It needs a
  `damo_theme` build that includes the rejection dialog.

## Run the upload test locally

The enclosing DDEV project needs a dedicated Selenium Chrome service and a
`test-damopen` command. Reusable templates are included under `tests/ddev`.
For the standard DAMopen project layout, copy them from the project root:

```sh
mkdir -p .ddev/commands/web
cp app/web/profiles/contrib/damopen/tests/ddev/docker-compose.selenium.yaml .ddev/
cp app/web/profiles/contrib/damopen/tests/ddev/test-damopen .ddev/commands/web/
ddev restart
```

Then run:

```sh
ddev start
ddev test-damopen
```

The upload test installs DAMopen in isolated prefixed database tables and uses
test-only file directories. It does not upload into the normal local site.
Chrome receives two different fixture images through Dropzone's actual file
input. The test waits for both asynchronous uploads, submits DAMopen's name and
category fields, and verifies:

- The success message and exactly two media entities.
- The uploader and chosen category on each entity.
- Permanent private files whose hashes match the original fixtures.
- Both assets in the searchable library with working edit/delete link markup.
- No JavaScript console errors or unexpected-error page.

Search indexing is triggered explicitly after submission instead of waiting for
cron. Library titles can be hidden until hover, so their presence is checked in
the rendered HTML. The test currently exercises an administrator; restricted
roles, rejected file types, large files and interrupted uploads are not covered.

The test exposed a form-factory bug: calling the contributed `create()` method
returned the base form (`new self()`), omitting DAMopen's custom fields and submit
handler. DAMopen now instantiates its own subclass with the required services.

## Other tests

```sh
ddev test-damopen web/profiles/contrib/damopen/modules/media_collection/tests/src/Functional/LoadTest.php
ddev test-damopen web/profiles/contrib/damopen/modules/damopen_assets_api/tests/src/Unit/MediaEntityNormalizerTest.php
```

The underlying runner is `tests/run-ddev.sh`, executed from the application's
Composer root inside the web container. Its base URL, test database, WebDriver
URL/capabilities and output directory can be overridden with
`SIMPLETEST_BASE_URL`, `SIMPLETEST_DB`, `MINK_DRIVER_ARGS_WEBDRIVER` and
`BROWSERTEST_OUTPUT_DIRECTORY`. It writes JUnit results to
`/tmp/damopen-browser-output/results.xml` by default. Drupal also prints paths to
HTML browser artifacts for debugging.

Always use a development/test database with permission to create isolated test
tables. The JavaScript test needs a Selenium-compatible Chrome server reachable
at `http://selenium:4444/wd/hub`; the browser must be able to reach `http://web`
and read the fixtures at the same `/var/www/html` paths as the PHP container.
The enclosing DDEV service mounts the project read-only into Selenium for this.
These tests are locally runnable; no CI workflow is configured yet.

Existing contributed-code deprecations remain visible in test output. Full
configuration-schema checking is excluded from the profile-level tests because
legacy contributed settings lack schemas; PHP/JavaScript failures and failed
assertions still fail the tests.
