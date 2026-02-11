import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { loginAsAdmin } from './helpers/ui/login';

test( 'upload file via upload.php media library ui', async ( {
	page,
	admin,
} ) => {
	await loginAsAdmin( page );

	// Navigate to the private media library page
	await admin.visitAdminPage( 'upload.php', 'post_type=private_media' );

	// Verify we're on the private media library
	await expect( page.locator( '.wrap h1' ).first() ).toContainText(
		'Media Library'
	);

	// Note the current media count before upload
	const countBefore = await page
		.locator( '.attachments .attachment' )
		.count();

	// Click "Add Media File" to reveal the inline uploader
	const addMediaButton = page.locator( 'a.page-title-action' ).first();
	await expect( addMediaButton ).toBeVisible();
	await addMediaButton.click();

	// Wait for the inline uploader to appear
	const uploadUI = page.locator( '.upload-ui' );
	await expect( uploadUI ).toBeVisible( { timeout: 5000 } );

	// Use setInputFiles on the hidden file input within the uploader
	const fileInput = page.locator( '.moxie-shim input[type="file"]' );
	await fileInput.setInputFiles( './tests/_data/sample.pdf' );

	// Wait for the upload to complete — the new attachment should appear in the grid
	// The grid count should increase by 1
	await expect( page.locator( '.attachments .attachment' ) ).toHaveCount(
		countBefore + 1,
		{ timeout: 15000 }
	);

	// Verify the newly uploaded file is the private_media post type
	// by checking it appears on this filtered page (post_type=private_media)
	const newestAttachment = page
		.locator( '.attachments .attachment' )
		.first();
	await expect( newestAttachment ).toBeVisible();
} );
