# bh-wp-private-uploads – remediation plan

Each numbered section below is intended to be one PR. They are ordered so no PR depends on a later one. PR 1 is the fix for the bug observed in bh-wp-mailboxes (uploads failing on cron because of `current_user_can( 'upload_files' )`).

Conventions for every PR:

- Branch name: `fix/<kebab-case-title>` or `test/<kebab-case-title>` as appropriate.
- Run `composer lint` (PHPCS) and PHPStan before committing; match existing code style (tabs, file/class naming `class-*.php`, docblocks on every method).
- Unit tests (10up mocks) go in `tests/unit/`, WordPress-loaded tests in `tests/wpunit/`, mirroring the `includes/` subdirectory structure and existing test-class naming (`class-<name>-Unit-Test.php`, `class-<name>-WPUnit-Test.php`).
- Update `CHANGELOG.md` in each PR.

---

## PR 1: Remove user-capability checks from the API class so uploads work on cron and WP-CLI

**Problem:** `API::download_remote_file_to_private_uploads()` (`includes/api/class-api.php:57`) and `API::move_file_to_private_uploads()` (`includes/api/class-api.php:98`) throw `Private_Uploads_Exception` when `current_user_can( 'upload_files' )` is false. On cron, WP-CLI without `--user`, and any other unauthenticated server-side context, the current user id is 0, so these library methods always fail. Authorization is already enforced at every request boundary that reaches this class: `REST_Private_Uploads_Controller` inherits `create_item_permissions_check()` from `WP_REST_Attachments_Controller`; media-library uploads go through core's `wp_ajax_upload_attachment()`; WP-CLI is trusted by definition.

**Changes:**

1. In `includes/api/class-api.php`, delete both `current_user_can( 'upload_files' )` blocks (lines 57–59 and 98–100).
2. In their place in `move_file_to_private_uploads()` only, add a filter so consumer plugins can veto uploads if they want a capability check:
   ```php
   /**
    * Filter whether this file may be moved into private uploads.
    *
    * Authorization is the responsibility of the calling code / request handler
    * (REST permission_callback, admin-ajax capability checks, CLI). This filter
    * exists for consumer plugins that want an additional guard.
    *
    * @param bool   $can_upload Default true.
    * @param string $tmp_file   Source filepath.
    * @param string $filename   Destination filename.
    */
   if ( ! apply_filters( "bh_wp_private_uploads_{$this->settings->get_post_type_name()}_can_upload", true, $tmp_file, $filename ) ) {
       throw new Private_Uploads_Exception( 'Upload rejected by bh_wp_private_uploads_*_can_upload filter.' );
   }
   ```
   (`download_remote_file_to_private_uploads()` delegates to `move_file_to_private_uploads()`, so one filter call covers both.)
3. Update the `@throws` docblocks on both methods (no longer "On permissions failure" from capability; now "when the can_upload filter returns false").
4. Document in `README.md` that the API class performs no user-capability checks and that callers exposing it to web requests must check capabilities themselves.

**Tests (this closes the regression that motivated the PR — there is currently no test covering upload in a no-user context):**

- `tests/wpunit/api/class-api-wpunit-Test.php`: new test `test_move_file_to_private_uploads_succeeds_with_no_logged_in_user_during_cron()` — `wp_set_current_user( 0 )`, define/simulate `DOING_CRON`, create a temp file, assert the file lands in `uploads/<subdir>/` with no exception.
- New test `test_move_file_to_private_uploads_throws_when_can_upload_filter_returns_false()`.
- New wpunit REST test (see also PR 7): POST to the attachment route as a subscriber still returns 403, proving removing the API check did not weaken the REST boundary.

---

## PR 2: Always remove the `upload_dir` filter and restore `$_POST['action']` in `API::move_file_to_private_uploads()`

**Problem:** In `includes/api/class-api.php`, `add_filter( 'upload_dir', array( $this, 'set_private_uploads_path' ) )` (line 128) is only removed at line 167, after two `throw` statements (lines 159 and 164). When `wp_handle_upload()` returns an error, the filter remains attached for the rest of the request, silently redirecting all subsequent uploads into the private directory. Similarly, when `$_POST['action']` was not set before the call, it is left set to `wp_handle_private_upload`.

**Changes:**

1. Wrap the section from the `add_filter()` call through the `wp_handle_upload()` result-handling in `try { … } finally { … }`. In the `finally` block:
   - `remove_filter( 'upload_dir', array( $this, 'set_private_uploads_path' ) );`
   - restore `$_POST['action']` to its prior value, or `unset( $_POST['action'] )` when it was not previously set (the current code only restores, never unsets).
2. Keep the two `throw` statements inside the `try`; behavior on success is unchanged. Follow the pattern already used in `API::create_post_for_file()` (lines 287–292).

**Tests (both failure paths are currently uncovered):**

- `tests/wpunit/api/class-api-wpunit-Test.php`:
  - `test_upload_dir_filter_is_removed_when_wp_handle_upload_fails()` — force failure (e.g. unreadable/zero-byte tmp file or an `upload_dir` error via a competing filter), catch the `Private_Uploads_Exception`, then assert `has_filter( 'upload_dir', array( $api, 'set_private_uploads_path' ) )` is false and a subsequent plain `wp_upload_dir()` call returns the non-private path.
  - `test_post_action_global_is_unset_after_upload_when_not_previously_set()` and `test_post_action_global_is_restored_after_upload_when_previously_set()`.

---

## PR 3: Hook `Admin_Notices::on_dismiss()` so the "publicly accessible" notice un-snoozes after a week

**Problem:** `Admin_Notices::on_dismiss()` (`includes/admin/class-admin-notices.php:102`) claims `@hooked update_option_wptrt_notice_dismissed_<posttype>_private_uploads_public_url`, but `BH_WP_Private_Uploads_Hooks::define_admin_notices_hooks()` (`includes/class-bh-wp-private-uploads-hooks.php:84`) never registers it. A dismissed "private uploads directory is publicly accessible" warning therefore never reappears, and `Cron::unsnooze_dismissed_notice()` is dead code.

**Changes:**

1. In `define_admin_notices_hooks()`, compute the option name exactly as `Cron::unsnooze_dismissed_notice()` does (`wptrt_notice_dismissed_{post_type}_private_uploads_public_url` — extract a shared helper, e.g. `Cron::get_dismissed_notice_option_name()`, so the two cannot drift) and add:
   ```php
   add_action( "add_option_{$option_name}", array( $admin_notices, 'on_dismiss' ), 10, 2 );
   add_action( "update_option_{$option_name}", array( $admin_notices, 'on_dismiss' ), 10, 3 );
   ```
   Note the differing signatures: `add_option_{option}` passes `( $option, $value )`, `update_option_{option}` passes `( $old_value, $value, $option )`. Adjust `on_dismiss()`'s signature to tolerate both (or register two thin callbacks).
2. In `Cron::unsnooze_dismissed_notice()` (`includes/wp-includes/class-cron.php:139`), use the shared option-name helper instead of the inline `sprintf`.

**Tests:**

- `tests/unit/class-bh-wp-private-uploads-hooks-Unit-Test.php`: assert both `add_option_…`/`update_option_…` actions are registered with the expected hook names — this is a hook-registration test, the exact class of test whose absence let this bug ship.
- `tests/wpunit/`: integration test — add the dismissal option, assert a single cron event for `get_unsnooze_notice_cron_hook_name()` is scheduled ~`WEEK_IN_SECONDS` out; fire the hook, assert the option is deleted.

---

## PR 4: Use `wp_upload_dir()` instead of hard-coded `WP_CONTENT_DIR . '/uploads'` paths

**Problem:** Three places build the private-uploads location from constants rather than `wp_upload_dir()`, breaking multisite (per-site `sites/{blog_id}` dirs) and relocated-uploads (`UPLOADS` constant) installs, while `Serve_Private_File::send_private_file()` correctly uses `wp_upload_dir()['basedir']` — so files could be written to one location and served from another:

- `API::create_directory()` — `includes/api/class-api.php:346`
- `API::check_and_update_is_url_private()` — `includes/api/class-api.php:443–460` (both `WP_CONTENT_DIR` and `WP_CONTENT_URL`)
- `WP_Rewrite::register_rewrite_rule()` — `includes/wp-includes/class-wp-rewrite.php:39`

**Changes:**

1. Add a small shared helper (suggest `protected function get_private_uploads_directory_path(): string` and `…_url(): string` on `API`, or a tiny value class both `API` and `WP_Rewrite` can use) built from `wp_upload_dir( null, false )['basedir']` / `['baseurl']` + `/{$this->settings->get_uploads_subdirectory_name()}`. Use `wp_upload_dir( null, false )` to avoid the directory-creation side effect where only the path is needed.
2. Replace the three hard-coded usages. In `WP_Rewrite`, derive the rewrite regex from the basedir made relative to `ABSPATH`, as now, but from the `wp_upload_dir()` value.

**Tests (all three call sites are currently uncovered for path construction):**

- `tests/wpunit/api/class-api-wpunit-Test.php`: filter `upload_dir` to relocate `basedir`, then assert `create_directory()` creates inside the relocated path and `check_and_update_is_url_private()` probes the relocated URL.
- New `tests/wpunit/wp-includes/class-wp-rewrite-WPUnit-Test.php`: assert the generated regex/query pair added to `$wp_rewrite` external rules matches the relocated uploads path (first tests ever for `WP_Rewrite` — currently 0% coverage).

---

## PR 5: `Serve_Private_File` fixes — ETag comparison, capability check, login redirect, cache headers — plus a test suite

**Problems** (all in `includes/frontend/class-serve-private-file.php`, which has **zero test coverage despite being the security-critical access-control path**):

1. Line 211: ETag is sent quoted (`ETag: "hash"`, line 153) but compared unquoted against `If-None-Match`, so that branch of the 304 logic never fires (already flagged inline).
2. Line 96: `current_user_can( 'administrator' )` checks a role name, not a capability — fails for multisite super admins without an explicit role on the site, and is PHPCS-flagged (`WordPress.WP.Capabilities.RoleFound`).
3. Lines 108–115: comment says logged-out users should be redirected to login, but everyone gets a bare 403.
4. Caching headers: `Expires: +1 hour` is sent with no `Cache-Control`, so a shared proxy may cache private content; no `Content-Length` is sent.

**Changes:**

1. Strip surrounding double quotes (and optional `W/` weak prefix) from the `If-None-Match` value before comparing with the raw `$etag`.
2. Replace `current_user_can( 'administrator' )` with `current_user_can( 'manage_options' )`. Keep the existing `bh_wp_private_uploads_{post_type}_allow` filter as the extension point; note the capability change in the CHANGELOG as a potential behavior change.
3. When `! $should_serve_file`: if `! is_user_logged_in()`, call `auth_redirect()` (redirects to wp-login with return URL); otherwise keep the 403.
4. Add `header( 'Cache-Control: private, max-age=3600' );` alongside `Expires`, and `header( 'Content-Length: ' . (string) filesize( $path ) );` before `readfile()`.
5. **Testability refactor:** the `die()` calls make the class untestable, which is why it has no tests. Extract the decision logic into pure protected methods, e.g. `protected function get_response_for_request( string $file ): Private_File_Response` returning a small value object (status code, headers, filepath-to-stream or null), with `send_private_file()` reduced to emitting it and `die()`ing (or inject a terminator callable defaulting to `exit`). Keep the public behavior identical.

**Tests — new `tests/unit/frontend/class-serve-private-file-Unit-Test.php` and `tests/wpunit/frontend/class-serve-private-file-WPUnit-Test.php` covering:**

- logged-out user → login redirect; logged-in non-admin → 403; admin (`manage_options`) → 200 with file contents.
- `bh_wp_private_uploads_{post_type}_allow` filter grants a non-admin access.
- missing file → 404; `sanitize_filepath()` against traversal inputs (`../`, encoded slashes, `foo/../../wp-config.php`) — assert the resolved path stays inside the private directory.
- ETag: quoted `If-None-Match` and `W/"…"` both produce 304; `If-Modified-Since` newer than mtime produces 304; stale produces 200.
- Header assertions: `Content-Type`, `Cache-Control: private`, `Content-Length`.

---

## PR 6: Defense-in-depth for the private directory and cheaper `init`

**Problems:**

1. `WP_Rewrite::register_rewrite_rule()` adds an external rule, but nothing ever flushes rewrite rules, so the `.htaccess` protection only takes effect if the user happens to re-save permalinks. Nginx sites get no file-level protection at all (the hourly URL check exists to warn about this, but prevention is better).
2. `API::create_directory()` is hooked on every `init` (`define_api_hooks()`, `includes/class-bh-wp-private-uploads-hooks.php:62–65`) and does `file_exists()` filesystem work on every request (existing TODO acknowledges this).

**Changes:**

1. In `API::create_directory()`, after successfully creating (or confirming) the directory, write an `index.php` guard file if absent, to prevent directory listing where autoindex is enabled.

   **No deny-all `.htaccess`.** This was in the original plan, on the premise that "files are served by PHP via the `?{post-type}-private-uploads-file=` route, not directly". That premise is false: nothing generates that URL — the attachment `guid` is the direct uploads URL (`API::create_post_for_file()`), and the request only reaches PHP *because* the root-`.htaccess` rewrite rule intercepts it. Apache evaluates `Require all denied` in its auth phase, before the fixup phase where per-directory `mod_rewrite` rules run, so a deny-all here 403s the request before the rewrite can hand it to `Serve_Private_File`. Verified on wp-env: with the guard file in place an **admin** gets a bare Apache 403 for their own private file. It also adds nothing on nginx (which ignores `.htaccess`), and it poisons `check_and_update_is_url_private()`, whose probe would target the `.htaccess` — a file every server 403s regardless of exposure — permanently silencing the admin notice.

   Prevention on Apache is the rewrite rule (change 3 below makes it actually take effect); detection everywhere is the hourly URL check. `tests/e2e-pw/private-file-access.spec.ts` now pins both ends: an admin must get the file, an anonymous visitor must not.
2. Keep the `init` work off frontend page loads. `create_directory()` returns early when `doing_action( 'init' ) && ! is_admin() && ! wp_doing_cron() `, so the filesystem calls only happen for admin, cron and WP-CLI requests — no option-based throttle needed (an earlier revision of this PR stored `bh_wp_private_uploads_{post_type}_directory_created`; it added a cached "already done" flag that also meant a deleted guard file was never restored). Also call `create_directory()` lazily from `move_file_to_private_uploads()` before `wp_handle_upload()`, so cron/CLI uploads work even if `init`-time creation was skipped.
3. After `WP_Rewrite::register_rewrite_rule()` adds the rule, read the site-root `.htaccess` and `flush_rewrite_rules()` when the rule is missing from it.

   `.htaccess` is the source of truth, not an option recording that a flush has happened. An earlier revision of this PR used `bh_wp_private_uploads_{post_type}_rewrite_flushed`, which was unsound: `WP_Rewrite::flush_rules()` only writes the file via `save_mod_rewrite_rules()`, which is defined in `wp-admin/includes/misc.php` and therefore does not exist on frontend, cron or WP-CLI requests — so a "hard" flush there silently writes nothing while the option happily records success, permanently. (That is why CI has to run `wp rewrite structure --hard`: it activates the plugin over WP-CLI.) Reading the file also means the rule is restored if it is ever removed.

   Only run on admin page loads — the only context that can write the file — and skip when a flush could not write the rule anyway (multisite, plain permalinks, no `mod_rewrite`, `flush_rewrite_rules_hard` filtered false, unwritable file), otherwise we would flush on every admin request forever.

**Tests:**

- `tests/wpunit/api/class-api-wpunit-Test.php`: `create_directory()` writes `.htaccess` and `index.php`; second call is a no-op (assert via filemtime or by deleting the option only); upload via `move_file_to_private_uploads()` creates the directory when it does not yet exist (currently uncovered path).
- e2e (`tests/e2e-pw`): existing private-file delivery spec must still pass with the `.htaccess` present; direct URL to an uploaded file returns non-200.

---

## PR 7: Dead-code removal and test-coverage backfill for untested classes

**Problems / gaps:**

1. `API::is_url_public_for_admin()` (`includes/api/class-api.php:535–567`) is never called — dead code (and it forwards raw `$_COOKIE` values).
2. Classes with **no tests at all**: `Post_Type`, `REST_Private_Uploads_Controller`, `Admin_Meta_Boxes` (`WP_Rewrite` gains tests in PR 4, `Serve_Private_File` in PR 5).
3. `Media::on_upload_attachment()` nonce/referer branches (`is_private_upload_via_post()`, `is_private_upload_via_referer()`) are uncovered by the existing `tests/wpunit/wp-includes/class-media-WPUnit-Test.php`.

**Changes:**

1. Delete `API::is_url_public_for_admin()`.
2. New `tests/wpunit/wp-includes/class-post-type-WPUnit-Test.php`: post type is registered non-public, `show_in_rest`, correct `rest_namespace`/`rest_base`/`rest_controller_class` when `get_rest_base()` is non-null; not registered when post type name is empty.
3. New `tests/wpunit/wp-includes/class-rest-private-uploads-controller-WPUnit-Test.php`:
   - subscriber POST → 403 (pairs with PR 1),
   - editor/admin POST with file → post created with the custom post type (not `attachment`), file stored under `uploads/{subdir}/`,
   - `upload_item` route (`POST /{namespace}/{rest_base}/`) stores the file without creating a post and fires `rest_private_uploads_upload`,
   - `post_parent` and `post_author` params are respected.
4. New `tests/wpunit/admin/class-admin-meta-boxes-WPUnit-Test.php`: meta box registered only for post types returned by `get_meta_box_settings()`.
5. Extend `tests/wpunit/wp-includes/class-media-WPUnit-Test.php`: `on_upload_attachment()` adds its three hooks when a valid `media-form`/`upload-attachment` nonce + matching `post_type` POST is present, does nothing without the nonce, and the `async-upload.php` referer branch works.

---

## Suggested PR order and rationale

| PR | Title | Why this order |
|----|-------|----------------|
| 1 | API capability checks → filter (cron/CLI fix) | Fixes the live bug in bh-wp-mailboxes |
| 2 | `try/finally` around `wp_handle_upload()` | Touches the same method as PR 1; rebase after it |
| 3 | Hook `on_dismiss` unsnooze | Independent, small |
| 4 | `wp_upload_dir()` paths | Independent; prerequisite for PR 6's guard files being written in the right place |
| 5 | `Serve_Private_File` fixes + first test suite | Largest PR; independent of 1–4 |
| 6 | Guard files, lazy directory creation, rewrite flush | Depends on PR 4 |
| 7 | Dead code + coverage backfill | Last; REST tests assume PR 1 landed |
