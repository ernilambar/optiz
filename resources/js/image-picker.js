'use strict';

export function initImagePicker() {
	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.optiz-image-field' ).forEach( function ( w ) {
			const u = w.querySelector( '.optiz-image-url' );
			const up = w.querySelector( '.optiz-upload-image' );
			const rm = w.querySelector( '.optiz-remove-image' );
			const pv = w.querySelector( '.optiz-image-preview' );
			const pi = pv ? pv.querySelector( 'img' ) : null;

			if ( ! up || ! u ) return;

			let fr;

			up.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				if ( ! fr ) {
					fr = wp.media( {
						title: 'Select Image',
						button: { text: 'Use this image' },
						multiple: false,
						library: { type: 'image' },
						states: [
							new wp.media.controller.Library( {
								title: 'Select Image',
								library: wp.media.query( { type: 'image' } ),
								multiple: false,
								date: false,
								priority: 20,
								displaySettings: true,
								displayUserSettings: false,
							} ),
						],
					} );
					fr.on( 'select', function () {
						const sel = fr.state().get( 'selection' ).first();
						const a = sel.toJSON();
						const display = fr.state().display( sel ).toJSON();
						const size = display.size || 'full';
						const url =
							a.sizes && a.sizes[ size ] && a.sizes[ size ].url
								? a.sizes[ size ].url
								: a.url;
						u.value = url;
						if ( pi ) {
							pi.src = url;
						}
						if ( pv ) {
							pv.style.display = '';
						}
						if ( rm ) {
							rm.style.display = '';
						}
					} );
				}
				fr.open();
			} );

			if ( rm ) {
				rm.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					u.value = '';
					if ( pi ) {
						pi.src = '';
					}
					if ( pv ) {
						pv.style.display = 'none';
					}
					rm.style.display = 'none';
				} );
			}
		} );
	} );
}
