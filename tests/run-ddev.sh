#!/usr/bin/env bash
# Run from the application's Composer root inside DDEV's web container.
set -euo pipefail
export SIMPLETEST_BASE_URL="${SIMPLETEST_BASE_URL:-http://web}"
export SIMPLETEST_DB="${SIMPLETEST_DB:-mysql://db:db@db/db}"
export BROWSERTEST_OUTPUT_DIRECTORY="${BROWSERTEST_OUTPUT_DIRECTORY:-/tmp/damopen-browser-output}"
if [[ -z "${MINK_DRIVER_ARGS_WEBDRIVER:-}" ]]; then
  export MINK_DRIVER_ARGS_WEBDRIVER='["chrome",{"goog:chromeOptions":{"w3c":true,"args":["--headless=new","--no-sandbox","--disable-dev-shm-usage"]}},"http://selenium:4444/wd/hub"]'
fi
mkdir -p "$BROWSERTEST_OUTPUT_DIRECTORY"
exec vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  --log-junit "$BROWSERTEST_OUTPUT_DIRECTORY/results.xml" \
  "${@:-web/profiles/contrib/damopen/modules/damopen_assets/tests/src/FunctionalJavascript/BulkImageUploadTest.php}"
