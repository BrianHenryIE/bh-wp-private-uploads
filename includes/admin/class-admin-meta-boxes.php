<?php
/**
 * Add a file upload metabox to a post type admin ui edit screen
 *
 * TODO: The ids of the attachments should be submitted with the save/update page form and updated with the post id for new posts.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\Admin;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WC_Order;
use WP_Post;
use function BrianHenryIE\WP_Private_Uploads\str_underscores_to_hyphens;

/**
 * @see assets/bh-wp-private-uploads-admin.js
 */
class Admin_Meta_Boxes implements LoggerAwareInterface {
	use LoggerAwareTrait;

	/**
	 * The post currently being displayed in the admin UI.
	 */
	protected ?WP_Post $current_post = null;

	/**
	 * Constructor
	 *
	 * @param Private_Uploads_Settings_Interface $settings The private uploads settings.
	 * @param LoggerInterface                    $logger A PSR logger.
	 */
	public function __construct(
		protected Private_Uploads_Settings_Interface $settings,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * If we're on a nominated page (i.e. configured in the settings, e.g. shop-order), register the metabox,
	 * enqueue the media library JavaScript, and enqueue the post data that will be used by the JavaScript to
	 * set the post type, author and parent post.
	 *
	 * @see wordpress/wp-admin/includes/meta-boxes.php
	 * @hooked add_meta_boxes
	 *
	 * @param string                 $post_type The registered CPT type for this edit screen.
	 * @param WP_Post|WC_Order|mixed $post The actual post instance that is about to be displayed.
	 */
	public function add_meta_box( string $post_type, $post ): void {

		/**
		 * This is added to quickly fix an issue and can be removed after thorough testing.
		 *
		 * @see https://github.com/BrianHenryIE/bh-wp-autologin-urls/issues/23
		 */
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}

		if ( ! in_array( $post_type, array_keys( $this->settings->get_meta_box_settings() ), true ) ) {
			return;
		}

		$meta_box_settings = $this->settings->get_meta_box_settings()[ $post_type ];

		add_meta_box(
			$this->settings->get_post_type_name(),
			'Private Uploads',
			array( $this, 'print_meta_box' ),
			$post_type,
			'side',
			'default',
			$meta_box_settings
		);

		$this->current_post = $post;

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * @hooked admin_enqueue_scripts
	 */
	public function enqueue_scripts(): void {

		$post = $this->current_post;

		if ( null === $post ) {
			return;
		}

		wp_enqueue_media();

		$handle = sprintf(
			'%s-private-uploads-media-library-js',
			$this->settings->get_plugin_slug()
		);

		wp_enqueue_script( $handle );

		$ajax_data = array(
			'private_attachment_post_type' => $this->settings->get_post_type_name(),
			'post_id'                      => $post->ID,
			'post_author'                  => $post->post_author,
			'modal_title'                  => 'Private Uploads Media Upload',
			'modal_text'                   => 'Use this item',
		);

		$ajax_data_json = wp_json_encode( $ajax_data, JSON_PRETTY_PRINT );

		$post_type_name = $this->settings->get_post_type_name();

		$selector_prefix = str_underscores_to_hyphens( $post_type_name );
		$var_name        = $post_type_name . '_private_uploads_media_library_data';

		$script = <<<EOD
var $var_name = $ajax_data_json;

(function( $ ) {
	'use strict';

	jQuery( document ).ready( function( $ ) {

		var selector = '.' + '$selector_prefix' + '-private-media-library';

		registerPrivateUploadsMediaLibrary(
		    selector,
		    $var_name.private_attachment_post_type,
		    $var_name.post_id,
		    $var_name.post_author,
		    $var_name.modal_title,
		    $var_name.modal_text
        );

	});

})( jQuery );
EOD;

		wp_add_inline_script(
			$handle,
			$script,
			'after'
		);
	}

	/**
	 * HTML for the admin display of the private Media Upload modal
	 *
	 * @see self::add_meta_box()
	 *
	 * @param WP_Post                                                                     $post The post object being displayed on the admin edit screen.
	 * @param array{id:string,title:string,callback:callable,args:array<mixed>|null}|null $box The arguments used when registering this box (see above).
	 */
	public function print_meta_box( WP_Post $post, ?array $box = null ): void {

		$nothing_has_been_uploaded_message = 'Nothing has been uploaded yet.';
		$select_files_text                 = 'Select files';
		$remove_files_text                 = 'Remove files';

		$args            = array(
			'post_parent' => $post->ID,
			'post_type'   => $this->settings->get_post_type_name(),
			'post_status' => 'any',
			'order'       => 'ASC',
		);
		$private_uploads = get_posts( $args );

		$attachments_with_thumbnails = array_filter(
			$private_uploads,
			function ( $upload_post ) {

				$upload_post->post_type = 'attachment';
				wp_cache_set( $upload_post->ID, $upload_post, 'posts' );

				/**
				 *
				 *
				 * @var bool|array{0:string,1:int,2:int,3:bool} $image_src_array
				 */
				$image_src_array = wp_get_attachment_image_src( $upload_post->ID, 'thumbnail' );

				return ! empty( $image_src_array );
			}
		);

		$selector_prefix = str_underscores_to_hyphens( $this->settings->get_post_type_name() );

		printf(
			'<div id="%s-private-media-library-meta-box-input">',
			esc_attr( $selector_prefix )
		);

		if ( ! empty( $attachments_with_thumbnails ) ) {
			$upload_post = $attachments_with_thumbnails[ array_key_first( $attachments_with_thumbnails ) ];
			/**
			 *
			 *
			 * @var bool|array{0:string,1:int,2:int,3:bool} $image_src_array
			 */
			$image_src_array = wp_get_attachment_image_src( $upload_post->ID, 'thumbnail' );

			if ( is_array( $image_src_array ) ) {
				add_thickbox();

				$image_src  = $image_src_array[0];
				$image_href = wp_get_attachment_url( $upload_post->ID );

				if ( false !== $image_href ) {
					?>
				<a href="<?php echo esc_url( $image_href ); ?>?width=900&height=800" class="thickbox">
					<img class="image-preview-private" style="width: 100%; height: auto;" src="<?php echo esc_url( $image_src ); ?>"/>
				</a>
					<?php
				}
			}
		}

		echo '<ul class="private-media-library-post-attachments">';
		if ( ! empty( $private_uploads ) ) {
			foreach ( $private_uploads as $upload_post ) {
				// Change post_type to 'attachment' so `wp_get_attachment_url()` works correctly.
				$upload_post->post_type = 'attachment';
				wp_cache_set( $upload_post->ID, $upload_post, 'posts' );

				$attachment_href = wp_get_attachment_url( $upload_post->ID );
				if ( false !== $attachment_href ) {
					echo '<li><a href="' . esc_url( $attachment_href ) . '">' . esc_html( $upload_post->post_title ) . '</a></li>';
				}
			}
		}
		echo '</ul>';

		if ( empty( $private_uploads ) ) {
			echo '<div class="no-uploads-yet">';

			// TODO: Thickbox.
			echo '<img class="image-preview-private" style="width:100%; height:auto; display:none;"/>';

			printf( '<p>%s</p>', esc_html( $nothing_has_been_uploaded_message ) );

			echo '</div>';
		}

		echo '<p>';

		// TODO: add an E2E tested example implementation in development-plugin.
		printf(
			'<button class="button %s-private-media-library">%s</button>',
			esc_attr( $selector_prefix ),
			esc_html( $select_files_text )
		);
		if ( ! empty( $private_uploads ) ) {
			printf(
				'<button class="button %s-private-media-library remove-files">%s</button>',
				esc_attr( $selector_prefix ),
				esc_html( $remove_files_text )
			);
		}
		echo '</p>';
		echo '</div>';
	}
}
