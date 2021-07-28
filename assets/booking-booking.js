function BookingBookingCB () {
	( function ( $ ) {
		$( '.places-result' ).hide();
		$( '.places-lookup' ).each( function ( ix, e ) {
			var $e = $( e );
			var name      = $e.attr( 'data-name' );
			var $real     = $e.find( '.acf-input input, .acf-input textarea' );

			var searchOpts = { fields: [ 'address_components', 'formatted_address', 'icon', 'name' ] };
			if ( BookingBookingOpts.placesBias ) {
				const center = BookingBookingOpts.placesBias;
				const defaultBounds = {
					north: center.lat + 0.2,
					south: center.lat - 0.2,
					east: center.lng + 0.2,
					west: center.lng - 0.2,
				};
				searchOpts.strictBounds = false;
				searchOpts.bounds = defaultBounds;
				searchOpts.origin = center;
			}
			console.log( searchOpts );
			var searchBox = new google.maps.places.Autocomplete( $real[0], searchOpts );
			searchBox.addListener( 'place_changed', function () {
				const place = searchBox.getPlace();
				console.log( place );
				$( '.places-result-for-' + name ).show();
				var structured = { lines: [], city: null, region: null, postcode: null, country: null };
				var components = [ 'city', 'region', 'postcode', 'country' ];
				var skip_next = false;
				var count = place.address_components.length;
				for ( var i = 0; i < count; i++ ) {
					if ( skip_next ) {
						skip_next = false;
						continue;
					}
					var line = place.address_components[i];
					var peek = place.address_components[ 1 + i ]
					if ( line.types.includes( "street_number" ) && peek && peek.types.includes( "route" ) ) {
						structured.lines.push( line.long_name + " " + peek.long_name );
						skip_next = true;
					}
					else if ( line.types.includes( "postal_code" ) ) {
						structured.postcode = line.long_name;
					}
					else if ( line.types.includes( "country" ) ) {
						structured.country = line.long_name;
					}
					else if ( line.types.includes( "postal_town" ) ) {
						structured.city1 = line.long_name;
					}
					else if ( line.types.includes( "locality" ) && peek && ! peek.types.includes( "locality" ) ) {
						structured.city2 = line.long_name;
					}
					else if ( line.types.includes( "locality" ) && ! peek ) {
						structured.city3 = line.long_name;
					}
					else if ( line.types.includes( "administrative_area_level_1" ) ) {
						structured.region1 = line.long_name;
					}
					else if ( line.types.includes( "administrative_area_level_2" ) ) {
						structured.region2 = line.long_name;
					}
					else {
						structured.lines.push( line.long_name );
					}
				}
				structured.city   ||= structured.city1   || structured.city2  || structured.city3;
				structured.region ||= structured.region2 || structured.region1;
				if ( structured.region == structured.city ) {
					structured.region = null;
				}
				console.log( structured );
				for ( var i = 0; i < 10; i++ ) {
					var j = i + 1;
					var $dest = $( '.places-result-' + j + '.places-result-for-' + name );
					if ( $dest.length > 0 ) {
						var val = ( i < structured.lines.length ) ? structured.lines[i] : '';
						$dest.find('input,textarea').val( val );
					}
				}
				for ( var i in components ) {
					var j = components[i];
					var $dest = $( '.places-result-' + j + '.places-result-for-' + name );
					if ( $dest.length > 0 ) {
						var val = ( structured[j] === null ) ? '' : structured[j];
						$dest.find('input,textarea').val( val );
					}
				}
			} );
		} );

	} )( jQuery );
}

jQuery( document ).ready( function ( $ ) {

	var $lookups = $( '.places-lookup' );

	if ( $lookups.length > 0 ) {
		var jsURL =
			'https://maps.googleapis.com/maps/api/js?key=' +
			BookingBookingOpts.placesApiKey +
			'&libraries=places&callback=BookingBookingCB';
		$.getScript( jsURL );
	}
} );
