# Changelog

## Unreleased

### Fixed

* The `API` class no longer requires the `upload_files` capability, so uploads succeed on cron and WP-CLI (where there is no logged-in user). Authorization remains enforced at each request boundary. Consumer plugins can veto uploads via the new `bh_wp_private_uploads_{post_type}_can_upload` filter.
* `API::move_file_to_private_uploads()` now removes its `upload_dir` filter and restores `$_POST['action']` in a `finally` block, so a failed `wp_handle_upload()` no longer leaves the private-directory filter attached for the rest of the request.
* The "private uploads directory is publicly accessible" admin notice now un-snoozes a week after dismissal. `Admin_Notices::on_dismiss()` is now actually hooked (on `add_option_`/`update_option_` for the dismissal option), and the option name is computed by a single shared helper (`Cron::get_dismissed_notice_option_name()`) that matches the name the wp-trt/admin-notices library stores it under – the previous inline name never matched, so the notice could be dismissed forever.
* The private uploads directory path/URL and the rewrite rule are now derived from `wp_upload_dir()` instead of hard-coded `WP_CONTENT_DIR`/`WP_CONTENT_URL`, fixing directory creation, the public-URL check, and the rewrite rule on multisite (per-site upload directories) and relocated-uploads installs.
* `Serve_Private_File` fixes: the ETag `If-None-Match` comparison now strips surrounding quotes and an optional `W/` weak prefix (the 304 branch previously never fired); logged-out visitors are redirected to wp-login instead of receiving a bare 403; `Cache-Control: private, max-age=3600` and `Content-Length` headers are sent. The decision logic was extracted into a testable pure method (first tests for this class).

* The rewrite rule protecting the private directory is now flushed to the site-root `.htaccess` once, when it is first added. Nothing ever flushed before, so on Apache the directory stayed publicly readable until the user happened to re-save their permalinks.
* `create_directory()` now writes an `index.php` guard file, preventing directory listing where the server has autoindex enabled. The `init`-time directory work is throttled via an option, and run lazily from `move_file_to_private_uploads()` so cron/WP-CLI uploads still create the directory. (No deny-all `.htaccess`: Apache would 403 it in the auth phase, before the fixup phase where the rewrite rule runs, locking authorised users out of their own files.)
* The "directory is publicly accessible" check now probes a real uploaded file, descending into the `yyyy/mm` subdirectory. It previously probed whichever entry `scandir()` returned first — in practice the year *directory*, which any server with autoindex off returns 403 for — and read that as "private", so the admin notice could never fire. Dotfiles and the `index.php` guard file are skipped for the same reason: servers refuse them whether or not the documents beside them are public.
* New end-to-end tests assert that an admin can download a private file from its direct URL, while an anonymous visitor and a logged-in subscriber cannot.

### Changed

* `Serve_Private_File` now grants access with the `manage_options` capability rather than the `administrator` role name, so multisite super admins without an explicit site role are covered (potential behavior change for sites relying on the exact role check).
