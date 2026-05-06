'use strict';

export function initCodeEditor() {
	document.addEventListener( 'DOMContentLoaded', function () {
		if ( typeof window.optiz === 'undefined' || ! window.optiz.codeEditor ) return;

		const settings = window.optiz.codeEditor.settings;
		const mimeMap = window.optiz.codeEditor.mimeMap;

		document.querySelectorAll( '.optiz-code-editor' ).forEach( function ( el ) {
			const mode = mimeMap[ el.dataset.codeType ] || 'text/plain';
			const c = Object.assign( {}, settings, {
				codemirror: Object.assign( {}, settings.codemirror, { mode: mode } ),
			} );
			wp.codeEditor.initialize( el, c );
		} );
	} );
}
