import { createRoot } from 'react-dom/client';
import { QueryClientProvider } from '@tanstack/react-query';
import { getConfig } from './bootstrap';
import { createWpFetch } from './lib/wpFetch';
import { createRestClient } from './lib/restClient';
import { createQueryClient } from './lib/queryClient';
import { AppProvider } from './lib/context';
import ReadOnlyNoteView from './components/ReadOnlyNoteView';
import './styles/app.css';

const config = getConfig();
// No nonce: the public endpoint is unauthenticated.
const wpFetch = createWpFetch( { restUrl: config.restUrl } );
const rest = createRestClient( wpFetch, config.restNamespace );
const queryClient = createQueryClient();

const container = document.getElementById( 'easynote-public' );

if ( container ) {
	createRoot( container ).render(
		<QueryClientProvider client={ queryClient }>
			<AppProvider config={ config } rest={ rest }>
				<ReadOnlyNoteView />
			</AppProvider>
		</QueryClientProvider>
	);
}
