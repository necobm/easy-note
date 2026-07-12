import { createContext, useContext } from 'react';

/**
 * Provides the bootstrap config and bound REST client to the component tree.
 */
const AppContext = createContext( null );

export function AppProvider( { config, rest, children } ) {
	return <AppContext.Provider value={ { config, rest } }>{ children }</AppContext.Provider>;
}

export function useRest() {
	return useContext( AppContext ).rest;
}

export function useConfig() {
	return useContext( AppContext ).config;
}
