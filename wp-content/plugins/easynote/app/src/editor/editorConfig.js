/**
 * Yoopta plugin and mark configuration.
 *
 * Yoopta 6.x wires blocks/marks through createYooptaEditor({ plugins, marks }).
 * The editor tools (slash menu, floating toolbar, block actions) live in the
 * `@yoopta/ui` 6.x package as *headless compound components* — rendered bare
 * they produce nothing, so they are composed with the data below in
 * `../components/EditorTools`. `SLASH_COMMANDS` ids and `TOOLBAR_MARKS` slugs
 * must stay in sync with `PLUGINS`/`MARKS`.
 */
import Paragraph from '@yoopta/paragraph';
import { HeadingOne, HeadingTwo, HeadingThree } from '@yoopta/headings';
import { BulletedList, NumberedList, TodoList } from '@yoopta/lists';
import Blockquote from '@yoopta/blockquote';
import Link from '@yoopta/link';
import { Bold, Italic, Underline, Strike, CodeMark, Highlight } from '@yoopta/marks';
import {Code} from "@yoopta/code";

/**
 * The @yoopta/code block plugin is intentionally excluded: it bundles Shiki with
 * every language grammar/theme (~11 MB), which is disproportionate for a notes
 * app. Inline code is still available via the CodeMark. Re-adding fenced code
 * blocks with a slimmer highlighter is a follow-up.
 */
export const PLUGINS = [
	Paragraph,
	HeadingOne,
	HeadingTwo,
	HeadingThree,
	BulletedList,
	NumberedList,
	TodoList,
	Blockquote,
	Link,
	Code
];

export const MARKS = [ Bold, Italic, Underline, Strike, CodeMark, Highlight ];

/**
 * Slash-menu entries. `id` is the Yoopta block type the menu toggles to via
 * `editor.toggleBlock( id )`, so each id MUST match a plugin type in `PLUGINS`.
 * `group` buckets the item in the rendered list; `keywords` feed the fuzzy
 * filter alongside `title`/`description`.
 */
export const SLASH_COMMANDS = [
	{ id: 'Paragraph', title: 'Text', description: 'Plain paragraph.', group: 'Basic blocks', keywords: [ 'paragraph', 'text', 'plain', 'body' ] },
	{ id: 'HeadingOne', title: 'Heading 1', description: 'Large section heading.', group: 'Basic blocks', keywords: [ 'h1', 'title', 'heading', 'large' ] },
	{ id: 'HeadingTwo', title: 'Heading 2', description: 'Medium section heading.', group: 'Basic blocks', keywords: [ 'h2', 'subtitle', 'heading' ] },
	{ id: 'HeadingThree', title: 'Heading 3', description: 'Small section heading.', group: 'Basic blocks', keywords: [ 'h3', 'heading', 'small' ] },
	{ id: 'Blockquote', title: 'Quote', description: 'Capture a quote.', group: 'Basic blocks', keywords: [ 'quote', 'blockquote', 'citation' ] },
	{ id: 'Code', title: 'Code', description: 'Code snippet with highlighting.', group: 'Basic blocks', keywords: [ 'code', 'snippet', 'pre', 'fence' ] },
	{ id: 'BulletedList', title: 'Bulleted list', description: 'Simple bulleted list.', group: 'Lists', keywords: [ 'unordered', 'bullet', 'ul', 'list' ] },
	{ id: 'NumberedList', title: 'Numbered list', description: 'Ordered numbered list.', group: 'Lists', keywords: [ 'ordered', 'number', 'ol', 'list' ] },
	{ id: 'TodoList', title: 'To-do list', description: 'Track tasks with checkboxes.', group: 'Lists', keywords: [ 'todo', 'task', 'checkbox', 'check' ] },
];

/**
 * Floating-toolbar buttons. `slug` is the Yoopta mark key exposed on
 * `editor.formats[ slug ]` (`.isActive()` / `.toggle()`), so each MUST match a
 * mark type in `MARKS`.
 */
export const TOOLBAR_MARKS = [
	{ slug: 'bold', label: 'Bold', glyph: 'B' },
	{ slug: 'italic', label: 'Italic', glyph: 'I' },
	{ slug: 'underline', label: 'Underline', glyph: 'U' },
	{ slug: 'strike', label: 'Strikethrough', glyph: 'S' },
	{ slug: 'code', label: 'Inline code', glyph: '</>' },
	{ slug: 'highlight', label: 'Highlight', glyph: 'H' },
];
