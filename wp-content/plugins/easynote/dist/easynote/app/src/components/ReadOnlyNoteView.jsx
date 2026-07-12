import { useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import YooptaEditor, { createYooptaEditor } from '@yoopta/editor';
import { __ } from '@wordpress/i18n';
import { PLUGINS, MARKS } from '../editor/editorConfig';
import { parseContent } from '../editor/emptyValue';
import { useRest, useConfig } from '../lib/context';

/**
 * Public, read-only rendering of a single shared note. Fetches by slug from the
 * unauthenticated endpoint; a private/missing note yields a 404 → "not found".
 */
export default function ReadOnlyNoteView() {
	const rest = useRest();
	const config = useConfig();
	const slug = config.publicSlug;

	const { data, isLoading, isError } = useQuery( {
		queryKey: [ 'public-note', slug ],
		queryFn: () => rest.getPublicNote( slug ),
		retry: false,
	} );

	if ( isLoading ) {
		return <div className="en-public en-pane--muted">{ __( 'Loading…', 'easynote' ) }</div>;
	}
	if ( isError || ! data ) {
		return <div className="en-public en-pane--muted">{ __( 'This note is not available.', 'easynote' ) }</div>;
	}

	return <PublicNote note={ data } />;
}

function PublicNote( { note } ) {
	const editor = useMemo(
		() => createYooptaEditor( { plugins: PLUGINS, marks: MARKS, value: parseContent( note.content ), readOnly: true } ),
		[]
	);

	return (
		<article className="en-public">
			<h1 className="en-public__title">{ note.title }</h1>
			<div className="en-editor en-editor--readonly">
				<YooptaEditor editor={ editor } />
			</div>
		</article>
	);
}
