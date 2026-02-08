import { test, expect, Page } from '@playwright/test';
import { readFileSync } from 'fs';

test('upload file', async ({ page }) => {

    // test.skip(browserName === 'webkit', `Webkit doesn't support creating DataTransfer objects, used for drag and drop`)

  await page.goto( 'http://localhost:8888/wp-login.php', {
    waitUntil: 'networkidle',
  } );

  await page.fill( 'input[name="log"]', "admin" );
  await page.fill( 'input[name="pwd"]', "password" );
  await page.locator( '#loginform' ).getByText( 'Log In' ).click();

  await page.waitForLoadState( 'networkidle' );


  await page.goto('http://localhost:8888/wp-admin/media-new.php?post_type=private_media');

  // plupload-upload-ui
  await page.waitForSelector('#plupload-upload-ui');

    // await page.waitForEvent('filechooser');
    await page.waitForTimeout(500);

  // await dragAndDropFile(page, "#plupload-upload-ui", "./tests/_data/sample.pdf", "sample.pdf", "application/pdf");
  // await dragAndDropFile(page, "#drag-drop-area", "./tests/_data/sample.pdf", "sample.pdf", "application/pdf");


    // Read your file into a buffer.
    let fileName = 'sample.pdf';
    const buffer = readFileSync(`./tests/_data/sample.pdf`).toString('base64');

    let dropDiv = await page.locator("#drag-drop-area");

    // Create the DataTransfer and File
    const dataTransfer = await dropDiv.evaluateHandle(( data , { buffer, fileName } ) => {
        const dt = new DataTransfer();
        //convert buffer into hexString
        const hexString = Uint8Array.from(atob(buffer), c => c.charCodeAt(0));
        //create file
        const file = new File([hexString], fileName);
        dt.items.add(file);
        return dt;
    }, { buffer, fileName });

    // Now dispatch
    await dropDiv.dispatchEvent('drop', { dataTransfer: dataTransfer });

  // Copy URL to clipboard
    await expect(
        page.locator( '.copy-attachment-url', {
            hasText: /^Copy URL to clipboard$/,
        } )
    ).toBeVisible();

});


// https://stackoverflow.com/questions/77738800/how-to-drag-a-file-to-a-drag-zone-input-element-using-playwright
const dragAndDropFile = async (
    page: Page,
    selector: string,
    filePath: string,
    fileName: string,
    fileType = ''
) => {
  const buffer = readFileSync(filePath).toString('base64');

  const dataTransfer = await page.evaluateHandle(
      async ({ bufferData, localFileName, localFileType }) => {
        const dt = new DataTransfer();

        const blobData = await fetch(bufferData).then((res) => res.blob());

        const file = new File([blobData], localFileName, { type: localFileType });
        dt.items.add(file);
        return dt;
      },
      {
        bufferData: `data:application/octet-stream;base64,${buffer}`,
        localFileName: fileName,
        localFileType: fileType,
      }
  );

  await page.dispatchEvent(selector, 'drop', { dataTransfer });

  await page.waitForLoadState( 'networkidle' );
};
