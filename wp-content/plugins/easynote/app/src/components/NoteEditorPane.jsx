import { useMemo, useState } from 'react';
import { useParams } from 'react-router-dom';
import YooptaEditor, { createYooptaEditor } from '@yoopta/editor';
import { __ } from '@wordpress/i18n';
import { PLUGINS, MARKS } from '../editor/editorConfig';
import { parseContent } from '../editor/emptyValue';
import { useNote, useNoteMutations, useTree } from '../state/hooks';
import { useAutosave } from '../state/useAutosave';
import { useRest, useConfig } from '../lib/context';
import {FloatingBlockActions, FloatingToolbar, SlashCommandMenu} from "@yoopta/ui";

/**
 * Loads a note by the route param, then mounts the editor keyed on the note id so
 * switching notes tears down and recreates the Yoopta instance cleanly.
 */
export default function NoteEditorPane() {
	const { noteId } = useParams();
	const id = Number( noteId );
	const { data: note, isLoading, isError } = useNote( id );

	if ( isLoading ) {
		return <div className="en-pane en-pane--muted">{ __( 'Loading…', 'easynote' ) }</div>;
	}
	if ( isError || ! note ) {
		return <div className="en-pane en-pane--muted">{ __( 'Could not load this note.', 'easynote' ) }</div>;
	}

	return <NoteEditor key={ id } note={ note } />;
}

const STATUS_LABELS = {
	idle: '',
	saving: __( 'Saving…', 'easynote' ),
	saved: __( 'Saved', 'easynote' ),
	error: __( 'Save failed', 'easynote' ),
};

function flattenFolders( nodes, depth = 0, out = [] ) {
	for ( const node of nodes || [] ) {
		out.push( { id: node.id, name: `${ '— '.repeat( depth ) }${ node.name }` } );
		flattenFolders( node.children, depth + 1, out );
	}
	return out;
}

function NoteEditor( { note } ) {
	const rest = useRest();
	const config = useConfig();
	const { data: tree } = useTree();
	const { renameNote, moveNote, togglePublic } = useNoteMutations();

	const initialValue = useMemo( () => parseContent( note.meta && note.meta._easynote_content ), [ note ] );
	const editor = useMemo(
		() => createYooptaEditor( { plugins: PLUGINS, marks: MARKS, value: initialValue } ),
		[]
	);

	const contentSave = useAutosave( ( value ) => rest.updateNoteContent( note.id, JSON.stringify( value ) ) );
	const titleSave = useAutosave( ( t ) => rest.renameNote( note.id, t ), 600 );

	const [ title, setTitle ] = useState( ( note.title && note.title.raw ) || '' );
	const [ isPublic, setIsPublic ] = useState( !! ( note.meta && note.meta._easynote_public ) );

	const currentFolder = ( note.easynote_folder && note.easynote_folder[ 0 ] ) || 0;
	const folders = flattenFolders( tree && tree.folders );

	const publicUrl = `${ config.homeUrl.replace( /\/$/, '' ) }/n/${ note.slug }`;

	return (
		<div className="en-pane">
			<div className="en-toolbar">
				<select
					className="en-select"
					value={ currentFolder }
					onChange={ ( e ) => moveNote.mutate( { id: note.id, folderId: Number( e.target.value ) } ) }
				>
					<option value={ 0 }>{ __( 'Inbox', 'easynote' ) }</option>
					{ folders.map( ( f ) => (
						<option key={ f.id } value={ f.id }>
							{ f.name }
						</option>
					) ) }
				</select>

				<label className="en-share">
					<input
						type="checkbox"
						checked={ isPublic }
						onChange={ ( e ) => {
							const next = e.target.checked;
							setIsPublic( next );
							togglePublic.mutate( { id: note.id, isPublic: next } );
						} }
					/>
					{ __( 'Public', 'easynote' ) }
				</label>

				{ isPublic && (
					<a className="en-public-link" href={ publicUrl } target="_blank" rel="noreferrer">
						{ __( 'View public link', 'easynote' ) }
					</a>
				) }

				<span className="en-save-status">{ STATUS_LABELS[ contentSave.status ] }</span>
			</div>

			<input
				className="en-title"
				value={ title }
				placeholder={ __( 'Untitled', 'easynote' ) }
				onChange={ ( e ) => {
					setTitle( e.target.value );
					titleSave.schedule( e.target.value );
				} }
			/>

			<div className="en-editor">
				<YooptaEditor
					editor={ editor }
					autoFocus
					placeholder={ __( "Type / to open menu...", 'easynote' ) }
					onChange={ ( value ) => contentSave.schedule( value ) }
				>
					<FloatingToolbar />
					<FloatingBlockActions />
					<SlashCommandMenu />
				</YooptaEditor>
			</div>
		</div>
	);
}
