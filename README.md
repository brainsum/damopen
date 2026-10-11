# DAMopen - Distribution

Drupal 11 distribution to kickstart Digital Assets Management projects.

The `4.0.x` branch requires Drupal 11.3 or later and PHP 8.3 or later.

## Installation

Using the [DAMopen composer project](https://github.com/brainsum/damopen-project) is recommended.

Follow the [Drupal 11 installation and upgrade notes](DRUPAL11.md) to configure
the project's dependencies and required compatibility patches before installing
this branch or upgrading an existing site.

## Automated tests

Tests cover media serialization, asset rendering and downloads, and multi-image
uploads in a real browser. See the [test guide](tests/README.md) for setup and
commands.

## User roles

* **Media API** user is for the API users, e.g. for an interconnected Drupal system where the https://www.drupal.org/project/filefield_sources_jsonapi module is installed.
* **Agency** is for external users like graphic designers/agencies, photographers. They can just upload media assets for approval, which means a Manager will need to approve (publish) them first before they appear in the DAM library. A Manager can instead decline an asset with an optional reason: the Agency user is emailed, sees the reason under their assets waiting for approval, and can edit the asset to send it for approval again.
* **Authenticated user** is for simple "read-only" users, they can browse, search, view, download assets.
* **Manager** is the highest level regarding the DAM functionality. They can fully manage the content of the DAM.
* **Administrator** is like a superuser who can manage users, but also the full settings of the site. Give it only to people who know Drupal.
