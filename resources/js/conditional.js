'use strict';

import { showmoRules } from 'showmo';
import 'showmo/showmo.css';

export function initConditional() {
	const init = () => {
		const data = window.optiz && window.optiz.conditional;

		if ( ! data || ! data.rules || ! data.rules.length ) {
			return;
		}

		const rules = data.rules.map( toShowmoRule ).filter( ( rule ) => rule.when.length );

		if ( rules.length ) {
			showmoRules( rules );
		}
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}

function toShowmoRule( rule ) {
	return {
		target: '[data-field-id="' + rule.fieldId + '"]',
		when: rule.conditions.map( toWhen ),
	};
}

function toWhen( condition ) {
	const el = document.getElementById( 'optiz_' + condition.field );
	const source = el ? '#' + el.id : sourceFor( condition.field );

	if ( el && el.type === 'checkbox' ) {
		const want =
			condition.compare === '!==' ? ! toBool( condition.value ) : toBool( condition.value );

		return want ? { source } : { source, isNot: '1' };
	}

	return condition.compare === '!=='
		? { source, isNot: condition.value }
		: { source, is: condition.value };
}

function sourceFor( field ) {
	const inputs = document.querySelectorAll( '[name$="[' + field + ']"]' );

	return inputs.length ? inputs[ 0 ].name : '#optiz_' + field;
}

function toBool( value ) {
	if ( typeof value === 'boolean' ) {
		return value;
	}

	return value === '1' || value === 1 || value === 'true';
}
