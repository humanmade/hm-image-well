<?php

class Upload_Image_Well {

	public $save_on_upload;
	public $allowed_extensions;
	public $drop_text;

	function __construct( $id, $attachment_id, $args = array() ) {

		$this->id 				= $id;
		$this->attachment_id 	= $attachment_id;

		$args = wp_parse_args( $args, array(

			'allowed_extensions'  => array( 'jpg', 'jpeg', 'png', 'gif' ),
			'drop_text'           => __( 'Drop image here', 'themeforce' ),
			'size'                => 'width=440&height=200&crop=1',
			'save_on_upload'      => false,
			'display_placeholder' => true,
			'html_fields'         => array()

		) );

		$this->size = wp_parse_args( $args['size'] );

		$this->drop_text 		    = $args['drop_text'];
		$this->allowed_extensions   = $args['allowed_extensions'];
		$this->save_on_upload	    = $args['save_on_upload'];
		$this->display_placeholder  = $args['display_placeholder'];

		$this->html_fields = $args['html_fields'];

		if ( empty( $this->size['width'] ) )
			$this->size['width'] = '440';

		if ( empty( $this->size['height'] ) )
			$this->size['height'] = '200';

		if ( ! is_string( $this->size ) )
			$this->size_str = sprintf( 'width=%d&height=%d&crop=%s', $this->size['width'], $this->size['height'], $this->size['crop'] );

		else
			$this->size_str = $this->size;

	}

	static function enqueue_scripts() {

		require_once(ABSPATH . 'wp-admin/includes/template.php');

		// Enqueue same scripts and styles as for file field
		wp_enqueue_script( 'plupload-all' );

		wp_enqueue_script( 'well-plupload-image', IMAGE_WELL_URL . '/assets/plupload-image.js', array( 'jquery-ui-sortable', 'wp-ajax-response', 'plupload-all' ), IMAGE_WELL_VERSION );

		wp_localize_script( 'well-plupload-image', 'tf_well_plupload_defaults', array(
			'runtimes'				=> 'html5,silverlight,flash,html4',
			'file_data_name'		=> 'async-upload',
			'multiple_queues'		=> true,
			'max_file_size'			=> wp_max_upload_size().'b',
			'url'					=> admin_url('admin-ajax.php'),
			'flash_swf_url'			=> includes_url( 'js/plupload/plupload.flash.swf' ),
			'silverlight_xap_url'	=> includes_url( 'js/plupload/plupload.silverlight.xap' ),
			'filters'				=> array( array( 'title' => __( 'Allowed Image Files' ), 'extensions' => '*' ) ),
			'multipart'				=> true,
			'urlstream_upload'		=> true,
			// additional post data to send to our ajax hook
			'multipart_params'		=> array(
				'_ajax_nonce'	=> wp_create_nonce( 'plupload_image' ),
				'action'    	=> 'plupload_image_upload'
			)

		));

		// wp_enqueue_style( 'well-upload-image', IMAGE_WELL_URL . '/assets/plupload-image.css' );
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
		$file_attr = wp_handle_upload( $file, array('test_form'=>true, 'action' => 'plupload_image_upload') );

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

			$src = wp_get_attachment_image_src( $id, $_REQUEST['size'] );

			$response->add( array(
				'what'			=>'tf_well_image_response',
				'data'			=> $id,
				'supplemental'	=> array(
					'thumbnail'	=>  $src[0],
					'edit_link'	=> get_edit_post_link($id)
				)
			) );
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
	 *
	 * @param string $html
	 * @param mixed  $meta
	 * @param array  $field
	 *
	 * @return string
	 */
	public function html()  {
		// Filter to change the drag & drop box background string
		$drop_text = $this->drop_text;
		$extensions = implode( ',', $this->allowed_extensions );
		$i18n_select	= __('Select Files', 'themeforce');
		$img_prefix		= $this->id;
		$style = sprintf( 'width: %dpx; height: %dpx;', $this->size['width'], $this->size['height'] );

		$html = "<div style='$style' class='hm-uploader " . ( ( $this->attachment_id && $this->display_placeholder ) ? 'with-image' : '' ) . "' id='{$img_prefix}-container'>";

		$html .= "<input type='hidden' class='field-id rwmb-image-prefix' value='{$img_prefix}' />";
		$html .= "<input type='hidden' class='field-val' name='{$this->id}' value='{$this->attachment_id}' />";

		foreach ( $this->html_fields as $field )
			$html .= "<input type='hidden' class='{$field['class']}' name='{$field['name']}' value='{$field['value']}' />";


		echo $html;

		$html = '';
		?>
		<div style="<?php echo $style ?><?php echo ( $this->attachment_id && $this->display_placeholder ) ? '' : 'display: none;' ?> line-height: <?php echo $this->size['height'] ?>px;" class="current-image">
			<?php if ( $this->attachment_id && wp_get_attachment_image( $this->attachment_id, $this->size, false, 'id=' . $this->id ) ) : ?>
			<?php echo wp_get_attachment_image( $this->attachment_id, $this->size, false, 'id=' . $this->id ) ?>
			<?php else : ?>
			<img src="" />
			<?php endif; ?>
			<div class="image-options">
				<a href="#" class="delete-image"><?php _e( 'Delete', 'themeforce'); ?></a>
			</div>
		</div>
		<?php

		// Show form upload
		$html = "
		<div style='{$style}' id='{$img_prefix}-dragdrop' data-extensions='$extensions' data-size='{$this->size_str}' class='rwmb-drag-drop upload-form'>
			<div class = 'rwmb-drag-drop-inside'>
				<p>{$drop_text}</p>
				<p>" . __( 'or', 'themeforce' ) . "</p>
				<p><input id='{$img_prefix}-browse-button' type='button' value='{$i18n_select}' class='button' /></p>
			</div>
		</div>";

		?>
		<div style="<?php echo $style ?>" class="loading-block hidden">
			<img src="<?php echo IMAGE_WELL_URL . 'assets/ajax-loader.gif'; ?>" />
		</div>
		<?php

		$html .= "</div>";

		echo $html;
	}
}