import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import {loginAsAdmin} from "./helpers/ui/login";

// `npx wp-env install-path` . "WordPress/wp-content/uploads/private-media".
// ls $(npx wp-env install-path)"/WordPress/wp-content/uploads/private-media"

test('upload file via admin menu page', async ({ page, admin }) => {

  await loginAsAdmin( page );

  await admin.visitAdminPage('media-new.php', 'post_type=private_media');

  await page.waitForSelector('#plupload-upload-ui');

  // Use setInputFiles on the hidden file input - most reliable cross-browser approach
  // WordPress plupload creates a file input inside the uploader div
  const fileInput = page.locator('#plupload-upload-ui input[type="file"]');
  await fileInput.setInputFiles('./tests/_data/sample.pdf');

  // "Copy URL to clipboard" text indicates the upload was successful.
  const copyToClipboard = page.locator('.copy-attachment-url', {
	  hasText: /^Copy URL to clipboard$/,
  });

  await expect(copyToClipboard).toBeVisible();

  const uploadedUrl = await copyToClipboard.getAttribute('data-clipboard-text');

  // http://localhost:8888/wp-content/uploads/private-media/2026/02/sample-3.pdf
  // wp-content\/uploads\/private-media\/\d{4}\/\d{2}\/sample(-\d+)?.pdf
  expect(uploadedUrl).toMatch(/wp-content\/uploads\/private-media\/\d{4}\/\d{2}\/sample(-\d+)?.pdf/);
});
