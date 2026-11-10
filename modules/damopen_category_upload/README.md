# Category Library and uploads

Adds a hierarchical Library, category-specific image upload links, and an
administrator-managed **Upload category** field on user profiles.

## Local demo

- Browse: `/library` (also linked from the main menu).
- Manage terms: `/admin/structure/taxonomy/manage/category/overview`.
- Find/copy upload links: `/admin/content/category-upload-links`.
- Assign a user: People → Edit → **Upload category**.
- Example on the SNV local import: `/upload-image?category=24` selects
  Countries / Asia / Bangladesh. IDs are local; use the link list on other sites.

An assigned user can upload into that category and its descendants, including
multiple allowed categories. Parent categories, sibling branches and empty
category submissions are rejected. An unassigned user keeps their existing
upload permissions without a category restriction. Administrators bypass the
restriction. This controls placement, not viewing or approval permissions.
If an assigned category is deleted or unpublished, the user stays restricted and
cannot upload; the upload pages explain that an administrator must update it,
including when opened through an upload link.

A category link fixes the batch to that exact category. It requires a signed-in
user with image creation and upload permission; possession of the link grants
no access. Both the React uploader and `/media/bulk-upload/image?category=ID`
apply the preset to every image. Ordinary uploads default to the assigned root.
On other pages `?category=` keeps its usual meaning (for example a Views filter).
Native media forms use full category paths and show allowed choices. Categories
already on an asset stay listed; for a restricted user, those outside their
branch are locked, so editing metadata never removes them.

Server validation and presave checks cover new media, category changes and
source-file replacement, including JSON:API, where scope errors are 422
validation errors. Metadata-only edits and approvals of existing assets retain
existing permissions. A restricted user changing categories may add categories
in their branch, but may not add or remove others. Only users with **Administer
upload category assignments and library structure** may change assignments or
edit/delete/create Category terms, preventing hierarchy-based scope bypasses.
This is enforced through entity and field access (forms and JSON:API), so
Drush, cron, migrations and other programmatic saves are not blocked. The same
permission is required for Term Merge on Category (merging rewrites every
reference, including assignments), for drag-and-drop reordering and weight
resets, and for reverting or deleting category term revisions. Managers keep
Term Merge for other vocabularies.
Managers' existing `administer users` permission does not grant this permission;
grant it explicitly if Managers should keep editing the Category vocabulary.
Do not grant either new restricted permission to Agency or Manager roles.

The Library includes empty categories. Selecting a branch shows published assets
from its descendants, subject to media view access. Existing approval rules are
unchanged. Other structured metadata (Country, Project, Sector, Topic) is not
derived from Category.

## Installation and SNV hierarchy

Requires Drupal 11.2 or later.

From the application Composer root:

```sh
drush en damopen_category_upload -y
drush php:script packages/damopen/scripts/import-snv-library.php
drush cr
```

The explicit import uses `scripts/snv-library.json`, transcribed from the
verified Canto Library inventory on 9 November 2026. It creates 207 terms with
Canto source IDs, folder/album types, exact parents and sibling ordering. It
matches by source ID, never name, and is transactional and repeatable. Duplicate
labels and folder/album pairs remain distinct. Re-running updates imported
structure to match the manifest; unrelated demo terms and assets are untouched.
No files or Canto asset memberships are migrated by this script.

The local checkpoint before installation is `pre-snv-categories-20261110`.
Restoring that snapshot also rolls back subsequent local database changes; pair
it with the corresponding code state. Uninstalling the feature is not a taxonomy
rollback: do not delete imported terms if assets have subsequently referenced them.

## Tests

```sh
ddev test-damopen web/profiles/contrib/damopen/modules/damopen_category_upload/tests/src/Kernel/CategoryUploadTest.php
ddev test-damopen web/profiles/contrib/damopen/modules/damopen_category_upload/tests/src/FunctionalJavascript/CategoryUploadWorkflowTest.php
```

Tests use isolated tables and files. The kernel tests cover subtree scope,
administrator bypass, mixed/disallowed/empty categories, presets, reassignment,
metadata-only review, editing assets filed in other branches, unavailable
assignments (including upload links), Term Merge and reordering access, upload routes for `?category=`, assignment protection and
hierarchy access. Browser tests cover both real two-image upload paths,
renaming several files without a link, a restricted reviewer editing an asset
filed in another branch, a restricted Manager denied Term Merge and weight
reset with their assignment unchanged, guidance on upload links for an
unavailable assignment, and read-only staff access.

The React changes live in the `damo-theme` checkout as well as the installed
local theme. Build the `apps/damo_upload` workspace and deploy its generated
assets together with the PHP module; no external npm packages were added.
