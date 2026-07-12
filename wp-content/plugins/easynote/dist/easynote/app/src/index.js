import { createRoot } from 'react-dom/client';
import { QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import { getConfig } from './bootstrap';
import { createWpFetch } from './lib/wpFetch';
import { createRestClient } from './lib/restClient';
import { createQueryClient } from './lib/queryClient';
import { AppProvider } from './lib/context';
import App from './components/App';
import './styles/app.css';

const config = getConfig();
const wpFetch = createWpFetch( { restUrl: config.restUrl, nonce: config.nonce } );
const rest = createRestClient( wpFetch, config.restNamespace );
const queryClient = createQueryClient();

const container = document.getElementById( 'easynote-app' );

if ( container ) {
	createRoot( container ).render(
		<QueryClientProvider client={ queryClient }>
			<AppProvider config={ config } rest={ rest }>
				<BrowserRouter basename={ new URL( config.appBase ).pathname }>
					<App />
				</BrowserRouter>
			</AppProvider>
		</QueryClientProvider>
	);
}
