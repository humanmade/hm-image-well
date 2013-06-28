<?php

class Upload_Image_Well {

	public $allowed_extensions;
	public $drop_text;

	function __construct( $id, $attachment_id, $args = array() ) {

		$this->id 				= $id;
		$this->attachment_id 	= $attachment_id;

		$args = wp_parse_args( $args, array(

			'allowed_extensions'  => array( 'jpg', 'jpeg', 'png', 'gif' ),
			'drop_text'           => __( 'Drop image here', 'imagewell' ),
			'size'                => 'width=440&height=200&crop=1',
			'html_fields'         => array(),
			'select_button_text'  => 'Select Files',
		) );

		$this->size = wp_parse_args( $args['size'] );

		$this->drop_text 		    = $args['drop_text'];
		$this->allowed_extensions   = $args['allowed_extensions'];
		$this->args 				= $args;

		$this->html_fields = $args['html_fields'];

		if ( empty( $this->size['width'] ) )
			$this->size['width'] = '440';

		if ( empty( $this->size['height'] ) )
			$this->size['height'] = '200';


		if ( ! isset( $this->size['crop'] ) )
			$this->size['crop'] = '1';

		if ( ! is_string( $this->size ) )
			$this->size_str = sprintf( 'width=%d&height=%d&crop=%s', $this->size['width'], $this->size['height'], $this->size['crop'] );

		else
			$this->size_str = $this->size;

		$this->allowed_mime_types = array();
		foreach ( $this->allowed_extensions as $ext )
			$this->allowed_mime_types[] = end( wp_check_filetype( 'file.' . $ext ) );

	}

	static function enqueue_scripts() {

		require_once(ABSPATH . 'wp-admin/includes/template.php');

		// Enqueue same scripts and styles as for file field
		wp_enqueue_script( 'plupload-all' );

		wp_enqueue_script( 'well-plupload-image', IMAGE_WELL_URL . '/assets/plupload-image.js', array( 'wp-ajax-response', 'plupload-all' ), IMAGE_WELL_VERSION );

		wp_localize_script( 'well-plupload-image', 'tf_well_plupload_defaults', array(
			'runtimes'				=> 'html5,silverlight,flash,html4',
			'file_data_name'		=> 'async-upload',
			'multiple_queues'		=> true,
			'max_file_size'			=> wp_max_upload_size().'b',
			'url'					=> admin_url('admin-ajax.php'),
			'flash_swf_url'			=> includes_url( 'js/plupload/plupload.flash.swf' ),
			'silverlight_xap_url'	=> includes_url( 'js/plupload/plupload.silverlight.xap' ),
			'filters'				=> array( array( 'title' => __( 'Allowed Image Files' ), 'extensions' => apply_filters( 'image_well_pl_upload_allowed_extensions', 'jpg,jpeg,png,gif' ) ) ),
			'multipart'				=> true,
			'urlstream_upload'		=> true,
			// additional post data to send to our ajax hook
			'multipart_params'		=> array(
				'_ajax_nonce'	=> wp_create_nonce( 'plupload_image' ),
				'action'    	=> 'hm_image_upload_well'
			)

		));

		//wp_enqueue_style( 'well-upload-image', IMAGE_WELL_URL . '/assets/plupload-image.css' );
	}

	/**
	 * Upload
	 * Ajax callback function
	 *
	 * @return error or (XML-)response
	 */
	static function handle_upload () {

		header( 'Content-Type: text/html; charset=UTF-8' );

		if ( ! defined('DOING_AJAX' ) )
			define( 'DOING_AJAX', true );

		check_ajax_referer('plupload_image');

		$post_id = 0;

		if ( isset( $_REQUEST['post_id'] ) && is_numeric( $_REQUEST['post_id'] ) )
			$post_id = (int) $_REQUEST['post_id'];

		// you can use WP's wp_handle_upload() function:
		$file = $_FILES['async-upload'];
		$file_attr = wp_handle_upload( $file, array('test_form'=>true, 'action' => 'hm_image_upload_well') );

		$attachment = array (
			'post_mime_type'	=> $file_attr['type'],
			'post_title'		=> preg_replace( '/\.[^.]+$/', '', basename( $file['name'] ) ),
			'post_content'		=> '',
			'post_status'		=> 'inherit',

		);

		// Adds file as attachment to WordPress
		$id = wp_insert_attachment( $attachment, $file_attr['file'], $post_id );

		if ( ! is_wp_error( $id ) ) {

			$response = new WP_Ajax_Response();

			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file_attr['file'] ) );

			if ( isset( $_REQUEST['field_id'] ) ) {
				// Save file ID in meta field
				add_post_meta( $post_id, $_REQUEST['field_id'], $id, false );
			}

			$src = wp_get_attachment_image_src( $id, $_REQUEST['size'], true );

			$response_data = array(
				'what'			=>'tf_well_image_response',
				'data'			=> $id,
				'supplemental'	=> array(
					'thumbnail'	=>  $src[0],
					'edit_link'	=> get_edit_post_link($id),
				)
			);

			$response_data = apply_filters( 'image-well-response', $response_data, $id, $_REQUEST );

			$response->add( $response_data );
			
			$response->send();
		}

		exit;
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @return void
	 */
	public function admin_print_styles()
	{
		return self::enqueue_scripts();
	}

	/**
	 * Get field HTML
	 */
	public function html()  {
		// Filter to change the drag & drop box background string
		$drop_text = $this->drop_text;
		$extensions = implode( ',', $this->allowed_extensions );
		$img_prefix		= $this->id;
		$style = sprintf( 'width: %dpx; height: %dpx;', $this->size['width'], $this->size['height'] );

		?>
		<div style="<?php echo $style ?>" class="hm-uploader <?php echo $this->attachment_id ? 'with-image' : '' ?>" id="<?php echo $img_prefix ?>-container">

			<input type='hidden' class='field-id rwmb-image-prefix' value="<?php echo $img_prefix ?>" />
			<input type='hidden' class='field-val' name='<?php echo $this->id ?>' value='<?php echo $this->attachment_id ?>' />

			<?php foreach ( $this->html_fields as $field ) : ?>
				<input type='hidden' class='<?php echo $field['class'] ?>' name='<?php echo $field['name'] ?>' value="<?php echo $field['value'] ?>" />
			<?php endforeach ?>

			<div style="<?php echo $style ?><?php echo $this->attachment_id ? '' : 'display: none;' ?> line-height: <?php echo $this->size['height'] ?>px;" class="current-image">
				<?php if ( $this->attachment_id && wp_get_attachment_image( $this->attachment_id, $this->size, false, 'id=' . $this->id ) ) : ?>
				<?php echo wp_get_attachment_image( $this->attachment_id, $this->size, false, 'id=' . $this->id ) ?>
				<?php else : ?>
				<img src="" />
				<?php endif; ?>
				<div class="image-options">
					<a href="#" class="delete-image"><?php _e( 'Delete', 'themeforce'); ?></a>
				</div>
			</div>

			<div style="<?php echo $style ?>; background: #238AC2" class="hover-drag-block hidden">
				Drop Here!
			</div>

			<style>
				.hm-uploader.drag-hover .hover-drag-block { display: block !important; font-size: 24px; color: #fff; text-align: center; line-height: 180px; border-radius: 10px; box-shadow: inset 0 1px 3px 1px rgba(0,0,0,0.2);}
				.hm-uploader.drag-hover .upload-form { opacity: 0; }
			</style>

			<div style='<?php echo $style ?>' id='<?php echo $img_prefix ?>-dragdrop' data-extensions='<?php echo $extensions ?>' data-size='<?php echo $this->size_str ?>' class='rwmb-drag-drop upload-form'>
				<div class = 'rwmb-drag-drop-inside'>
					<span class="drop-text"><?php echo $drop_text ?></span>
					<span><input id='<?php echo $img_prefix ?>-browse-button' type='button' value='<?php echo $this->args['select_button_text'] ?>' class='button' /></span>
				</div>
			</div>

			<div style="<?php echo $style ?>; background: white" class="loading-block hidden">
				<img src="<?php echo IMAGE_WELL_URL . 'assets/ajax-loader.gif'; ?>" />
			</div>

		</div>

		<?php
	}
}