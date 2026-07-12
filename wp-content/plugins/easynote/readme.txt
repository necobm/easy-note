=== EasyNote ===
Contributors: easynote
Tags: notes, editor, notion, headless, react
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A self-hosted, Notion-like note app for WordPress: a React block editor on the front end, backed by the REST API, while wp-admin stays stock.

== Description ==

EasyNote turns WordPress into a focused, Notion-like note-taking app without changing wp-admin.

* Notes are a dedicated custom post type (`easynote_note`) organised into a hierarchical folder tree (`easynote_folder`).
* Editing happens in a headless React single-page app served on the front end at `/notes`, using the [Yoopta](https://github.com/yoopta-editor/Yoopta-Editor) block editor. Clicking a note swaps the editor in place — you never lose the sidebar.
* Each note is owned by its author; you only ever see your own notes (administrators can be granted access to everyone's).
* Notes are private by default. Any note can be shared as a read-only public page at `/n/{slug}` — everything else stays behind the WordPress login.
* wp-admin is untouched: administrators keep the full, stock WordPress admin, including a fallback editing screen for notes.

The app talks to WordPress entirely over the REST API (cookie auth + nonce), so it is a clean example of the Headless WordPress pattern.

== Installation ==

1. Upload the `easynote` folder to `/wp-content/plugins/`, or install the ZIP through Plugins → Add New → Upload.
2. Activate the plugin through the Plugins screen.
3. Make sure Settings → Permalinks is set to anything other than “Plain” (the app routes need pretty permalinks). EasyNote shows a reminder if they are still Plain.
4. Visit `/notes` while logged in to open the app.

== Frequently Asked Questions ==

= Does this change wp-admin? =

No. wp-admin stays completely stock. The notes app lives on the front end at `/notes`; the only admin addition is a reminder to enable pretty permalinks.

= Can a single administrator use it? =

Yes. On activation the note capabilities are granted to administrators, so a lone admin can create and edit notes immediately with no extra setup.

= Are my notes public? =

No. Notes are private and scoped to their author. A note becomes readable without login only when you explicitly toggle it public, and only that single note is exposed — at `/n/{slug}`.

= Where is the source for the bundled JavaScript? =

The React app's source lives in `app/src/` inside the plugin, with build instructions in `app/README.md`. The shipped `app/build/` files are produced from it with `@wordpress/scripts` (webpack), which are standard, non-obfuscated builds.

== Changelog ==

= 0.1.0 =
* Initial release: `easynote_note` post type and `easynote_folder` taxonomy, REST API (tree, move, share, public read), a Yoopta-based React app served at `/notes`, and public note pages at `/n/{slug}`.
