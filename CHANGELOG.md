# Changelog

## Unreleased

### Fixed

* The `API` class no longer requires the `upload_files` capability, so uploads succeed on cron and WP-CLI (where there is no logged-in user). Authorization remains enforced at each request boundary. Consumer plugins can veto uploads via the new `bh_wp_private_uploads_{post_type}_can_upload` filter.
* `API::move_file_to_private_uploads()` now removes its `upload_dir` filter and restores `$_POST['action']` in a `finally` block, so a failed `wp_handle_upload()` no longer leaves the private-directory filter attached for the rest of the request.
