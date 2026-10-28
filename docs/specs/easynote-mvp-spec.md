# EasyNote — MVP Specification

- **Status:** Draft v2
- **Date:** 2026-10-28 (v1: 2026-07-10)
- **Owner:** Néstor Brito
- **Applies to:** WordPress 7.0.1 (dev environment in this repo)

### Revision history

| Version | Date | Change |
|---------|------|--------|
| v1 | 2026-07-10 | Initial spec. The app lives inside a restyled wp-admin, uses the Gutenberg editor, and stores notes as core posts and categories. |
| v2 | 2026-10-28 | Applies the UI decision of 2026-07-11 (§3): a headless React app with the Yoopta editor on the front end, wp-admin left stock, and a dedicated post type and taxonomy. Roles, routing, the data model and acceptance criteria are rewritten to match. |

---

## 1. Vision

EasyNote is a self-hosted, Notion-like note-taking application built entirely on
top of WordPress. The goal is to **own our data**: notes live in our own server
and database. At the same time it keeps the ease of use of Notion's UI and the
convenience of opening it from any device with an internet connection.

WordPress supplies the **backend**: authentication, user management, storage,
revisions, the media library and a REST API. The **UI is a separate React
single-page app** that talks to WordPress over that API (the *Headless
WordPress* pattern). The app is served on the site's front end, behind the
WordPress login.

### 1.1 Guiding principle

> The entire feature set is delivered as a **custom WordPress plugin**. Installing
> and activating that plugin turns any standard WordPress installation into the
> EasyNote app. **WordPress core files must never be modified.** All behavior is
> achieved through standard WordPress hooks (actions and filters) and the REST
> API, from the plugin's main file and its supporting classes.

The plugin approach (chosen over a theme) keeps EasyNote independent of the
active theme. The app routes render their own minimal HTML shell rather than the
theme's templates, so the app keeps working when the theme changes. **wp-admin
is left completely stock.**

---

## 2. Goals and non-goals

### 2.1 Goals (MVP)

- Single-workspace note-taking on self-hosted WordPress.
- Notes are rich documents edited in a Notion-style block editor: text, headings,
  lists, quotes, links, and embedded images.
- Notes are organized in a tree that nests to any depth, shown in the app's
  sidebar like Notion's page tree. Selecting a note swaps the editor in place, so
  **the sidebar never disappears**.
- One owner can run the whole app with a **single WordPress user**, even an
  administrator, without noticing any difference in the app.
- Notes are private by default. Their author can explicitly share any single
  note as a public, read-only page.
- Zero core modifications; everything ships in one activatable plugin.

### 2.2 Non-goals (explicitly out of scope for MVP)

- Multiple workspaces / workspace switching.
- Real-time collaborative editing, comments, mentions.
- Databases/tables, kanban boards, or other Notion "database" views.
- Full-text search UI.
- Customizing wp-admin (menus, dashboards, admin bar) or restricting access to it.
- Mobile-native apps (the responsive web app serves mobile browsers).
- Import/export from Notion.

---

## 3. Key design decision: editor and UI architecture

### 3.1 Background (v1)

v1 chose the **Gutenberg** editor, using the native post edit screen, with the
note tree drawn as a custom sidebar inside wp-admin.

### 3.2 What went wrong (2026-07-11)

In practice, Gutenberg notes didn't work well. WordPress's default behavior
doesn't match a Notion-like experience: **clicking a note navigates to the full
editor screen, so the sidebar menu disappears.** The admin menu had already
needed heavy changes to show categories and notes, and this was the point where
it no longer fit.

The editor was replaced with **[Yoopta](https://github.com/yoopta-editor/Yoopta-Editor)**,
a React-based, Notion-style block editor. Because it's React, the UI
architecture and technologies had to change. Two options were considered.

**Variant 1: keep the admin panel as the app.**
- wp-admin stays the main environment, with the (customized) sidebar menu.
- The center of the admin page hosts the Yoopta editor, which switches to
  whichever note is selected.
- The sidebar menu changes based on the logged-in user (admin or editor).
- Each owner needs at least two users: an admin and an editor.

**Variant 2: headless app on the front end. ✅ Chosen.**
- wp-admin stays **unaltered**, exactly as WordPress ships it, and remains
  accessible.
- The notes app is the site's **public side, behind the WordPress login**. The
  only exception is notes explicitly made public.
- The editor and the menu are a **React app** using Yoopta. It talks to
  WordPress over the **REST API** (Headless WordPress pattern).
- The owner can work with **a single user, even an admin**, without noticing any
  difference in the app.

### 3.3 Why Variant 2

- It removes the need to fight wp-admin's page-per-screen navigation and its
  two-level menu API. The app controls its own layout, so the sidebar always
  stays visible.
- It needs no role juggling: one user is enough, and wp-admin keeps working
  unchanged as a fallback and admin console.
- It isolates EasyNote's UI from WordPress admin CSS and JS. That gives a cleaner
  Notion-like look and an independent build (`@wordpress/scripts`).
- It cleanly enables public sharing of single notes, which was a non-goal in v1.

**Consequences.** The data model moves from core posts and categories to a
dedicated post type and taxonomy (§5). The editor's document format is Yoopta
JSON rather than Gutenberg block HTML. The "editor role confined to EasyNote"
requirement from v1 is dropped (§4).

---

## 4. Personas and roles

| User | How they use EasyNote |
|------|-----------------------|
| **Owner (any user with note capabilities)** | Uses the app at `/notes`. Sees and edits **their own** notes. Can share their own notes publicly. |
| **Administrator** | Same as owner. On activation, administrators also get the full note capability set (including `edit_others_easynote_notes`), so a lone admin can use the app right away and sees every user's notes. Keeps the full, unchanged wp-admin. |
| **Anonymous visitor** | Can only read notes that their author has explicitly made public, at `/n/{slug}`. Everything else redirects to the WordPress login. |

Notes:

- The minimum setup is **one user**. No second "editor" account is needed.
- Note permissions use a dedicated capability set (`capability_type`
  `easynote_note`, `map_meta_cap`). On activation, the plugin grants it to
  `administrator`. Granting it to other roles, such as `editor` for multi-user
  sites, is a site decision and future work (§11).
- Activation also repairs the native `editor` role if it's missing. It doesn't
  restrict that role or change wp-admin for anyone.
- All authentication, sessions and permission checks are delegated to
  **WordPress core security**: `current_user_can()`, cookie auth and REST nonces.
  EasyNote adds no separate auth system.

---

## 5. Data model

EasyNote maps Notion concepts onto a **dedicated custom post type and taxonomy**.
That keeps notes separate from any blog posts and categories on the site.

| Notion concept | WordPress object | Field mapping |
|----------------|------------------|---------------|
| Workspace | The whole site (implicit) | Single, implicit. No object needed. |
| Folder / page with children (tree node) | `easynote_folder` term (hierarchical taxonomy) | `name` → title; `parent` → nesting; term meta `_easynote_content` → the folder's own body (Yoopta JSON). |
| Note (leaf) | `easynote_note` post | `post_title` → title; `post_author` → owner; post meta `_easynote_content` → body (Yoopta JSON); post meta `_easynote_public` → shared flag. |
| Note location in the tree | Note → folder term assignment | A note assigned to a folder is a leaf under that folder. |
| Embedded images | Media library attachments | Standard WordPress media, referenced from image blocks. |

### 5.1 Content storage

The body is the Yoopta editor value, stored as a **JSON string** in
`_easynote_content`. Yoopta keys blocks by dynamic IDs, which doesn't map onto a
fixed REST schema, so a JSON string keeps the transport simple and lossless.
On save, the value is sanitized to a JSON object; anything malformed is replaced
with `{}`. Note content meta takes part in WordPress **revisions**.

### 5.2 The tree

- **Interior nodes are folders.** The taxonomy is hierarchical, so folders nest
  to any depth.
- **Leaf nodes are notes.**
- A folder can contain both sub-folders and notes.
- Like a Notion page, **a folder has its own body content**, edited with the same
  Yoopta editor.
- **Folders are shared** across the workspace (terms are not per-user).
  **Notes are per-author**: a user only sees their own notes, unless they have
  `edit_others_easynote_notes`.

```
Workspace (implicit root)
├── Inbox                                (virtual: notes with no folder)
├── Folder: "Work"                       (term, own body)
│   ├── Folder: "Projects"               (child term)
│   │   ├── Note: "EasyNote spec"
│   │   └── Note: "Q3 roadmap"
│   └── Note: "Meeting notes 2026-07"
└── Folder: "Personal"
    └── Note: "Reading list"
```

### 5.3 The "Inbox" (unfiled notes)

Notes with no folder are never lost. They appear under a **virtual "Inbox"
node** at the root of the tree. The Inbox isn't stored; the tree builder
synthesizes it. Deleting a folder moves its notes to the Inbox.

### 5.4 Ordering

For the MVP, siblings are ordered **alphabetically by title**. Manual
drag-to-reorder is future work and would store an order index in term and post
meta (no core change required).

---

## 6. Functional requirements

### 6.1 Single workspace

- FR-1. There is exactly one workspace, implicit and global. There is no UI for
  creating or switching workspaces.

### 6.2 Notes

- FR-2. A note is an `easynote_note` post, edited in the app with the **Yoopta**
  block editor.
- FR-3. A note supports **text and embedded images** at minimum. Images use the
  WordPress media library (the user needs `upload_files`).
- FR-4. A note can be created from the app (sidebar or folder). When created from
  a folder, it's assigned to that folder.
- FR-5. Selecting a note opens it in the editor pane **without leaving the app**.
  The sidebar stays visible. View and edit are the same thing, as in Notion.
- FR-5a. Edits are **autosaved**; there's no explicit save step.
- FR-5b. Deleting a note **moves it to Trash** (recoverable) using WordPress's
  native trash. The app provides a recovery path.
- FR-5c. Notes with no folder appear under the virtual **Inbox** (§5.3).
- FR-5d. A note can be **moved** to another folder, or to the Inbox.

### 6.3 Sidebar tree

- FR-6. The app's left sidebar shows the folder → note tree, nested and
  expandable like Notion's page tree.
- FR-7. Selecting a **folder** opens its page (§6.4). Selecting a **note** opens
  it in the editor pane. The URL updates (`/notes/{id}`) so it can be bookmarked
  and the browser's back button works.
- FR-8. The tree reflects the live folder hierarchy and note assignments. It
  supports creating, renaming and deleting folders and notes, and reparenting
  folders.

### 6.4 Folder page

- FR-9. Opening a folder shows its **title**, its **body** (Yoopta, editable) and
  a clickable list of its **children** (sub-folders and notes).
- FR-10. A folder page lets the user add a sub-folder or a note under it.
- FR-11. Layout and typography evoke Notion: a clean single column with generous
  spacing.

### 6.5 Sharing (public notes)

- FR-12. A note's **author** (and only its author) can mark it **public**.
  Sharing publishes the note if it's still a draft, and can set a custom slug.
- FR-13. A public note is readable without login at **`/n/{slug}`**, rendered
  read-only.
- FR-14. A note that isn't public (or isn't published) returns **404, never
  403**, so the response never confirms whether a private note exists.

### 6.6 wp-admin

- FR-15. wp-admin is **unchanged** for every user. The only addition is an admin
  notice reminding the user to enable pretty permalinks, shown while permalinks
  are set to "Plain".
- FR-16. Administrators keep a stock wp-admin fallback for notes and folders
  (`show_ui`).

---

## 7. Architecture

### 7.1 Plugin layout

```
wp-content/plugins/easynote/
├── easynote.php                 # plugin header + bootstrap
├── uninstall.php                # cleanup on uninstall (§7.6)
├── inc/
│   ├── class-easynote.php       # loader / component container
│   ├── class-activator.php      # activation/deactivation (caps, rewrite flush)
│   ├── class-roles.php          # note capability set, ownership helpers
│   ├── class-post-type.php      # easynote_note CPT + meta
│   ├── class-taxonomy.php       # easynote_folder taxonomy + term meta
│   ├── class-notes.php          # queries + tree builder (Inbox)
│   ├── class-rest-api.php       # authenticated easynote/v1 endpoints
│   ├── class-rest-public.php    # unauthenticated public-note endpoint
│   └── class-app-shell.php      # front-end routes, login gate, asset loading
├── app/                         # React SPA
│   ├── src/                     # source (index = app, public-entry = public view)
│   └── build/                   # compiled by @wordpress/scripts (git-ignored)
├── tests/                       # WP-CLI smoke test, PHPUnit suite
└── bin/build-release.sh         # distributable ZIP
```

### 7.2 Hooks used (all standard WordPress, no core edits)

| Concern | Hook(s) | Purpose |
|---------|---------|---------|
| Bootstrap | `plugins_loaded` | Instantiate components and register their hooks. |
| Data model | `init` | Register the CPT, taxonomy and meta (`register_post_meta`, `register_term_meta`). |
| Activation / deactivation | `register_activation_hook`, `register_deactivation_hook` | Grant note caps to administrators, repair the `editor` role, flush rewrites. Deactivation only flushes rewrites. |
| Routing | `init` (`add_rewrite_rule`), `query_vars`, `template_redirect` | Serve the app shell at `/notes`, `/notes/{id}` and `/n/{slug}`. |
| REST | `rest_api_init`, `rest_easynote_note_query` | Custom endpoints; scope note listings to the current author. |
| Admin notice | `admin_notices` | Pretty-permalinks reminder. |

### 7.3 Routing

| Route | Access | Renders |
|-------|--------|---------|
| `/notes` | Logged in; anonymous users go to `wp-login.php?redirect_to=…` | App home |
| `/notes/{id}` | Logged in | App with note `{id}` open |
| `/n/{slug}` | Public | Read-only note view |

Each route renders a **minimal standalone HTML shell**, not the theme's header
and footer, containing the React root and the enqueued bundle. Login gating uses
`is_user_logged_in()`. `auth_redirect()` can't be used here because its auth
cookie is scoped to `/wp-admin`. The routes need pretty permalinks.

### 7.4 REST API

Core controllers handle plain CRUD, because the CPT (`wp/v2/notes`) and the
taxonomy (`wp/v2/easynote-folders`) have `show_in_rest` enabled. EasyNote adds
the following endpoints under `easynote/v1`:

| Method & path | Permission | Purpose |
|---------------|------------|---------|
| `GET /tree` | logged in | The whole folder and note tree, plus the Inbox, in one response. |
| `POST /notes/{id}/move` `{folder_id}` | `edit_post` | Move a note to a folder (`0` = Inbox). |
| `POST /notes/{id}/public` `{public, slug?}` | note author only | Toggle sharing; publish on share; optionally set a unique slug. |
| `POST /folders/{id}/move` `{parent_id}` | `manage_categories` | Reparent a folder (`0` = root). |
| `GET /public-notes/{slug}` | public | Safe fields of a public, published note, or 404. |

The app authenticates with the WordPress **cookie plus a `wp_rest` nonce**,
injected into the shell as bootstrap config.

### 7.5 Front-end app

- React 18 (externalized to WordPress core's handles), built with
  `@wordpress/scripts` (webpack). It has two entry points: `index` (the app) and
  `public` (the read-only view).
- Editor: Yoopta 6 (paragraphs, headings, lists, blockquote, links, marks).
  Data fetching uses TanStack Query. Routing uses react-router. Styling uses
  Tailwind.
- Node isn't in the container, so the app is built on the host. A release ZIP
  includes the compiled `build/` and the human-readable `src/`, as WordPress.org
  requires.

### 7.6 Uninstall

On uninstall, EasyNote removes only its **own** data: the `easynote_version`
option and the note capabilities it granted. It **never deletes user data**.
Notes and folders stay in the database.

### 7.7 Environment

Runs in the Docker Compose dev stack (see `README.md` / `CLAUDE.md`). Activate
with:

```bash
docker compose exec wordpress wp plugin activate easynote
```

---

## 8. UX and interaction details

- Two-pane layout: the sidebar tree on the left and the editor or folder page on
  the right. Navigating never reloads the page.
- The sidebar mirrors Notion: nested rows, hover actions (rename, delete),
  expand/collapse chevrons and an active-node highlight.
- The editor feels like Notion: markdown shortcuts (`#`, `-`, `>`) and a block
  options menu (duplicate, copy link, delete).
- Destructive actions ask for confirmation. Deleting a note says it goes to
  Trash; deleting a folder says its notes move to the Inbox.
- Empty states are friendly, e.g. "Add your first note".
- The layout is responsive, so the app works in mobile browsers.

---

## 9. Security and permissions

- Every REST route has an explicit `permission_callback`. Access control uses
  **WordPress capabilities** (`current_user_can()`), and EasyNote never stores
  its own auth or permissions.
- **Ownership:** note listings are filtered to the current author unless the user
  can `edit_others_easynote_notes`.
- **Sharing is author-only.** It's never delegated to users who can edit others'
  notes.
- **The public endpoint is the only unauthenticated surface.** It's kept in its
  own small class so it's easy to audit. It returns a fixed set of safe fields,
  and returns 404 for anything not both public and published.
- All input is **sanitized**: `absint`, `sanitize_title`, `rest_sanitize_boolean`,
  and JSON normalization of content meta. Output in PHP is **escaped**.
- No WordPress core file is edited, and `wp-config.php` is never hand-edited.

---

## 10. Acceptance criteria

The MVP is complete when, on a clean WordPress install with the EasyNote plugin
activated and pretty permalinks enabled:

1. **Single user:** a lone administrator can open `/notes` and create, edit and
   organize notes without creating any other user.
2. **wp-admin untouched:** wp-admin looks and behaves exactly like stock
   WordPress (apart from the permalinks notice while it applies).
3. **Login gate:** an anonymous visitor to `/notes` or `/notes/{id}` is sent to
   the WordPress login and returned to the same URL afterwards.
4. **Tree:** folders nest to any depth in the sidebar; notes appear as leaves
   under their folder.
5. **In-place editing:** selecting a note opens it in the editor pane while the
   sidebar stays visible. The URL updates and survives a reload.
6. **Content:** a note can contain text and an embedded image. Edits autosave and
   are still there after a reload.
7. **Folder page:** selecting a folder shows its title, its body and a clickable
   list of its children.
8. **Create in place:** a note or sub-folder created from a folder is placed in
   that folder.
9. **Inbox:** a note with no folder appears under the Inbox. Deleting a folder
   moves its notes there.
10. **Trash:** deleting a note moves it to Trash and it can be recovered.
11. **Ownership:** a user without `edit_others_easynote_notes` sees only their
    own notes, in both the tree and the REST API.
12. **Sharing:** after the author marks a note public, it is readable at
    `/n/{slug}` without login. Private or draft notes return 404 there.
13. **Core integrity:** no file outside `wp-content/plugins/easynote/` is
    modified. Deactivating the plugin leaves a stock WordPress site with all
    notes and folders intact.

---

## 11. Future work

- **Editor role for multi-user sites:** grant the note capabilities to `editor`,
  or a dedicated role, so non-admin users can use the app.
- **Private folders**, or a per-user folder tree. Folders are currently shared.
- **Search** across notes.
- **Drag-and-drop** to reorder and reparent items in the tree, using an order
  index in meta.
- **Version history** in the app, built on WordPress revisions.
- **Multiple workspaces.**
- **Editor tooling:** a slash menu and floating toolbar once Yoopta's tool
  packages support v6; a code block with lazy-loaded grammars.
- **Designed public pages** for shared notes (theming, SEO metadata).

---

## 12. Resolved decisions

| # | Decision point | Resolution |
|---|----------------|------------|
| 1 | Delivery vehicle | **Plugin** (not theme). See §1.1. |
| 2 | Editor | v1: Gutenberg. **v2: Yoopta (React)**, because Gutenberg's navigation broke the Notion experience. See §3. |
| 3 | UI host | v1: restyled wp-admin. **v2: headless React SPA on the front end, behind login; wp-admin stock** (Variant 2). See §3.2. |
| 4 | Data model | v1: core posts and categories. **v2: `easynote_note` CPT + `easynote_folder` taxonomy.** See §5. |
| 5 | Roles | v1: an admin plus a confined editor. **v2: a single user (even an admin) is enough; no confinement.** See §4. |
| 6 | Routing | `/notes`, `/notes/{id}`, and `/n/{slug}` for public notes. See §7.3. |
| 7 | Sharing | **Author-only public toggle**; 404 for anything private. Promoted from non-goal. See §6.5. |
| 8 | Note deletion | **Trash** (recoverable). See FR-5b. |
| 9 | Unfiled notes | **Virtual "Inbox"** node at the tree root. See §5.3. |
| 10 | Folder bodies | **Rich (Yoopta JSON) term meta** instead of plain-text descriptions. See §5.2. |
