# Changelog

## Unreleased

### Changed

* All hooks now have static names and are passed the plugin slug and post type name as their final two arguments, so a single callback can serve (or ignore) each instance: `bh_wp_private_uploads_allow`, `bh_wp_private_uploads_url_is_public_warning`, `bh_wp_private_uploads_rest_upload`.
* `Serve_Private_File` now grants access with the `manage_options` capability rather than the `administrator` role name, so multisite super admins without an explicit site role are covered (potential behavior change for sites relying on the exact role check).

### Removed

* `API::is_url_public_for_admin()`, which was never called and forwarded raw `$_COOKIE` values, along with the `Example_Private_Uploads` wrapper that was its only demonstration. It was `protected` on a class designed for extension, so a subclassing consumer could in principle have called it.

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

* The rewrite rule protecting the private directory is now written to the site-root `.htaccess`. Nothing ever flushed the rules before, so on Apache the directory stayed publicly readable until the user happened to re-save their permalinks. On each admin page load the `.htaccess` is read and the rules are flushed if the rule is missing — so it is also written when the plugin is activated over WP-CLI (where a plugin's own hard flush cannot write the file, because `save_mod_rewrite_rules()` is only defined for admin requests), and restored if another plugin's flush ever drops it.
* `create_directory()` now writes an `index.php` guard file, preventing directory listing where the server has autoindex enabled. It is also run lazily from `move_file_to_private_uploads()`, so cron/WP-CLI uploads still create the directory. (No deny-all `.htaccess`: Apache would 403 it in the auth phase, before the fixup phase where the rewrite rule runs, locking authorised users out of their own files.)
* The "directory is publicly accessible" check now probes a real uploaded file, descending into the `yyyy/mm` subdirectory. It previously probed whichever entry `scandir()` returned first — in practice the year *directory*, which any server with autoindex off returns 403 for — and read that as "private", so the admin notice could never fire. Dotfiles and the `index.php` guard file are skipped for the same reason: servers refuse them whether or not the documents beside them are public.
* New end-to-end tests assert that an admin can download a private file from its direct URL, while an anonymous visitor and a logged-in subscriber cannot.
* The REST `upload_item` route (upload a file without creating a post) is now reachable. It was registered at `{namespace}/{rest_base}/`, but `register_rest_route()` trims the trailing slash, giving it the same path as the collection route – both `CREATABLE`, so `create_item` won on dispatch and `upload_item` could never run. It now registers at `{namespace}/{rest_base}/upload`.

### Tests

* Added test coverage for previously-untested classes: `Post_Type`, `REST_Private_Uploads_Controller` and `Admin_Meta_Boxes`, plus `Media::on_upload_attachment()`'s nonce and referer branches.
