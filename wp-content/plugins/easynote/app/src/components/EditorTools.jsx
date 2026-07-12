import { __ } from '@wordpress/i18n';
import {
	BlockOptions,
	FloatingBlockActions,
	FloatingToolbar,
	SlashCommandMenu,
	useBlockActions,
} from '@yoopta/ui';
import { SLASH_COMMANDS, TOOLBAR_MARKS } from '../editor/editorConfig';

/**
 * Composes the three @yoopta/ui 6.x editor tools. Each is a headless compound
 * component: rendered bare it renders nothing, so it has to be given its item
 * list and sub-parts here. Mount these as children of <YooptaEditor>; they read
 * the editor from context.
 *
 * @param {Object} props
 * @param {Object} props.editor The instance from createYooptaEditor().
 */
export default function EditorTools( { editor } ) {
	return (
		<>
			<MarkToolbar editor={ editor } />
			<BlockActions />
			<SlashMenu />
		</>
	);
}

/**
 * Selection toolbar of inline-mark toggles. `editor.formats[ slug ]` exposes
 * `.isActive()` / `.toggle()`; the toolbar only mounts its content while a
 * selection is active, so reading `isActive()` at render reflects that range.
 */
function MarkToolbar( { editor } ) {
	return (
		<FloatingToolbar>
			<FloatingToolbar.Content>
				{ TOOLBAR_MARKS.map( ( mark ) => {
					const format = editor.formats[ mark.slug ];
					if ( ! format ) {
						return null;
					}
					return (
						<FloatingToolbar.Button
							key={ mark.slug }
							title={ mark.label }
							active={ format.isActive() }
							// Keep the editor selection: don't let the click blur it.
							onMouseDown={ ( e ) => e.preventDefault() }
							onClick={ () => format.toggle() }
						>
							{ mark.glyph }
						</FloatingToolbar.Button>
					);
				} ) }
			</FloatingToolbar.Content>
		</FloatingToolbar>
	);
}

/**
 * Per-block hover actions. The render prop hands us the hovered block id; the
 * options menu duplicates / links / deletes that block via useBlockActions().
 */
function BlockActions() {
	return (
		<FloatingBlockActions>
			{ ( { blockId } ) => ( blockId ? <BlockActionsMenu blockId={ blockId } /> : null ) }
		</FloatingBlockActions>
	);
}

function BlockActionsMenu( { blockId } ) {
	const { duplicateBlock, copyBlockLink, deleteBlock } = useBlockActions();
	return (
		<BlockOptions>
			<BlockOptions.Trigger asChild>
				<FloatingBlockActions.Button title={ __( 'Block options', 'easynote' ) }>
					⋮
				</FloatingBlockActions.Button>
			</BlockOptions.Trigger>
			<BlockOptions.Content>
				<BlockOptions.Item onSelect={ () => duplicateBlock( blockId ) }>
					{ __( 'Duplicate', 'easynote' ) }
				</BlockOptions.Item>
				<BlockOptions.Item onSelect={ () => copyBlockLink( blockId ) }>
					{ __( 'Copy link to block', 'easynote' ) }
				</BlockOptions.Item>
				<BlockOptions.Separator />
				<BlockOptions.Item variant="destructive" onSelect={ () => deleteBlock( blockId ) }>
					{ __( 'Delete', 'easynote' ) }
				</BlockOptions.Item>
			</BlockOptions.Content>
		</BlockOptions>
	);
}

/**
 * The "/" command menu. It self-registers a keydown listener on the editor and
 * opens when "/" is typed at the start of an empty block. On select it toggles
 * the block whose type equals the item id (the @yoopta/ui default handler), so
 * no onSelect override is needed.
 */
function SlashMenu() {
	return (
		<SlashCommandMenu items={ SLASH_COMMANDS }>
			{ ( { groupedItems } ) => (
				<SlashCommandMenu.Content>
					<SlashCommandMenu.Input placeholder={ __( 'Filter blocks…', 'easynote' ) } />
					<SlashCommandMenu.List>
						<SlashCommandMenu.Empty>
							{ __( 'No blocks found', 'easynote' ) }
						</SlashCommandMenu.Empty>
						{ [ ...groupedItems.entries() ].map( ( [ group, items ] ) => (
							<SlashCommandMenu.Group key={ group } heading={ group }>
								{ items.map( ( item ) => (
									<SlashCommandMenu.Item
										key={ item.id }
										value={ item.id }
										title={ item.title }
										description={ item.description }
									/>
								) ) }
							</SlashCommandMenu.Group>
						) ) }
					</SlashCommandMenu.List>
				</SlashCommandMenu.Content>
			) }
		</SlashCommandMenu>
	);
}
