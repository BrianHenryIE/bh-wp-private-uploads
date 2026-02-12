import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { loginAsAdmin } from './helpers/ui/login';

test( 'upload file via upload.php media library ui', async ( {
	page,
	admin,
} ) => {
	await loginAsAdmin( page );

	// Navigate to the private media library page.
	// Set up the AJAX response listener BEFORE navigating so we don't miss it.
	const gridLoadedPromise = page.waitForResponse(
		( response ) =>
			response.url().includes( 'admin-ajax.php' ) &&
			response.request().postData()?.includes( 'query-attachments' ) === true,
		{ timeout: 15000 }
	);
	await admin.visitAdminPage( 'upload.php', 'post_type=private_media&mode=grid' );

	// Verify we're on the private media library
	await expect( page.locator( '.wrap h1' ).first() ).toContainText(
		'Media Library'
	);

	// Wait for the grid's AJAX query to complete so we get an accurate count
	await gridLoadedPromise;
	// Small delay for DOM to update after AJAX response
	await page.waitForTimeout( 500 );

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

	// Reload the page with list view and verify the uploaded file persisted.
	// Don't check exact counts — parallel browser tests share the same WP instance,
	// so counts can shift between page loads. Just verify items are present.
	await admin.visitAdminPage( 'upload.php', 'post_type=private_media&mode=list' );

	// List view should have at least one row
	await expect(
		page.locator( '.wp-list-table #the-list tr' ).first()
	).toBeVisible( { timeout: 10000 } );

	// Reload with grid view and verify items are present
	const gridReloadPromise = page.waitForResponse(
		( response ) =>
			response.url().includes( 'admin-ajax.php' ) &&
			response.request().postData()?.includes( 'query-attachments' ) === true,
		{ timeout: 15000 }
	);
	await admin.visitAdminPage( 'upload.php', 'post_type=private_media&mode=grid' );
	await gridReloadPromise;
	await page.waitForTimeout( 500 );

	// Grid view should have at least one attachment
	await expect(
		page.locator( '.attachments .attachment' ).first()
	).toBeVisible();
} );
