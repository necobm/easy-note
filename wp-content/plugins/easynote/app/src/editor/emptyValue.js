import { generateId } from '@yoopta/editor';

/**
 * A YooptaContentValue with a single empty paragraph, used for new notes so the
 * editor always mounts with a focusable block rather than an empty object.
 *
 * @return {Object} Yoopta content value keyed by block id.
 */
export function createEmptyValue() {
	const blockId = generateId();

	return {
		[ blockId ]: {
			id: blockId,
			type: 'Paragraph',
			meta: { order: 0, depth: 0 },
			value: [
				{
					id: generateId(),
					type: 'paragraph',
					children: [ { text: '' } ],
					props: { nodeType: 'block' },
				},
			],
		},
	};
}

/**
 * Whether every block in a Yoopta value has the shape the editor requires
 * (id, type, a `meta` object and a `value` array). Guards against malformed or
 * legacy content that would otherwise crash Yoopta on render.
 *
 * @param {Object} value Candidate Yoopta content value.
 * @return {boolean}
 */
function isWellFormed( value ) {
	const blocks = Object.values( value );
	if ( ! blocks.length ) {
		return false;
	}
	return blocks.every(
		( b ) => b && typeof b === 'object' && b.type && b.meta && typeof b.meta === 'object' && Array.isArray( b.value )
	);
}

/**
 * Parse a stored content string into a Yoopta value, falling back to an empty
 * document. Accepts either a JSON string (as stored) or an already-parsed object.
 *
 * @param {string|Object} raw Stored `_easynote_content`.
 * @return {Object} Yoopta content value.
 */
export function parseContent( raw ) {
	let value = null;

	if ( raw && typeof raw === 'object' ) {
		value = raw;
	} else if ( typeof raw === 'string' && raw.trim() !== '' ) {
		try {
			value = JSON.parse( raw );
		} catch ( e ) {
			value = null;
		}
	}

	if ( value && typeof value === 'object' && isWellFormed( value ) ) {
		return value;
	}
	return createEmptyValue();
}
