<?php
/**
 * Add a file upload metabox admin pages.
 *
 * TODO: The ids of the attachments should be submitted with the save/update page form and updated with the post id for new posts.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\Admin;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WP_Post;

class Admin_Meta_Boxes {
	use LoggerAwareTrait;

	/**
	 * The plugin's settings.
	 *
	 * @uses Settings_Interface::get_plugin_basename()
	 * @uses Settings_Interface::get_plugin_version()
	 * @uses Settings_Interface::get_post_type_name()
	 */
	protected Private_Uploads_Settings_Interface $settings;

	protected ?WP_Post $current_post = null;

	/**
	 * Constructor
	 *
	 * @param Private_Uploads_Settings_Interface $settings
	 * @param LoggerInterface                    $logger A PSR logger.
	 */
	public function __construct( Private_Uploads_Settings_Interface $settings, LoggerInterface $logger ) {
		$this->setLogger( $logger );
		$this->settings = $settings;
	}

	/**
	 * If we're on a shop order page, register the metabox, enqueue the media library JavaScript, and enqueue the
	 * post data that will be used by the JavaScript to set the post type, author and parent post.
	 *
	 * @hooked add_meta_boxes
	 *
	 * @param string  $post_type The registered CPT type for this edit screen.
	 * @param WP_Post $post The actual post instance that is about to be displayed.
	 */
	public function add_meta_box( string $post_type, WP_Post $post ): void {

		if ( ! in_array( $post_type, array_keys( $this->settings->get_meta_box_settings() ) ) ) {
			return;
		}

		$meta_box_settings = $this->settings->get_meta_box_settings()[ $post_type ];

		add_meta_box(
			$this->settings->get_post_type_name(),
			'Private Uploads',
			array( $this, 'print_meta_box' ),
			$post_type,
			'side'
		);

		$this->current_post = $post;

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * @hooked admin_enqueue_scripts
	 */
	public function enqueue_scripts(): void {

		$post = $this->current_post;

		wp_enqueue_media();

		$handle = "{$this->settings->get_plugin_slug()}-private-uploads-media-library-js";

		wp_enqueue_script( $handle );

		$post_author = 0;

		// $post_author = $post->post_author;
		//
		// $order = wc_get_order( $post->ID );
		// if ( $order instanceof \WC_Order && 0 !== $order->get_customer_id() ) {
		// $post_author = $order->get_customer_id();
		// } else {
		// $post_author = get_current_user_id();
		// }

		$ajax_data = array(
			'private_attachment_post_type' => $this->settings->get_post_type_name(),
			'post_id'                      => $post->ID,
			'post_author'                  => $post_author,
			'modal_title'                  => 'Private Uploads Media Upload',
			'modal_text'                   => 'Use this item',
		);

		$ajax_data_json = wp_json_encode( $ajax_data, JSON_PRETTY_PRINT );

		$plugin_snake = str_replace( '-', '_', $this->settings->get_plugin_slug() );
		$plugin_slug  = $this->settings->get_plugin_slug();

		$var_name = $plugin_snake . '_private_uploads_media_library_data';

		$script = <<<EOD
var $var_name = $ajax_data_json;

(function( $ ) {
	'use strict';

	jQuery( document ).ready( function( $ ) {

		var plugin_slug = '$plugin_slug';
		
		var selector = '.' + plugin_slug + '-private-media-library';

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
	 * @see Admin_Order_UI::add_meta_box()
	 *
	 * @param WP_Post                                                         $post The post object being displayed on the admin edit screen.
	 * @param array{id:string,title:string,callback:callable,args:array|null} $box The arguments used when registering this box (see above).
	 */
	public function print_meta_box( WP_Post $post, array $box = null ): void {

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

		if ( ! empty( $attachments_with_thumbnails ) ) {
			$upload_post = $attachments_with_thumbnails[ array_key_first( $attachments_with_thumbnails ) ];
			/**
			 *
			 *
			 * @var bool|array{0:string,1:int,2:int,3:bool} $image_src_array
			 */
			$image_src_array = wp_get_attachment_image_src( $upload_post->ID, 'thumbnail' );

				add_thickbox();

				/** @var string $image_src */
				$image_src = $image_src_array[0];

				/** @var string $image_href */
				$image_href = wp_get_attachment_url( $upload_post->ID );

			?>
				<a href="<?php echo esc_url( $image_href ); ?>?width=900&height=800" class="thickbox">
					<img class="image-preview-private" style="width: 100%; height: auto;" src="<?php echo esc_url( $image_src ); ?>"/>
				</a>
				<?php
		}

		if ( ! empty( $private_uploads ) ) {
			echo '<ul>';
			foreach ( $private_uploads as $upload_post ) {
				$non_image_attachment_href = wp_get_attachment_url( $upload_post->ID );
				echo '<li><a href="' . esc_url( $non_image_attachment_href ) . '">' . $upload_post->post_title . '</a></li>';
			}
			echo '</ul>';

		} else {

			echo '<div class="no-uploads-yet">';

			// TODO: Thickbox.
			echo '<img class="image-preview-private" style="width:100%; height:auto; display:none;"/>';

			echo "<p>{$nothing_has_been_uploaded_message}</p>";

			echo '</div>';
		}

		echo '<p>';
		// TODO test-plugin
		echo '<button class="button bh-wp-private-uploads-test-plugin-private-media-library">' . $select_files_text . '</button>';
		echo '<button class="button bh-wp-private-uploads-test-plugin-private-media-library remove-files" style="display: none">' . $remove_files_text . '</button>';
		echo '</p>';
	}
}
