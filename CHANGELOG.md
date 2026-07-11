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
* `API::move_file_to_private_uploads()` now removes its `upload_dir` filter and restores `$_POST['action']` in a `finally` block, so a failed `wp_handle_upload()` no longer leaves the private-directory filter attached for the rest of the request.
* The "private uploads directory is publicly accessible" admin notice now un-snoozes a week after dismissal. `Admin_Notices::on_dismiss()` is now actually hooked (on `add_option_`/`update_option_` for the dismissal option), and the option name is computed by a single shared helper (`Cron::get_dismissed_notice_option_name()`) that matches the name the wp-trt/admin-notices library stores it under – the previous inline name never matched, so the notice could be dismissed forever.
* The private uploads directory path/URL and the rewrite rule are now derived from `wp_upload_dir()` instead of hard-coded `WP_CONTENT_DIR`/`WP_CONTENT_URL`, fixing directory creation, the public-URL check, and the rewrite rule on multisite (per-site upload directories) and relocated-uploads installs.
* `Serve_Private_File` fixes: the ETag `If-None-Match` comparison now strips surrounding quotes and an optional `W/` weak prefix (the 304 branch previously never fired); logged-out visitors are redirected to wp-login instead of receiving a bare 403; `Cache-Control: private, max-age=3600` and `Content-Length` headers are sent. The decision logic was extracted into a testable pure method (first tests for this class).

### Changed

* `Serve_Private_File` now grants access with the `manage_options` capability rather than the `administrator` role name, so multisite super admins without an explicit site role are covered (potential behavior change for sites relying on the exact role check).
