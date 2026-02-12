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
			// Set `post_id` to the post ID we want to attach the file to.
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

		// When the modal is dismissed, run a callback to display the selected image(s) etc.
		file_frame.on( 'select', function() {
			var attachments = file_frame.state().get('selection');

			if (attachments.length !== 0) {

				var meta_box_id = '#' + private_attachment_post_type.replaceAll('_','-') + "-private-media-library-meta-box-input";

				jQuery(meta_box_id + ' .no-uploads-yet').css('display', 'none');

				const unorderedList = jQuery(meta_box_id + ' .private-media-library-post-attachments');

				attachments.each(function(attachment) {
					const attachmentData = attachment.toJSON();

					// TODO: set ALT/title text

					const imgSrc = (attachmentData.sizes && attachmentData.sizes['medium'])
						? attachmentData.sizes['medium'].url
						: attachmentData.icon;

					const li = jQuery('<li>', {
						'class': 'private-media-library-post-attachment',
						'id': private_attachment_post_type + '-' + attachmentData.id
					});
					const img = jQuery('<img>', { 'src': imgSrc });
					li.append(img);
					unorderedList.append(li);
				});
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
