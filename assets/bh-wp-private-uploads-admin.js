function registerPrivateUploadsMediaLibrary( selector, private_attachment_post_type, post_id, post_author, modal_title, modal_text ) {

	// Uploading files
	var file_frame;
	var wp_media_post_id;
	var set_to_post_id = post_id;

	var realAjaxUrl = wp.ajax.settings.url

	jQuery( selector ).on('click', function( event ){

		wp_media_post_id = wp.media.model.settings.post.id; // Store the old id

		event.preventDefault();

		// If the media frame already exists, reopen it.
		if ( file_frame ) {
			// Set the post ID to what we want
			file_frame.uploader.uploader.param( 'post_id', set_to_post_id );

			// For uploading.
			window.wp.Uploader.defaults.multipart_params.post_type = private_attachment_post_type;

			// For querying.
			wp.ajax.settings.url = realAjaxUrl + '?post_type=' + private_attachment_post_type;

			// Open frame
			file_frame.open();
			return;
		} else {
			// Set the wp.media post id so the uploader grabs the ID we want when initialised
			wp.media.model.settings.post.id = set_to_post_id;
		}

		// Create the media frame.
		file_frame = wp.media.frames.file_frame = wp.media({
			title: modal_title,
			button: {
				text: modal_text,
			},
			multiple: true
		});

		// When an image is selected, run a callback.
		file_frame.on( 'select', function() {
			// We set multiple to false so only get one image from the uploader
			var attachment = file_frame.state().get('selection').first().toJSON();

			// Do something with attachment.id and/or attachment.url here

			// TODO: set attachment.url to a data tag on the image
			// TODO: set ALT/title text

			var meta_box_id = '#' + private_attachment_post_type;

			if( attachment.sizes ) {
				jQuery(meta_box_id + ' .no-uploads-yet').css('display', 'none');
				jQuery(meta_box_id + ' .image-preview-private').attr('src', attachment.sizes['medium'].url);
				jQuery(meta_box_id + ' .image-preview-private').css('display', '');
				// jQuery(meta_box_id + ' .image_attachment_id_private').val(attachment.id);
			}

			// Restore the main post ID
			wp.media.model.settings.post.id = wp_media_post_id;

			window.wp.Uploader.defaults.multipart_params.post_type = null;
			wp.ajax.settings.url = realAjaxUrl;

			// TODO: Trigger here. E.g. when an image is selected... the order status should be packed.
		});

		// For uploading.
		window.wp.Uploader.defaults.multipart_params.post_type = private_attachment_post_type;

		// For querying.
		wp.ajax.settings.url = realAjaxUrl + '?post_type=' + private_attachment_post_type;

		// Finally, open the modal
		file_frame.open();
	});

	// Restore the main ID when the add media button is pressed
	jQuery( 'a.add_media' ).on( 'click', function() {
		wp.media.model.settings.post.id = wp_media_post_id;

		window.wp.Uploader.defaults.multipart_params.post_type = null;
		wp.ajax.settings.url = realAjaxUrl;
	});
}
