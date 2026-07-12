import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useRest } from '../lib/context';

/**
 * React Query hooks over the REST client. Mutations invalidate the tree (and the
 * affected note) so the sidebar and editor stay in sync.
 */

export function useTree() {
	const rest = useRest();
	return useQuery( { queryKey: [ 'tree' ], queryFn: () => rest.getTree() } );
}

export function useNote( id ) {
	const rest = useRest();
	return useQuery( {
		queryKey: [ 'note', id ],
		queryFn: () => rest.getNote( id ),
		enabled: !! id,
	} );
}

export function useNoteMutations() {
	const rest = useRest();
	const qc = useQueryClient();
	const invalidateTree = () => qc.invalidateQueries( { queryKey: [ 'tree' ] } );

	return {
		createNote: useMutation( {
			mutationFn: ( vars ) => rest.createNote( vars ),
			onSuccess: invalidateTree,
		} ),
		renameNote: useMutation( {
			mutationFn: ( { id, title } ) => rest.renameNote( id, title ),
			onSuccess: ( _data, { id } ) => {
				invalidateTree();
				qc.invalidateQueries( { queryKey: [ 'note', id ] } );
			},
		} ),
		deleteNote: useMutation( {
			mutationFn: ( id ) => rest.deleteNote( id ),
			onSuccess: invalidateTree,
		} ),
		moveNote: useMutation( {
			mutationFn: ( { id, folderId } ) => rest.moveNote( id, folderId ),
			onSuccess: invalidateTree,
		} ),
		togglePublic: useMutation( {
			mutationFn: ( { id, isPublic, slug } ) => rest.togglePublic( id, isPublic, slug ),
			onSuccess: ( _data, { id } ) => qc.invalidateQueries( { queryKey: [ 'note', id ] } ),
		} ),
	};
}

export function useFolderMutations() {
	const rest = useRest();
	const qc = useQueryClient();
	const invalidateTree = () => qc.invalidateQueries( { queryKey: [ 'tree' ] } );

	return {
		createFolder: useMutation( {
			mutationFn: ( vars ) => rest.createFolder( vars ),
			onSuccess: invalidateTree,
		} ),
		renameFolder: useMutation( {
			mutationFn: ( { id, name } ) => rest.renameFolder( id, name ),
			onSuccess: invalidateTree,
		} ),
		deleteFolder: useMutation( {
			mutationFn: ( id ) => rest.deleteFolder( id ),
			onSuccess: invalidateTree,
		} ),
	};
}
