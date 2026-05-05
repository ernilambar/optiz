'use strict';

export function initCodeEditor() {
	document.addEventListener( 'DOMContentLoaded', function () {
		if ( typeof optizCodeEditor === 'undefined' ) return;

		const settings = optizCodeEditor.settings;
		const mimeMap = optizCodeEditor.mimeMap;

		document.querySelectorAll( '.optiz-code-editor' ).forEach( function ( el ) {
			const mode = mimeMap[ el.dataset.codeType ] || 'text/plain';
			const c = Object.assign( {}, settings, {
				codemirror: Object.assign( {}, settings.codemirror, { mode: mode } ),
			} );
			wp.codeEditor.initialize( el, c );
		} );
	} );
}
