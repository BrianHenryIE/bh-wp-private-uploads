# Changelog

## 0.3.0

### Added

- API: `move_file_to_private_uploads_and_create_post()` and `download_remote_file_to_private_uploads_and_create_post()` — save a file to the private uploads directory **and** create a post of the registered private-uploads post type recording it, so programmatically added files appear in the private media library UI. Accepts an optional owner (`post_author_id`, default: no owner) and parent post (`post_parent_id`, e.g. a WooCommerce order id). Returns the new `File_Upload_With_Post_Result` (extends `File_Upload_Result`, adds `post_id`).
- CLI: `--create-post`, `--post_author=<user_id>`, and `--post_parent=<post_id>` options on the `download` command (the id options imply `--create-post`); `post_id` is included in the command output.
- Behat CI workflow (SQLite, no MySQL service) running the WP-CLI feature tests.
- Release CI workflow verifying CHANGELOG.md documents the version on tag push.
- Dev tooling: `bin/sync-composer-wpenv.php` and `bin/wpenv-mappings-to-phpstorm.php`, replacing `jq`/`sponge` shell pipelines.

### Changed

- **Breaking:** `API_Interface` gained the two new methods — external implementors of the interface must add them.
- CI: unit-test coverage threshold is now enforced (ratchet at 40%); PHPCS failures on PRs are scoped to changed files; `setup-php` pinned; integration workflow now maps the MySQL service port correctly.
- `npm run test:e2e` runs Playwright via `tsx` (was pointing at the removed Puppeteer runner).

### Fixed

- CLI `download` Behat scenarios run as an admin user (they were failing the `upload_files` capability check).
- Chronically failing e2e test: the post-meta-box test no longer waits for `networkidle` after reloading the editor (Gutenberg's background requests meant the network never went quiet in CI).
