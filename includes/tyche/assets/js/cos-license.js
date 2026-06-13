
jQuery( function( $ )  {
	var settings = {
		init: function() {
			$( document ).on(
				'click',
				'#edd_cos_license_deactivate, #edd_cos_license_activate',
				settings.save_license
			);
		},

		save_license: function() {
			var license_key = $( '#edd_license_key_cos' ).val();
			var action = 'Deactivate' === $( this ).val() ? 'cos_deactivate_license' : 'cos_activate_license';
			var key = 'Deactivate' === $( this ).val() ? $( '#edd_cos_license_deactivate' ).val() : $( '#edd_cos_license_activate' ).val();

			var data = { 
				action: action,
				edd_cos_license_activate: key,
				edd_cos_license_deactivate: key,
				license_key: license_key,
			};

			$.ajax({
				type: 'POST',
				url: localizeStrings.ajax_url,
				data: data,
				success: function( response ) {
					// Check the response
					if( 'valid' === response ) {
						// Hide the activate button and show the deactivate button
						$( '#edd_cos_license_activate' ).hide();
						$( '#edd_cos_license_deactivate' ).show();
						$('.mode-deactive').show();
						$('.mode-active').hide();
					} else {
						// Hide the deactivate button and show the activate button
						$( '#edd_cos_license_deactivate' ).hide();
						$( '#edd_cos_license_activate' ).show();
						$('.mode-active').show();
						$('.mode-deactive').hide();
					}
					window.location.reload();
				},
				complete: function() {
					// Reload the page
					window.location.reload();
				}
			});
		}
	}
	settings.init();
});