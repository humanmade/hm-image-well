var tf_image_uploaders = {};
var totalRWMB = 0;

var ImageWellController = new function() {

	var self = this;

	self.addFileUploadCallbackForImageWell = function( well_id, callback ) {

		if ( ! tf_image_uploaders[ well_id ] ) {

			setTimeout( function() {
				self.addFileUploadCallbackForImageWell( well_id, callback );
			}, 1000 );

			return;
		}

		tf_image_uploaders[ well_id ].bind( 'FileUploaded', function( a, b, c ) {

			var arguments = arguments;

			c = jQuery.parseXML( c.response );
			
			c = wpAjax.parseAjaxResponse( c, 'ajax-response' ).responses[0];
			// we set a timeout, else the input value won't be set yet.
			setTimeout( function() {

			   callback( a, b, c );

			}, 100 );

		} );

	}

	self.addDeleteFileUploadCallbackForImageWell = function( well_id, callback ) {
		
		if ( ! tf_image_uploaders[ well_id ] ) {

			setTimeout( function() {
				self.addDeleteFileUploadCallbackForImageWell( well_id, callback );
			}, 1000 );

			return;
		}

		jQuery( '#' + well_id + '-container .delete-image' ).click( function(e) {
			// we set a timeout, else the input value won;t be set yet.
			setTimeout( function() {

				callback();

			}, 100 );
		});
	}

	self.clearDataForImageWell = function( well_id ) {
		
		jQuery( '#' + well_id + '-container .delete-image' ).click();
	}
}

jQuery( document ).ready( function($) {
	// Object containing all the plupload uploaders
	var 
		hundredMB				= null,
		max						= null
	;

	$( '.delete-image' ).bind( 
		'click',
		function(e)
		{
			e.preventDefault()
			var uploader = jQuery( this ).closest( '.hm-uploader' )
			
			uploader.removeClass( 'with-image' )
			uploader.find( '.current-image' ).fadeOut('fast', function() {
				uploader.removeClass('with-image');
			})
			uploader.find( '.upload-form' ).show()
			uploader.find( '.field-val' ).val( '' )
		}
	);

	// Using all the image prefixes
	totalRWMB = $( 'input:hidden.rwmb-image-prefix' ).length
	
	$( 'input:hidden.rwmb-image-prefix' ).each( function() { CMBInitImageWell( this ); } );


});

function CMBInitImageWell( obj ) {
	prefix = jQuery( obj ).val();
	
	var input = jQuery( obj )
	// Adding container, browser button and drag ang drop area
	var tf_well_plupload_init = jQuery.extend( 
		{
			container:		prefix + '-container',
			browse_button:	prefix + '-browse-button',
			drop_element:	prefix + '-dragdrop'
		},
		tf_well_plupload_defaults
	);
	
	tf_well_plupload_init.multipart_params.field_id = prefix;
	tf_well_plupload_init.multipart_params.size = input.parent().find( '.upload-form' ).attr( 'data-size' );

	if ( totalRWMB == 1 )
		tf_well_plupload_init.filters[0].extensions = input.parent().find( '.upload-form' ).attr( 'data-extensions' );

	// Create new uploader
	tf_image_uploaders[ prefix ] = new plupload.Uploader( jQuery.extend( true, {}, tf_well_plupload_init ) );

	tf_image_uploaders[ prefix ].init();
	//
	tf_image_uploaders[ prefix ].bind( 
		'FilesAdded', 
		function( up, files )
		{
			hundredMB	= 100 * 1024 * 1024, 
			max			= parseInt( up.settings.max_file_size, 10 );
			plupload.each(
				files, 
				function( file )
				{
					input.closest( '.hm-uploader' ).find( '.loading-block' ).fadeIn('fast', function() {
					
						input.closest( '.hm-uploader' ).addClass( 'loading' );
					
					} );
				}
			);
			up.refresh();
			up.start();
		}
	);

	tf_image_uploaders[ prefix ].bind(
		'FileUploaded', 
		function( up, file, response ) 
		{	
			response_xml = jQuery.parseXML( response.response );
				res = wpAjax.parseAjaxResponse( response_xml, 'ajax-response' );
				if ( false === res.errors )
				{
					res		= res.responses[0];
					img_id	= res.data;
					img_src	= res.supplemental.thumbnail;
					img_edit = res.supplemental.edit_link;
					
					jQuery(input).closest('.hm-uploader').find( '.loading-block' ).fadeOut('fast' )
					
					setTimeout( function() {
						jQuery(input).closest('.hm-uploader').find( '.current-image img' )
							.attr('src',img_src).removeAttr( 'width' ).removeAttr('height');
					
					}, 2000 );

					
					jQuery(input).closest('.hm-uploader').find( '.current-image' ).show();
					
					setTimeout( function() { jQuery(input).closest('.hm-uploader').find( '.current-image' ).fadeIn('fast', function() {
						
						jQuery(input).closest('.hm-uploader').removeClass( 'loading' );
						jQuery(input).closest('.hm-uploader').addClass( 'with-image' ); 
						
					} ) }, 100 )
					
					jQuery(input).closest('.hm-uploader').find( '.field-val' ).val( img_id )
				}

			
		});

	tf_image_uploaders[ prefix ].bind( 'Error', function( up, error ) {
		alert( 'Error: ' + error.message );
	} );
}