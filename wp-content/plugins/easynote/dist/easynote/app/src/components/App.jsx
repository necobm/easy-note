import { Routes, Route } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import Sidebar from './Sidebar';
import NoteEditorPane from './NoteEditorPane';

/**
 * Logged-in two-pane layout: sidebar tree + routed editor pane.
 */
export default function App() {
	return (
		<div className="en-app">
			<Sidebar />
			<main className="en-main">
				<Routes>
					<Route path="/" element={ <EmptyState /> } />
					<Route path="/:noteId" element={ <NoteEditorPane /> } />
				</Routes>
			</main>
		</div>
	);
}

function EmptyState() {
	return (
		<div className="en-pane en-pane--muted">
			{ __( 'Select a note, or create a new one.', 'easynote' ) }
		</div>
	);
}
