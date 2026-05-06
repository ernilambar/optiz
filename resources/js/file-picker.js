'use strict';

export function initFilePicker() {
	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.optiz-file-field' ).forEach( function ( w ) {
			const u = w.querySelector( '.optiz-file-url' );
			const up = w.querySelector( '.optiz-upload-file' );
			const rm = w.querySelector( '.optiz-remove-file' );

			if ( ! up || ! u ) return;

			let fr;

			up.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				if ( ! fr ) {
					fr = wp.media( {
						title: 'Select File',
						button: { text: 'Use this file' },
						multiple: false,
					} );
					fr.on( 'select', function () {
						const a = fr.state().get( 'selection' ).first().toJSON();
						u.value = a.url;
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
					rm.style.display = 'none';
				} );
			}
		} );
	} );
}
