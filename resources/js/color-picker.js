'use strict';

import Coloris from '@melloware/coloris';
import '@melloware/coloris/dist/coloris.css';

export function initColorPicker() {
	document.addEventListener( 'DOMContentLoaded', function () {
		const pickers = document.querySelectorAll( '.optiz-color-picker' );
		if ( ! pickers.length ) return;

		Coloris.init();
		Coloris( { el: '.optiz-color-picker', themeMode: 'auto', format: 'hex', alpha: false } );

		pickers.forEach( function ( el ) {
			const format = el.dataset.format || 'hex';
			const alpha = el.dataset.alpha === '1';
			const paletteRaw = el.dataset.palette;

			// Coloris uses 'rgb' format for both rgb and rgba; alpha flag controls opacity.
			const instanceConfig = {
				format: format === 'rgba' ? 'rgb' : format,
				alpha,
			};

			if ( paletteRaw ) {
				try {
					const swatches = JSON.parse( paletteRaw );
					if ( swatches.length ) {
						instanceConfig.swatches = swatches;
					}
				} catch ( _e ) {}
			}

			Coloris.setInstance( '#' + el.id, instanceConfig );
		} );
	} );
}
