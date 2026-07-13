# Changelog

## 0.4.0 – 2026-07-12

The private uploads directory was not actually private on a stock Apache install, and the admin notice that was supposed to warn about it could never fire. Both are fixed. See **Upgrading** below for the two behaviour changes worth checking before you update.

### Added

* `bh_wp_private_uploads_can_upload` filter, so a consumer plugin can veto an upload. Passed the source path, filename, plugin slug and post type name.
* An `index.php` guard file is written into the private uploads directory, preventing a directory listing where the server has autoindex enabled.
* End-to-end tests asserting an admin can download a private file from its direct URL, while an anonymous visitor and a logged-in subscriber cannot.
* First tests for `Serve_Private_File`, `WP_Rewrite`, `Post_Type`, `REST_Private_Uploads_Controller` and `Admin_Meta_Boxes`.

### Fixed

* **The rewrite rule protecting the directory is now actually written to `.htaccess`.** Nothing ever flushed the rewrite rules, so on Apache the private directory stayed publicly readable until someone happened to re-save their permalinks.
* The rule is now restored if it is ever removed — `.htaccess` is read on admin page loads and the rules flushed when the rule is missing, rather than trusting an option that could record a flush which never happened.
* **The "directory is publicly accessible" notice could never fire.** The check probed whatever `scandir()` returned first — in practice the `yyyy` directory, which any server with autoindex off returns 403 for — and read that as "private". It now probes a real uploaded file.
* The same notice now un-snoozes a week after dismissal. `Admin_Notices::on_dismiss()` was never hooked, and the option name never matched the one the wp-trt/admin-notices library stores, so a dismissal was permanent.
* Uploads now succeed on cron and WP-CLI, where there is no logged-in user — the `API` no longer requires the `upload_files` capability.
* Paths and URLs are derived from `wp_upload_dir()` rather than hard-coded `WP_CONTENT_DIR`/`WP_CONTENT_URL`, fixing multisite (per-site upload directories) and relocated-uploads installs.
* A failed `wp_handle_upload()` no longer leaves the `upload_dir` filter attached for the rest of the request, which silently redirected every subsequent upload into the private directory.
* `Serve_Private_File`: the ETag was sent quoted but compared unquoted, so the 304 branch never fired; logged-out visitors now get a login redirect instead of a bare 403; `Cache-Control: private` and `Content-Length` headers are sent.
* The REST `upload_item` route (upload a file without creating a post) was unreachable. It registered at `{namespace}/{rest_base}/`, and `register_rest_route()` trims the trailing slash — giving it the same path as the collection route, which won on dispatch.
* `API::create_directory()` no longer throws. It is hooked on `init`, where a filesystem failure must not fatal the site; failures are logged and returned as an unsuccessful result.

### Changed

* **PHP 8.1 is now the minimum** (was 8.0).
* All hooks have static names, and are passed the plugin slug and post type name as their final two arguments, so one callback can serve — or ignore — each private uploads instance.
* The REST "upload without creating a post" endpoint moved to `{namespace}/{rest_base}/upload`. It was previously unreachable (see Fixed), so nothing can have been calling it.
* `Serve_Private_File` grants access with the `manage_options` capability rather than the `administrator` role name, so multisite super admins without an explicit site role are covered.
* The `API` class performs **no user-capability checks**. Authorisation is enforced at each request boundary instead (REST `permission_callback`, admin-ajax, WP-CLI). If you call the API from a web request, check capabilities yourself.

### Deprecated

The old hooks still fire, and raise a deprecation notice when hooked. They will be removed in a future release.

* `bh_wp_private_uploads_{$post_type_name}_allow` → `bh_wp_private_uploads_allow`.
* `bh_wp_private_uploads_url_is_public_warning_{$post_type_name}` → `bh_wp_private_uploads_url_is_public_warning`.
* `rest_private_uploads_upload` → `bh_wp_private_uploads_rest_upload`. The settings object is no longer passed; the plugin slug and post type name are passed instead.

### Removed

* `API::is_url_public_for_admin()` — never called, and it forwarded raw `$_COOKIE` values into an outbound HTTP request. It was `protected` on a class designed for extension, so a subclassing consumer could in principle have been calling it.

### Upgrading

* **Access is now granted by the `manage_options` capability, not the `administrator` role.** If you relied on the exact role check, or on a user having the role but not the capability, revisit it.
* **The `API` no longer checks capabilities.** That is what makes cron and WP-CLI uploads work. If your plugin calls `API::move_file_to_private_uploads()` (or the `download_…` variants) directly from a web request, you must check capabilities before doing so, or hook `bh_wp_private_uploads_can_upload`.
* Nothing is required to pick up the `.htaccess` fix — the rule is written on the next admin page load.
