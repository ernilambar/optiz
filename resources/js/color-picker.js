'use strict';

export function initColorPicker() {
	document.addEventListener( 'DOMContentLoaded', function () {
		if ( typeof jQuery === 'undefined' || ! jQuery.fn.wpColorPicker ) return;
		jQuery( '.optiz-color-picker' ).wpColorPicker();
	} );
}
