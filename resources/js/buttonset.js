'use strict';

export function initButtonset() {
	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.optiz-buttonset' ).forEach( function ( bs ) {
			bs.addEventListener( 'change', function ( e ) {
				if ( e.target.type !== 'radio' ) return;
				bs.querySelectorAll( '.optiz-buttonset-item' ).forEach( function ( item ) {
					item.classList.toggle(
						'is-active',
						item.querySelector( 'input[type="radio"]' ) === e.target
					);
				} );
			} );
		} );
	} );
}
