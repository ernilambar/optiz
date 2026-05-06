'use strict';

import Coloris from '@melloware/coloris';
import '@melloware/coloris/dist/coloris.css';

export function initColorPicker() {
	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! document.querySelector( '.optiz-color-picker' ) ) return;
		Coloris.init();
		Coloris( {
			el: '.optiz-color-picker',
			format: 'hex',
			alpha: false,
			themeMode: 'auto',
		} );
	} );
}
