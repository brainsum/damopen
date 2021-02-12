# DAMopen - Distribution

Drupal 9 distribution to kickstart Digital Assets Management projects.

## Installation

Using the [DAMopen composer project](https://github.com/brainsum/damopen-project) is recommended.

## User roles

* **Media API** user is for the API users, e.g. for an interconnected Drupal system where the https://www.drupal.org/project/filefield_sources_jsonapi module is installed.
* **Agency** is for external users like graphic designers/agencies, photographers. They can just upload media assets for approval, which means a Manager will need to approve (publish) them first before they appear in the DAM library.
* **Authenticated user** is for simple "read-only" users, they can browse, search, view, download assets.
* **Manager** is the highest level regarding the DAM functionality. They can fully manage the content of the DAM.
* **Administrator** is like a superuser who can manage users, but also the full settings of the site. Give it only to people who know Drupal.
