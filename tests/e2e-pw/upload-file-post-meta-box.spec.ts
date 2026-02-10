import { test, expect } from '@playwright/test';

async function login(page) {
	// Login
	await page.goto('http://localhost:8888/wp-login.php', {
		waitUntil: 'networkidle',
	});

	await page.fill('input[name="log"]', 'admin');
	await page.fill('input[name="pwd"]', 'password');
	await page.locator('#loginform').getByText('Log In').click();
	await page.waitForLoadState('networkidle');
}

async function createNewWpPage(page): Promise<number> {
	// Use REST API to create a page
	// First, we need to get a nonce for authentication by visiting an admin page
	// The wpApiSettings.nonce is available on any admin page after login

	await page.goto('http://localhost:8888/wp-admin/');
	await page.waitForLoadState('networkidle');

	// Get the REST API nonce from wpApiSettings
	const nonce = await page.evaluate(() => {
		// @ts-ignore - wpApiSettings is defined by WordPress
		return window.wpApiSettings?.nonce;
	});

	if (!nonce) {
		throw new Error('Could not get WordPress REST API nonce');
	}

	const title = 'Test Private Upload Page ' + Date.now();

	// Now make the REST API call with the nonce
	const response = await page.request.post('http://localhost:8888/?rest_route=/wp/v2/pages', {
		headers: {
			'X-WP-Nonce': nonce,
		},
		data: {
			title: title,
			status: 'draft',
		},
	});

	if (!response.ok()) {
		throw new Error(`Failed to create page via REST API: ${response.status()} ${await response.text()}`);
	}

	const pageData = await response.json();
	const pageId = pageData.id;

	// Navigate to the edit screen for the newly created page
	await page.goto(`http://localhost:8888/wp-admin/post.php?post=${pageId}&action=edit`);

	// Wait for block editor to load
	await page.waitForSelector('.edit-post-header, .editor-header', { timeout: 15000 });

	return pageId;
}

test('upload file via post meta-box', async ({ page }) => {

	await login(page);
	await createNewWpPage(page);

  // The Private Uploads panel is in the sidebar (Editor settings), not in Meta Boxes
  // Look for the "Select files" button in the Private Uploads panel
  // First ensure the Settings sidebar is open (it shows the Page/Block tabs)
  // Use a more specific selector to avoid Query Monitor's Settings button
  const settingsButton = page.locator('.editor-header button[aria-label="Settings"], .edit-post-header button[aria-label="Settings"]').first();
  const isSettingsOpen = await settingsButton.getAttribute('aria-pressed');
  if (isSettingsOpen !== 'true') {
    await settingsButton.click();
    await page.waitForTimeout(300);
  }

  // The Private Uploads panel should be visible in the sidebar
  // Find and expand it if collapsed
  const privateUploadsPanel = page.locator('button:has-text("Toggle panel: Private Uploads")');
  if (await privateUploadsPanel.isVisible()) {
    const isExpanded = await privateUploadsPanel.getAttribute('aria-expanded');
    if (isExpanded === 'false') {
      await privateUploadsPanel.click();
      await page.waitForTimeout(300);
    }
  }

  // Click the "Select files" button to open the media modal
  const selectFilesButton = page.locator('button:has-text("Select files")');
  await expect(selectFilesButton).toBeVisible({ timeout: 5000 });
  await selectFilesButton.click();

  // Wait for the media modal to open
  await page.waitForSelector('.media-modal', { timeout: 5000 });

  // The modal should be open - now we need to upload a file
  // In the media modal, there's an "Upload files" tab
  const uploadTab = page.locator('.media-modal .media-menu-item:has-text("Upload files")');
  if (await uploadTab.isVisible()) {
    await uploadTab.click();
    await page.waitForTimeout(300);
  }

  // Find the file input inside the media modal's uploader
  const fileInput = page.locator('.media-modal input[type="file"]');
  await fileInput.setInputFiles('./tests/_data/sample.pdf');

  // Wait for the upload to complete - check that no error message appears
  // and the "Uploading" section disappears
  await page.waitForTimeout(2000); // Give upload time to process

  // Check for upload errors
  const uploadError = page.locator('.media-modal:has-text("An error occurred")');
  // fail test
  await expect(uploadError).not.toBeVisible({timeout: 5 * 1000})

  // Wait for an attachment to be available in the media library
  // The modal shows attachments as li.attachment elements
  await page.waitForSelector('.media-modal .attachment', { timeout: 10000 });

  // Select the first/most recent attachment
  // WordPress media library attachments are role="checkbox", so use check()
  const firstAttachment = page.locator('.media-modal .attachments li.attachment').first();
  await firstAttachment.check();

  // Wait for the attachment to be marked as checked
  await expect(firstAttachment).toBeChecked({ timeout: 5000 });

  // Click the "Use this item" button - wait for it to be enabled
  const useButton = page.locator('.media-modal button:has-text("Use this item")');
  await expect(useButton).toBeEnabled({ timeout: 5000 });
  await useButton.click();

  // Wait for modal to close
  await page.waitForSelector('.media-modal', { state: 'hidden', timeout: 5000 });

  // After the file is selected, the panel should update to show the attachment
  // The JS adds items to .private-media-library-post-attachments list
  // In the sidebar, look for list items in the Private Uploads panel
  const attachmentsList = page.locator('.private-media-library-post-attachments');
  await expect(attachmentsList.locator('li')).toHaveCount(1, { timeout: 5000 });

  // Verify the uploaded file appears (checking for an image or link in the list)
  const attachmentItem = attachmentsList.locator('li').first();
  await expect(attachmentItem).toBeVisible();

  // Save the page to persist the attachment relationship
  // Use keyboard shortcut since "Save draft" may show as "Saved"
  await page.keyboard.press('Meta+s');

  // Wait for save to complete
  await page.waitForTimeout(2000);

  // Reload the page to verify persistence
  await page.reload();

  // Wait for editor to load again - look for the page title in the header area
  await page.waitForSelector('.edit-post-header, .editor-header', { timeout: 15000 });

  // Open settings sidebar if needed after reload
  const settingsButtonAfterReload = page.locator('.editor-header button[aria-label="Settings"], .edit-post-header button[aria-label="Settings"]').first();
  const isSettingsOpenAfterReload = await settingsButtonAfterReload.getAttribute('aria-pressed');
  if (isSettingsOpenAfterReload !== 'true') {
    await settingsButtonAfterReload.click();
    await page.waitForTimeout(300);
  }

  // Ensure Private Uploads panel is expanded
  const privateUploadsPanelAfterReload = page.locator('button:has-text("Toggle panel: Private Uploads")');
  if (await privateUploadsPanelAfterReload.isVisible()) {
    const isExpanded = await privateUploadsPanelAfterReload.getAttribute('aria-expanded');
    if (isExpanded === 'false') {
      await privateUploadsPanelAfterReload.click();
      await page.waitForTimeout(300);
    }
  }

  // Verify the attachment is still listed after reload
  const attachmentsListAfterReload = page.locator('.private-media-library-post-attachments');
  await expect(attachmentsListAfterReload.locator('li')).toHaveCount(1, { timeout: 5000 });

});
