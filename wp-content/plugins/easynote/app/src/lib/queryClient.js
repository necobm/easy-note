import { QueryClient } from '@tanstack/react-query';

/**
 * A QueryClient tuned for a same-origin admin-like app: don't refetch on window
 * focus (edits are the source of truth), keep data briefly fresh, retry once.
 */
export function createQueryClient() {
	return new QueryClient( {
		defaultOptions: {
			queries: {
				refetchOnWindowFocus: false,
				staleTime: 30_000,
				retry: 1,
			},
		},
	} );
}
