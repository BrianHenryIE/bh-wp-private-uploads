# Changelog

## Unreleased

### Security

* Breaking: the REST read routes no longer disclose private uploads to the public. `GET /{plugin-slug}/v1/{rest-base}` and `GET /{plugin-slug}/v1/{rest-base}/{id}` returned every private upload's title, owner and dates to logged-out visitors, because core treats a post with the `inherit` status as published. Both now require a logged-in user: a record is readable by the file's owner (`post_author`) and by users with the post type's `edit_others_posts` capability, and the list contains only the user's own files unless they have that capability.
* The private uploads post type is registered with `rewrite` and `query_var` disabled, so no front-end rewrite rules or query var exist for it. (It was already not publicly queryable.)
* The result of the hourly is-the-directory-private check is saved in its transient as a JSON string, not as a serialized `Is_Private_Result` object, so reading it no longer has WordPress `unserialize()` a class from the database. A value in any other shape, including one saved by an earlier version, is deleted and a new check is scheduled.
* A private file is only served when its real path, after resolving symlinks, is inside the private uploads directory. A symlink in the directory that points outside it is now answered with a 404 and logged, as a second layer behind the existing per-segment filename sanitization.
* Breaking: served private files are sent with `X-Content-Type-Options: nosniff` and a `Content-Disposition` header. Only images (JPEG, PNG, GIF, WebP, AVIF), PDF, plain text, audio and video are displayed in the browser; every other type, including HTML and SVG, is now downloaded, so a file with active content cannot run scripts on the site's origin. Inline images and plain text are also sent with `Content-Security-Policy: sandbox; default-src 'none'`.
* Add: `bh_wp_private_uploads_response_headers` filter to add, change or remove the headers sent with a private file.
* Add: `bh_wp_private_uploads_cache_control_max_age` filter to set the `Cache-Control` max-age (default one hour) of a served private file; the `Expires` header follows it.
* Breaking: the REST `post_parent` and `post_author` params are now authorized per object. Core only checks its own `post` and `author` params, so any user with `upload_files` could attach a private file to any post, or record any user as its owner. `post_parent` now requires `edit_post` on that post, and a `post_author` other than the current user requires the post type's `edit_others_posts` capability; otherwise the request is rejected with 403. Both params are declared in the routes' `args`: they must be integers and the ids of an existing post / user (400 otherwise).
* The REST create route's filters that set the post type, author and parent are scoped to the post for the uploaded file and removed after the request, so attachments inserted by other plugins' hooks during the request are no longer converted to the private uploads post type.
* Breaking: the REST `POST /{plugin-slug}/v1/{rest-base}/upload` response no longer discloses the server's absolute filesystem path. `file.file` is now relative to `WP_CONTENT_DIR` (e.g. `uploads/my-plugin/2026/10/doc.pdf`), or just the filename when uploads are outside `WP_CONTENT_DIR`. The `bh_wp_private_uploads_rest_upload` action still receives the absolute path.

### 0.5.1

* Fix: The "publicly accessible" notice was shown when the webserver redirects unauthenticated requests for private uploads to the WordPress login page: `wp_remote_get()` follows the redirect and the login page's 200 was read as public. The is-private check now treats a request that ends on the login page (the path of `wp_login_url()`) as private.

## 0.5.0 – 2026-09-03

Two new WP-CLI commands, `check` and `status`, for verifying and inspecting the private uploads directory, and a fix for a JavaScript error thrown on every admin page while the "publicly accessible" notice was snoozed. The hook argument order changed; see **Upgrading** below.

### Added

* `wp {cli-base} check` CLI command to verify the private uploads URL is not publicly accessible; exits non-zero when the URL is public or the check cannot be performed
* `wp {cli-base} status` CLI command to print the private uploads directory's path, URL, post count and most recent is-private check result, backed by new `API_Interface::get_status()`

### Changed

* Breaking: `$plugin_slug` and `$post_type_name` are now the first arguments (after the filtered value) of `bh_wp_private_uploads_can_upload`, `bh_wp_private_uploads_allow`, `bh_wp_private_uploads_url_is_public_warning` (filters) and `bh_wp_private_uploads_rest_upload` (action), moved from the end of the argument list
* The private media library, its "Media" submenu and the upload meta boxes are no longer registered when `Private_Uploads_Settings_Interface::get_post_type_name()` returns an empty string, matching the existing behaviour for the post type registration itself. `Private_Uploads_Settings_Trait` always returns a post type name, so only settings classes that implement the method themselves are affected.

### Fixed

* While the "publicly accessible" notice's dismissal was snoozed, every admin page threw a JavaScript `TypeError` per instance ("dismissBtn is null"): the WPTRT admin-notices library prints each registered notice's inline dismiss script even when the notice itself is suppressed as dismissed. The notice is now not registered at all while its dismissal is snoozed.

### Upgrading

* **Callbacks on the four hooks above must be updated for the new argument order.** Instance-specific data comes first, so a callback that only needs `$plugin_slug` can now declare an accepted-args count of `2` instead of listing every argument. For example, `add_filter( 'bh_wp_private_uploads_allow', $callback, 10, 2 )` with `function ( bool $should_serve_file, string $plugin_slug ): bool`. The deprecated per-post-type hooks are unchanged.

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
