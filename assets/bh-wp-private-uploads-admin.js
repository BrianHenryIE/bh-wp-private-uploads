function registerPrivateUploadsMediaLibrary( selector, post_type, post_id, post_author ) {

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
			window.wp.Uploader.defaults.multipart_params.post_type = post_type;

			// For querying.
			wp.ajax.settings.url = realAjaxUrl + '?post_type=' + post_type;

			// Open frame
			file_frame.open();
			return;
		} else {
			// Set the wp.media post id so the uploader grabs the ID we want when initialised
			wp.media.model.settings.post.id = set_to_post_id;
		}

		// Create the media frame.
		file_frame = wp.media.frames.file_frame = wp.media({
			title: 'Fulfillment Photos',
			button: {
				text: 'Use this image',
			},
			multiple: false
		});

		// When an image is selected, run a callback.
		file_frame.on( 'select', function() {
			// We set multiple to false so only get one image from the uploader
			var attachment = file_frame.state().get('selection').first().toJSON();

			// Do something with attachment.id and/or attachment.url here
			$( '#image-preview-private' ).attr( 'src', attachment.url ).css( 'width', 'auto' );
			$( '#image_attachment_id_private' ).val( attachment.id );

			// Restore the main post ID
			wp.media.model.settings.post.id = wp_media_post_id;

			window.wp.Uploader.defaults.multipart_params.post_type = null;
			wp.ajax.settings.url = realAjaxUrl;

			// TODO: When an image is selected... the order status should be packed.
		});

		// For uploading.
		window.wp.Uploader.defaults.multipart_params.post_type = post_type;

		// For querying.
		wp.ajax.settings.url = realAjaxUrl + '?post_type=' + post_type;

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
