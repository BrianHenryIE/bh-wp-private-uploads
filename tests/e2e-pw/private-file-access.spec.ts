import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { Page, request } from '@playwright/test';
import { login, loginAsAdmin } from './helpers/ui/login';

/**
 * End-to-end proof of the whole private-uploads design, which no PHP test can cover.
 *
 * The file lives at a real URL under `wp-content/uploads/private-media/`. It is *not* protected by a
 * deny-all `.htaccess` in that directory – that would 403 the request in Apache's auth phase, before the
 * fixup phase where the site-root `.htaccess` rewrite rule hands the request to WordPress. Protection
 * comes from that rewrite rule sending the request to `Serve_Private_File`, which checks capabilities and
 * streams the file itself.
 *
 * So the two assertions below are in tension, and that is the point:
 *
 *   - an admin must GET the file (proves the rewrite reaches WordPress and it serves the bytes);
 *   - an anonymous visitor must NOT (proves the rewrite is actually flushed into the root `.htaccess`;
 *     without the flush Apache serves the file straight off disk).
 *
 * Requires pretty permalinks flushed hard, so the rewrite rule is written to the root `.htaccess`:
 *   npx wp-env run cli -- wp rewrite structure '/%year%/%monthnum%/%postname%/' --hard
 * CI does this in .github/workflows/e2e.yml.
 */

const PDF_BYTES = Buffer.from(
	'%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n'
);

/**
 * Upload a uniquely-named PDF through the private media library and return its URL.
 *
 * The URL is read from the attachment details rather than reconstructed from the date, because
 * WordPress suffixes the filename (`-1`, `-2`) when it collides with an existing upload.
 */
async function uploadPrivateFile( page: Page, admin ): Promise< string > {
	const filename = `private-e2e-${ Date.now() }-${ Math.random()
		.toString( 36 )
		.slice( 2, 8 ) }.pdf`;

	const gridLoaded = page.waitForResponse(
		( response ) =>
			response.url().includes( 'admin-ajax.php' ) &&
			response.request().postData()?.includes( 'query-attachments' ) ===
				true,
		{ timeout: 15000 }
	);
	await admin.visitAdminPage(
		'upload.php',
		'post_type=private_media&mode=grid'
	);
	await gridLoaded;
	await page.waitForTimeout( 500 );

	const countBefore = await page
		.locator( '.attachments .attachment' )
		.count();

	await page.locator( 'a.page-title-action' ).first().click();
	await expect( page.locator( '.upload-ui' ) ).toBeVisible( {
		timeout: 15000,
	} );

	await page.locator( '.moxie-shim input[type="file"]' ).setInputFiles( {
		name: filename,
		mimeType: 'application/pdf',
		buffer: PDF_BYTES,
	} );

	// Wait for the upload to finish, otherwise the first grid item is still a placeholder.
	await expect( page.locator( '.attachments .attachment' ) ).toHaveCount(
		countBefore + 1,
		{ timeout: 30000 }
	);

	// Newest first: open its details to read the URL WordPress actually assigned.
	await page.locator( '.attachments .attachment' ).first().click();

	const urlField = page.locator(
		'.attachment-details [data-setting="url"] input'
	);
	await expect( urlField ).toBeVisible( { timeout: 15000 } );
	await expect( urlField ).not.toHaveValue( '', { timeout: 15000 } );

	const url = await urlField.inputValue();

	expect( url ).toContain( '/wp-content/uploads/private-media/' );
	expect( url ).toContain( filename.replace( '.pdf', '' ) );

	return url;
}

test( 'an admin can download a private file from its direct URL', async ( {
	page,
	admin,
} ) => {
	await loginAsAdmin( page );

	const fileUrl = await uploadPrivateFile( page, admin );

	// `page.request` carries the logged-in cookies.
	const response = await page.request.get( fileUrl );

	expect( response.status() ).toBe( 200 );
	expect( ( await response.body() ).toString( 'latin1' ) ).toContain(
		'%PDF'
	);
} );

test( 'an anonymous visitor cannot download a private file', async ( {
	page,
	admin,
	baseURL,
} ) => {
	await loginAsAdmin( page );

	const fileUrl = await uploadPrivateFile( page, admin );

	// `storageState: undefined` is essential: the config sets a logged-in admin storage state globally,
	// which a new context would otherwise inherit – and then this would pass while proving nothing.
	const anonymous = await request.newContext( {
		baseURL,
		storageState: undefined,
	} );

	const response = await anonymous.get( fileUrl );

	// The assertion that matters: the bytes must never reach a visitor with no session. If the rewrite
	// rules were not flushed into the root .htaccess, Apache would serve the PDF straight off disk.
	const body = ( await response.body() ).toString( 'latin1' );
	expect( body ).not.toContain( '%PDF' );

	// `Serve_Private_File` sends the logged-out to wp-login via `auth_redirect()`; 403 is also acceptable.
	// `response.url()` is the final URL, after the redirect has been followed.
	expect(
		response.url().includes( 'wp-login.php' ) || response.status() === 403
	).toBe( true );

	await anonymous.dispose();
} );

test( 'a logged-in subscriber cannot download a private file', async ( {
	page,
	admin,
	requestUtils,
	browser,
	baseURL,
} ) => {
	await loginAsAdmin( page );

	const fileUrl = await uploadPrivateFile( page, admin );

	const username = `subscriber-${ Date.now() }`;
	const password = 'subscriber-password';

	await requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/users',
		data: {
			username,
			password,
			email: `${ username }@example.org`,
			roles: [ 'subscriber' ],
		},
	} );

	// `storageState: undefined` discards the admin session the config would otherwise apply here.
	const subscriberContext = await browser.newContext( {
		baseURL,
		storageState: undefined,
	} );
	const subscriberPage = await subscriberContext.newPage();

	await login( { username, password }, subscriberPage );

	const response = await subscriberPage.request.get( fileUrl );

	// Authenticated but unauthorised: refused outright, not redirected to log in as someone else.
	expect( ( await response.body() ).toString( 'latin1' ) ).not.toContain(
		'%PDF'
	);
	expect( response.status() ).toBe( 403 );

	await subscriberContext.close();
} );
