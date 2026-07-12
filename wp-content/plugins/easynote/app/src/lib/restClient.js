/**
 * Named REST operations for EasyNote.
 *
 * Everything the UI needs goes through this object, so the exact routes/namespace
 * live in one place and stay swappable. CRUD uses core controllers (the CPT and
 * taxonomy are show_in_rest); action-shaped operations use the easynote/v1
 * namespace.
 */
export function createRestClient( wpFetch, namespace = 'easynote/v1' ) {
	const ns = namespace.replace( /\/$/, '' );

	return {
		/* Tree ----------------------------------------------------------- */
		getTree: () => wpFetch( `${ ns }/tree` ),

		/* Notes (core CRUD) ---------------------------------------------- */
		getNote: ( id ) => wpFetch( `wp/v2/notes/${ id }?context=edit` ),

		createNote: ( { title = 'Untitled', folderId = 0 } = {} ) =>
			wpFetch( 'wp/v2/notes', {
				method: 'POST',
				body: {
					title,
					status: 'publish',
					...( folderId ? { easynote_folder: [ folderId ] } : {} ),
				},
			} ),

		renameNote: ( id, title ) =>
			wpFetch( `wp/v2/notes/${ id }`, { method: 'POST', body: { title } } ),

		updateNoteContent: ( id, contentJson ) =>
			wpFetch( `wp/v2/notes/${ id }`, {
				method: 'POST',
				body: { meta: { _easynote_content: contentJson } },
			} ),

		deleteNote: ( id ) => wpFetch( `wp/v2/notes/${ id }`, { method: 'DELETE' } ),

		/* Note actions --------------------------------------------------- */
		moveNote: ( id, folderId ) =>
			wpFetch( `${ ns }/notes/${ id }/move`, { method: 'POST', body: { folder_id: folderId } } ),

		togglePublic: ( id, isPublic, slug ) =>
			wpFetch( `${ ns }/notes/${ id }/public`, {
				method: 'POST',
				body: { public: isPublic, ...( slug ? { slug } : {} ) },
			} ),

		/* Folders -------------------------------------------------------- */
		createFolder: ( { name, parentId = 0 } ) =>
			wpFetch( 'wp/v2/easynote-folders', {
				method: 'POST',
				body: { name, ...( parentId ? { parent: parentId } : {} ) },
			} ),

		renameFolder: ( id, name ) =>
			wpFetch( `wp/v2/easynote-folders/${ id }`, { method: 'POST', body: { name } } ),

		deleteFolder: ( id ) =>
			wpFetch( `wp/v2/easynote-folders/${ id }?force=true`, { method: 'DELETE' } ),

		moveFolder: ( id, parentId ) =>
			wpFetch( `${ ns }/folders/${ id }/move`, { method: 'POST', body: { parent_id: parentId } } ),

		/* Public --------------------------------------------------------- */
		getPublicNote: ( slug ) => wpFetch( `${ ns }/public-notes/${ slug }` ),
	};
}
