/**
 * Yoopta plugin and mark configuration.
 *
 * Yoopta 6.x wires blocks/marks through createYooptaEditor({ plugins, marks });
 * block insertion works via markdown shortcuts (e.g. "# ", "- ", "> "). The
 * separate slash-menu/toolbar tool packages are not yet 6.x-compatible and are
 * intentionally omitted (tracked as a follow-up).
 */
import Paragraph from '@yoopta/paragraph';
import { HeadingOne, HeadingTwo, HeadingThree } from '@yoopta/headings';
import { BulletedList, NumberedList, TodoList } from '@yoopta/lists';
import Blockquote from '@yoopta/blockquote';
import Link from '@yoopta/link';
import { Bold, Italic, Underline, Strike, CodeMark, Highlight } from '@yoopta/marks';

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
];

export const MARKS = [ Bold, Italic, Underline, Strike, CodeMark, Highlight ];
