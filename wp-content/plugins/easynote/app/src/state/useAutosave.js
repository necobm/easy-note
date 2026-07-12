import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * Debounced, serialized autosave.
 *
 * Coalesces rapid changes and keeps a single save in flight per key: if new
 * edits arrive while a save is running, only the latest value is saved next.
 * Flushes immediately on unmount and on tab hide / page unload so nothing is
 * lost when navigating away.
 *
 * @param {(value:any)=>Promise<any>} saveFn  Persists the value.
 * @param {number}                    delayMs Debounce delay.
 * @return {{ schedule:(value:any)=>void, status:'idle'|'saving'|'saved'|'error' }}
 */
export function useAutosave( saveFn, delayMs = 800 ) {
	const [ status, setStatus ] = useState( 'idle' );

	const timer = useRef( null );
	const inFlight = useRef( false );
	const pending = useRef( undefined ); // Latest value awaiting save, or undefined.
	const saveRef = useRef( saveFn );
	saveRef.current = saveFn;

	const flush = useCallback( async () => {
		if ( timer.current ) {
			clearTimeout( timer.current );
			timer.current = null;
		}
		if ( inFlight.current || pending.current === undefined ) {
			return;
		}

		const value = pending.current;
		pending.current = undefined;
		inFlight.current = true;
		setStatus( 'saving' );

		try {
			await saveRef.current( value );
			// Save whatever accumulated while this request was in flight.
			if ( pending.current !== undefined ) {
				inFlight.current = false;
				await flush();
			} else {
				setStatus( 'saved' );
			}
		} catch ( e ) {
			setStatus( 'error' );
		} finally {
			inFlight.current = false;
		}
	}, [] );

	const schedule = useCallback(
		( value ) => {
			pending.current = value;
			if ( timer.current ) {
				clearTimeout( timer.current );
			}
			timer.current = setTimeout( flush, delayMs );
		},
		[ flush, delayMs ]
	);

	// Flush on tab hide / unload and on unmount.
	useEffect( () => {
		const onHide = () => {
			if ( document.visibilityState === 'hidden' ) {
				flush();
			}
		};
		document.addEventListener( 'visibilitychange', onHide );
		window.addEventListener( 'beforeunload', flush );
		return () => {
			document.removeEventListener( 'visibilitychange', onHide );
			window.removeEventListener( 'beforeunload', flush );
			flush();
		};
	}, [ flush ] );

	return { schedule, status };
}
