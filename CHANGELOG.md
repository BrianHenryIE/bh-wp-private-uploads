# Changelog

## Unreleased

### Changed

* All hooks now have static names and are passed the plugin slug and post type name as their final two arguments, so a single callback can serve (or ignore) each instance: `bh_wp_private_uploads_allow`, `bh_wp_private_uploads_url_is_public_warning`, `bh_wp_private_uploads_rest_upload`.

### Deprecated

* `bh_wp_private_uploads_{$post_type_name}_allow` – use `bh_wp_private_uploads_allow`.
* `bh_wp_private_uploads_url_is_public_warning_{$post_type_name}` – use `bh_wp_private_uploads_url_is_public_warning`.
* `rest_private_uploads_upload` – use `bh_wp_private_uploads_rest_upload`. The settings object is no longer passed; the plugin slug and post type name are passed instead.

  The deprecated hooks still fire, and trigger a deprecation notice when hooked (when `WP_DEBUG` is enabled). They will be removed in a future release.

### Fixed

* The `API` class no longer requires the `upload_files` capability, so uploads succeed on cron and WP-CLI (where there is no logged-in user). Authorization remains enforced at each request boundary. Consumer plugins can veto uploads via the new `bh_wp_private_uploads_can_upload` filter, which is passed the plugin slug and post type name.
