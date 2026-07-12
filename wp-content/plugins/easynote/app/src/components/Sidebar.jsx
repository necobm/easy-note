import { useNavigate, useParams } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import { useTree, useNoteMutations, useFolderMutations } from '../state/hooks';
import { useConfig } from '../lib/context';

/**
 * Left pane: workspace tree (folders + notes) with create/rename/delete actions.
 * Navigation is client-side; the active note is highlighted from the route.
 */
export default function Sidebar() {
	const config = useConfig();
	const navigate = useNavigate();
	const { data: tree, isLoading } = useTree();
	const { createNote } = useNoteMutations();
	const { createFolder } = useFolderMutations();

	const addNote = async ( folderId = 0 ) => {
		const note = await createNote.mutateAsync( { folderId } );
		if ( note && note.id ) {
			navigate( `/${ note.id }` );
		}
	};

	const addFolder = () => {
		const name = window.prompt( __( 'Folder name', 'easynote' ) );
		if ( name ) {
			createFolder.mutate( { name } );
		}
	};

	return (
		<aside className="en-sidebar">
			<div className="en-sidebar__head">
				<span className="en-workspace">{ config.currentUser ? config.currentUser.name : __( 'Notes', 'easynote' ) }</span>
				<div className="en-sidebar__actions">
					<button type="button" onClick={ () => addNote( 0 ) } title={ __( 'New note', 'easynote' ) }>
						+ { __( 'Note', 'easynote' ) }
					</button>
					<button type="button" onClick={ addFolder } title={ __( 'New folder', 'easynote' ) }>
						+ { __( 'Folder', 'easynote' ) }
					</button>
				</div>
			</div>

			{ isLoading && <div className="en-sidebar__muted">{ __( 'Loading…', 'easynote' ) }</div> }

			{ tree && (
				<nav className="en-tree">
					{ tree.folders.map( ( folder ) => (
						<FolderNode key={ folder.id } folder={ folder } onAddNote={ addNote } />
					) ) }

					<div className="en-inbox">
						<div className="en-inbox__label">{ __( 'Inbox', 'easynote' ) }</div>
						{ tree.inbox.map( ( note ) => (
							<NoteRow key={ note.id } note={ note } />
						) ) }
					</div>
				</nav>
			) }
		</aside>
	);
}

function FolderNode( { folder, onAddNote } ) {
	const { renameFolder, deleteFolder } = useFolderMutations();

	const rename = () => {
		const name = window.prompt( __( 'Rename folder', 'easynote' ), folder.name );
		if ( name && name !== folder.name ) {
			renameFolder.mutate( { id: folder.id, name } );
		}
	};

	const remove = () => {
		if ( window.confirm( __( 'Delete this folder? Notes inside move to the Inbox.', 'easynote' ) ) ) {
			deleteFolder.mutate( folder.id );
		}
	};

	return (
		<div className="en-folder">
			<div className="en-folder__row">
				<span className="en-folder__name" onDoubleClick={ rename }>
					{ folder.name }
				</span>
				<span className="en-row__actions">
					<button type="button" onClick={ () => onAddNote( folder.id ) } title={ __( 'New note here', 'easynote' ) }>
						+
					</button>
					<button type="button" onClick={ rename } title={ __( 'Rename', 'easynote' ) }>
						✎
					</button>
					<button type="button" onClick={ remove } title={ __( 'Delete', 'easynote' ) }>
						×
					</button>
				</span>
			</div>

			<div className="en-folder__children">
				{ folder.notes.map( ( note ) => (
					<NoteRow key={ note.id } note={ note } />
				) ) }
				{ folder.children.map( ( child ) => (
					<FolderNode key={ child.id } folder={ child } onAddNote={ onAddNote } />
				) ) }
			</div>
		</div>
	);
}

function NoteRow( { note } ) {
	const navigate = useNavigate();
	const { noteId } = useParams();
	const { deleteNote } = useNoteMutations();
	const active = Number( noteId ) === note.id;

	const remove = ( e ) => {
		e.stopPropagation();
		if ( window.confirm( __( 'Move this note to Trash?', 'easynote' ) ) ) {
			deleteNote.mutate( note.id, {
				onSuccess: () => {
					if ( active ) {
						navigate( '/' );
					}
				},
			} );
		}
	};

	return (
		<div
			className={ `en-note-row${ active ? ' is-active' : '' }` }
			onClick={ () => navigate( `/${ note.id }` ) }
		>
			<span className="en-note-row__title">{ note.title || __( '(untitled)', 'easynote' ) }</span>
			<button type="button" className="en-note-row__del" onClick={ remove } title={ __( 'Delete', 'easynote' ) }>
				×
			</button>
		</div>
	);
}
