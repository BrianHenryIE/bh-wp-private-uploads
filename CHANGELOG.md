# Changelog

## Unreleased

### Fixed

* The `API` class no longer requires the `upload_files` capability, so uploads succeed on cron and WP-CLI (where there is no logged-in user). Authorization remains enforced at each request boundary. Consumer plugins can veto uploads via the new `bh_wp_private_uploads_{post_type}_can_upload` filter.
* `API::move_file_to_private_uploads()` now removes its `upload_dir` filter and restores `$_POST['action']` in a `finally` block, so a failed `wp_handle_upload()` no longer leaves the private-directory filter attached for the rest of the request.
* The "private uploads directory is publicly accessible" admin notice now un-snoozes a week after dismissal. `Admin_Notices::on_dismiss()` is now actually hooked (on `add_option_`/`update_option_` for the dismissal option), and the option name is computed by a single shared helper (`Cron::get_dismissed_notice_option_name()`) that matches the name the wp-trt/admin-notices library stores it under – the previous inline name never matched, so the notice could be dismissed forever.
