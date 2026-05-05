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
					} );
					fr.on( 'select', function () {
						const a = fr.state().get( 'selection' ).first().toJSON();
						u.value = a.url;
						if ( pi ) {
							pi.src = a.url;
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
