import { test, expect } from '@playwright/test';

// `npx wp-env install-path` . "WordPress/wp-content/uploads/private-media".
// ls $(npx wp-env install-path)"/WordPress/wp-content/uploads/private-media"

test('upload file', async ({ page }) => {

  await page.goto( 'http://localhost:8888/wp-login.php', {
    waitUntil: 'networkidle',
  } );

  await page.fill( 'input[name="log"]', "admin" );
  await page.fill( 'input[name="pwd"]', "password" );
  await page.locator( '#loginform' ).getByText( 'Log In' ).click();

  await page.waitForLoadState( 'networkidle' );

  await page.goto('http://localhost:8888/wp-admin/media-new.php?post_type=private_media');

  await page.waitForSelector('#plupload-upload-ui');
  await page.waitForTimeout(500);

  // Use setInputFiles on the hidden file input - most reliable cross-browser approach
  // WordPress plupload creates a file input inside the uploader div
  const fileInput = page.locator('#plupload-upload-ui input[type="file"]');
  await fileInput.setInputFiles('./tests/_data/sample.pdf');

  // "Copy URL to clipboard" text indicates the upload was successful.
  await expect(
      page.locator( '.copy-attachment-url', {
          hasText: /^Copy URL to clipboard$/,
      } )
  ).toBeVisible();

});
