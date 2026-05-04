( function () {
	'use strict';

	if ( typeof optizConditional === 'undefined' || ! optizConditional.rules || ! optizConditional.rules.length ) {
		return;
	}

	const rules = optizConditional.rules;

	const getFieldWrapper = ( fieldId ) => document.querySelector( '[data-field-id="' + fieldId + '"]' );

	const getFieldValue = ( fieldId ) => {
		const inputs = Array.from( document.querySelectorAll( '[name$="[' + fieldId + ']"]' ) );
		if ( ! inputs.length ) return null;

		const checkbox = inputs.find( ( el ) => el.type === 'checkbox' );
		if ( checkbox ) return checkbox.checked;

		const checkedRadio = inputs.find( ( el ) => el.type === 'radio' && el.checked );
		if ( checkedRadio ) return checkedRadio.value;

		return inputs[ inputs.length - 1 ].value;
	};

	const isVisible = ( fieldId ) => {
		const wrapper = getFieldWrapper( fieldId );
		return ! wrapper || wrapper.style.display !== 'none';
	};

	const evaluateAll = () => {
		let changed    = true;
		let iterations = 0;

		while ( changed && iterations < 10 ) {
			changed = false;
			iterations++;

			rules.forEach( ( rule ) => {
				const wrapper = getFieldWrapper( rule.fieldId );
				if ( ! wrapper ) return;

				const shouldShow = rule.conditions.every( ( condition ) => {
					if ( ! isVisible( condition.field ) ) return false;

					const value    = getFieldValue( condition.field );
					const expected = condition.value;

					if ( typeof expected === 'boolean' ) {
						return Boolean( value ) === expected;
					}

					return String( value ) === String( expected );
				} );

				const currentlyVisible = wrapper.style.display !== 'none';
				if ( shouldShow !== currentlyVisible ) {
					wrapper.style.display = shouldShow ? '' : 'none';
					changed = true;
				}
			} );
		}
	};

	const init = () => {
		evaluateAll();
		document.addEventListener( 'change', ( event ) => {
			if ( event.target.matches( 'input, select, textarea' ) ) {
				evaluateAll();
			}
		} );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
