# EasyNote — MVP Specification

- **Status:** Draft v1
- **Date:** 2026-07-10
- **Owner:** Néstor Brito
- **Applies to:** WordPress 7.0.1 (dev environment in this repo)

---

## 1. Vision

EasyNote is a self-hosted, Notion-like note-taking application built entirely on
top of WordPress. The goal is to **own our data** — notes live in our own server
and database — while preserving the ease of use of Notion's UI and its
"access from any device with an internet connection" convenience.

Rather than build a new application, EasyNote **repurposes the WordPress Admin
Panel as the primary UI**. WordPress already provides authentication, user
management, a rich editor, a hierarchical taxonomy, and a content type for
documents. EasyNote wires these primitives together into a nested, Notion-style
notes workspace.

### 1.1 Guiding principle

> The entire feature set is delivered as a **custom WordPress plugin**. Installing
> and activating that plugin turns any standard WordPress installation into the
> EasyNote app. **WordPress core files must never be modified.** All behavior is
> achieved through standard WordPress hooks (actions and filters) from the
> plugin's main file and its supporting classes.

The plugin approach (chosen over a theme) keeps EasyNote's behavior independent
of the active theme: it survives theme switches and doesn't couple app logic to
site presentation. The site's active theme is irrelevant to EasyNote because the
app UI lives entirely in the WordPress admin panel.

---

## 2. Goals and non-goals

### 2.1 Goals (MVP)

- Single-workspace note-taking on self-hosted WordPress.
- Notes are plain documents: text and embedded images.
- Notes are organized in an arbitrarily nestable tree, browsable from the admin
  sidebar, like Notion's page tree.
- Two roles: **admin** (full WordPress) and **editor** (EasyNote-only UI).
- Zero core modifications; everything ships in one activatable plugin.

### 2.2 Non-goals (explicitly out of scope for MVP)

- Multiple workspaces / workspace switching.
- Real-time collaborative editing, comments, mentions.
- Sharing / public publishing of notes, permalinks for public consumption.
- Databases/tables, kanban boards, or other Notion "database" views.
- Full-text search UI (beyond what WordPress offers by default).
- Mobile-native apps (the responsive admin panel serves mobile browsers).
- Import/export from Notion.

---

## 3. Key design decision: editor choice

The source brief contains a contradiction:

- The feature list says notes use *"the legacy WordPress editor"*.
- The closing instruction says: *"I want a first version using the Gutenberg
  editor for creating the notes, to see how it goes."*

**Decision for MVP:** use the **Gutenberg (block) editor** — the default
WordPress post editor — for creating and editing notes. This is the native,
zero-configuration path and requires no core changes.

The Classic (legacy) editor remains a documented fallback (see
[§11 Future work](#11-future-work)); switching to it would be a plugin option,
not a rewrite of the data model.

---

## 4. Personas and roles

| Role | WordPress base role | Admin menu | Capabilities |
|------|--------------------|------------|--------------|
| **Admin** | `administrator` | Standard, unchanged WordPress admin menu | Everything. Manages the site, users, theme, and notes. |
| **Editor** | `editor` | **Replaced** with the EasyNote note tree only | Create/edit/delete notes and categories; upload images. Cannot see WordPress-native admin items (plugins, themes, tools, settings, comments, users, etc.). |

Notes:

- We reuse WordPress's **native `editor` role** rather than inventing a new one.
  The `editor` role already grants full post and category management plus media
  upload, and lacks site-administration capabilities — which matches the intent.
- All authentication, password handling, sessions, and permission checks are
  delegated to **WordPress core security** (`current_user_can()`, nonces,
  capabilities). EasyNote adds no parallel auth system.
- Because EasyNote is a plugin, the `editor` role is ensured/repaired on
  **plugin activation** (and left intact on deactivation).

---

## 5. Data model

EasyNote maps Notion concepts onto **built-in** WordPress objects. No custom
post types or custom taxonomies are introduced for the MVP.

| Notion concept | WordPress object | Field mapping |
|----------------|------------------|---------------|
| Workspace | The whole site (implicit) | Single, implicit. No object needed. |
| Section / folder page (tree node) | **Category** (`category` taxonomy term) | `name` → title; `description` → the node's text content; `parent` → nesting. |
| Note (leaf) | **Post** (`post` type) | `post_title` → title; `post_content` → body (Gutenberg blocks: text + images). |
| Note location in the tree | Post → Category assignment | A post assigned to a category is a leaf under that category. |
| Embedded images | Media library attachments | Standard WordPress media, referenced by image blocks. |

### 5.1 The tree

- **Interior nodes are categories.** WordPress categories are hierarchical
  natively (`parent` term field), giving us arbitrary-depth nesting for free.
- **Leaf nodes are posts.** A post is a note; it lives under the category it is
  assigned to.
- A category can contain **both** child categories and posts (mirroring Notion,
  where a page can have sub-pages of any kind).

```
Workspace (implicit root)
├── Category: "Work"                     (term, description = intro text)
│   ├── Category: "Projects"             (child term)
│   │   ├── Post: "EasyNote spec"        (leaf note)
│   │   └── Post: "Q3 roadmap"           (leaf note)
│   └── Post: "Meeting notes 2026-07"    (leaf note)
└── Category: "Personal"
    └── Post: "Reading list"
```

### 5.2 Category as a "page"

In Notion, every node is itself a viewable page with its own body content.
EasyNote reproduces this: a **category's `description` field holds its text
content**. When a category is opened, EasyNote shows its title, that description
text, and the clickable list of its children.

- MVP stores the description as **plain text** (the native term description
  field). Rich formatting of category bodies is future work (see §11).

### 5.3 The "Inbox" (uncategorized notes)

Notes that are not assigned to any category are never lost: they surface under a
**virtual "Inbox" node** at the root of the tree. "Virtual" means it is not a
stored category — it is a synthesized node that lists all posts with no EasyNote
category assignment. This lets editors capture a note quickly without first
choosing a parent; the note can be filed into a category later. (Implementation
detail: query posts in the `Uncategorized` default term / with no meaningful
category assignment and present them under Inbox.)

### 5.4 Ordering

WordPress categories and posts have no intrinsic sibling ordering that matches a
Notion menu. For MVP, the tree is ordered **alphabetically by title** within each
parent (categories first, then posts, or interleaved — see §6.3). Manual
drag-to-reorder is future work and would be implemented with **term meta** /
post meta storing an order index (no core change required).

---

## 6. Functional requirements

### 6.1 Single workspace

- FR-1. There is exactly one workspace, implicit and global. No UI for creating
  or switching workspaces.

### 6.2 Notes (create / edit)

- FR-2. A note is a WordPress **post** edited with the **Gutenberg** editor.
- FR-3. A note supports **text and embedded images** at minimum. Images use the
  standard media library and the core Image block.
- FR-4. Creating a note is initiated from within the EasyNote UI (e.g. an
  "Add note" affordance on a category page or in the sidebar) and, where
  possible, **pre-assigns the note to the category it was created from**.
- FR-5. Opening a note opens the standard post edit screen (view = edit, as in
  Notion). No separate read-only view is required for MVP.
- FR-5a. Deleting a note **moves it to Trash** (recoverable), using WordPress's
  native trash. Editors get a recover path; permanent deletion is not the default
  action.
- FR-5b. Notes with no category assignment appear under the virtual **Inbox**
  node (§5.3), so quick-captured notes are never orphaned.

### 6.3 Nested navigation menu (the sidebar tree)

- FR-6. The WordPress admin **sidebar** presents the category→note tree, styled
  and behaving like Notion's page tree (nested, expandable).
- FR-7. Each tree item is clickable:
  - Clicking a **category** opens the EasyNote **category page** (§6.4).
  - Clicking a **post** opens that note in the editor (§6.2).
- FR-8. The tree reflects the live category hierarchy and post assignments.

**Sidebar depth — decided.** The native WordPress admin menu API
(`add_menu_page` / `add_submenu_page`) supports only **two levels**
(top-level + one submenu), but the Notion tree is arbitrarily deep. EasyNote
therefore **renders a fully custom, collapsible tree** in the admin sidebar:
register a single EasyNote top-level menu, then inject the deep tree markup and
style/animate it with the plugin's own **CSS and a small JS** (expand/collapse,
active-node highlight) enqueued via `admin_enqueue_scripts`. This delivers true
Notion-depth nesting to arbitrary levels.

### 6.4 Category page (Notion-like node view)

- FR-9. Opening a category renders an admin screen showing:
  1. The category **title** (term name).
  2. The category **content** — the term `description` text, if any.
  3. A **list of children** — child categories and posts under this category —
     each **clickable** and routing per FR-7.
- FR-10. The page provides affordances to **add a child** (new sub-category and
  new note under this category) and to **edit** this category's title/description.
- FR-11. Layout and typography evoke Notion (clean single column, generous
  spacing). It reuses WordPress admin styling and the plugin's CSS; no external UI
  framework is required for MVP.

### 6.5 Role-based admin experience

- FR-12. For **admin** users, the WordPress admin menu is **unchanged**. Admins
  retain full access, including a link into EasyNote.
- FR-13. For **editor** users:
  - All WordPress-native admin menu items are **removed** (Posts, Media,
    Comments, Appearance, Plugins, Tools, Settings, Users, Dashboard, etc.).
  - The menu is **replaced** by the EasyNote nested category→note tree (§6.3).
  - The admin bar and dashboard are cleaned of WordPress-native items.
  - After login, editors are **redirected** to the EasyNote home (the workspace
    root / first category page) rather than the WordPress dashboard.
- FR-14. Removal is **presentational and capability-aware**: menu items are
  hidden for editors, and direct-URL access to disallowed screens is guarded by
  WordPress capabilities (editors already lack `manage_options`, etc.). EasyNote
  hides what editors shouldn't use; it does not grant editors anything beyond the
  native `editor` role.

---

## 7. Architecture

### 7.1 Delivery vehicle: a custom plugin

EasyNote is a single custom plugin (working name: `easynote`) under
`wp-content/plugins/easynote/`. Activating it turns the site into EasyNote,
regardless of which theme is active.

```
wp-content/plugins/easynote/
├── easynote.php             # plugin header + bootstrap; registers all hooks
├── uninstall.php            # cleanup on uninstall (see §7.6)
├── inc/
│   ├── class-easynote.php            # bootstrap / DI
│   ├── class-activator.php           # activation/deactivation (roles, caps)
│   ├── class-roles.php               # role & capability shaping
│   ├── class-admin-menu.php          # remove native menus, register EasyNote
│   ├── class-sidebar-tree.php        # builds & renders the nested tree
│   ├── class-category-page.php       # Notion-like category screen (§6.4)
│   ├── class-notes.php               # note (post) helpers, create-in-category
│   └── class-redirects.php           # login/dashboard/front-end redirects
└── assets/
    ├── css/easynote-admin.css        # Notion-like admin styling + tree
    └── js/easynote-admin.js          # sidebar expand/collapse, interactions
```

EasyNote is theme-agnostic: the app UI lives entirely in the WordPress admin, so
the site's active theme is left untouched. Editors never see the front-end theme
(they are redirected into the admin app — see §7.4).

### 7.2 Hooks used (all standard WordPress, no core edits)

| Concern | Hook(s) | Purpose |
|---------|---------|---------|
| Bootstrap | `plugins_loaded`, `init` | Plugin load, register handlers. |
| Activation / deactivation | `register_activation_hook`, `register_deactivation_hook` | Ensure/repair the `editor` role and caps on activate; no destructive changes on deactivate. |
| Menu replacement | `admin_menu`, `admin_head` | `remove_menu_page()` / `remove_submenu_page()` for editors; `add_menu_page()` / `add_submenu_page()` for EasyNote screens. |
| Sidebar tree render | `admin_menu` + custom output, `admin_enqueue_scripts` | Inject the nested collapsible tree; enqueue CSS/JS. |
| Category page render | Page render callback passed to `add_menu_page`/`add_submenu_page` | Render the Notion-like node view. |
| Roles / capabilities | (via the activation hook above) | Ensure the `editor` role exists with the right caps; no new capabilities invented for MVP. |
| Note creation defaults | `load-post-new.php`, `default_category` / query args | Pre-assign the category when creating a note from a category page. |
| Admin bar cleanup | `admin_bar_menu`, `wp_before_admin_bar_render` | Strip WordPress items for editors. |
| Dashboard cleanup | `wp_dashboard_setup` | Remove default dashboard widgets for editors. |
| Redirects | `login_redirect`, `admin_init`, `template_redirect` | Send editors to EasyNote home after login; bounce them off disallowed admin screens; redirect editors from the public front end into the app. |
| Asset loading | `admin_enqueue_scripts` | Load `easynote-admin.css` / `.js` only in admin. |

### 7.3 Routing

- **Category page:** an EasyNote admin screen, e.g.
  `wp-admin/admin.php?page=easynote&node=<term_id>`. The render callback reads
  `node`, loads the term, and renders title + description + children.
- **Note:** the native post edit screen,
  `wp-admin/post.php?post=<id>&action=edit` (Gutenberg). "Add note" uses
  `post-new.php` with the target category pre-selected.
- **Home:** `admin.php?page=easynote` with no node → workspace root (top-level
  categories).

### 7.4 Front end

The MVP UI *is* the admin panel; there is no public-facing site to design. The
active site theme renders the front end unchanged. **Editors are redirected from
the public front end into the EasyNote admin app** (via a `template_redirect`
check on `current_user_can()`), so an editor always lands in the app. Admins and
anonymous visitors see the normal front end. Public front-end note rendering is
future work.

### 7.5 Environment

Runs in the existing Docker Compose dev stack (see `CLAUDE.md`): WordPress +
MySQL, bind-mounted project, WP-CLI available in the container. Activate with:

```bash
docker compose exec wordpress wp plugin activate easynote
```

### 7.6 Uninstall

On uninstall, EasyNote removes only its **own** settings (e.g. plugin options and
any term/post meta it created for ordering). It **never deletes user data** —
notes (posts) and categories (terms) are standard WordPress objects and remain
intact so the site degrades gracefully to stock WordPress.

---

## 8. UX and interaction details

- The sidebar tree mirrors Notion: nested rows, hover affordances, an
  expand/collapse chevron on nodes that have children, and a subtle "active"
  highlight on the current node.
- A category page reads top-to-bottom: **title**, **body text**, then a
  **children list** where categories and posts are visually distinguishable
  (e.g. folder-like vs document-like icons) and clicking navigates per FR-7.
- "Add note" and "Add sub-page" affordances are present on each category page and
  create the child already parented to the current node.
- Empty states are friendly (e.g. an empty category shows an "Add your first
  note" prompt).
- The experience is responsive; the WordPress admin is already mobile-capable,
  satisfying the "any device with internet" requirement.

---

## 9. Security and permissions

- All access control uses **WordPress capabilities** via `current_user_can()`.
  EasyNote never rolls its own auth or permission storage.
- Menu hiding for editors is a UX measure; **capability checks are the real
  guard**. Editors lack `manage_options`, `edit_theme_options`,
  `activate_plugins`, `list_users`, etc., so disallowed screens remain protected
  even by direct URL, and `class-redirects.php` bounces editors away from them.
- All EasyNote admin actions (create/edit/delete category or note, reorder) use
  **nonces** and verify capabilities server-side.
- All output is **escaped** (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`
  for note content) and all input **sanitized** (`sanitize_text_field`,
  `wp_kses_post`, term/post APIs) per WordPress standards.
- No WordPress core file is edited; there is no `wp-config.php` hand-editing
  (it's generated by the image entrypoint per `CLAUDE.md`).

---

## 10. Acceptance criteria

The MVP is complete when, on a clean WordPress install with the EasyNote
plugin activated:

1. **Editor experience:** logging in as an `editor` shows the EasyNote sidebar
   tree instead of the WordPress menu; no WordPress-native admin items are
   visible; the user lands on the EasyNote home after login.
2. **Admin experience:** logging in as an `administrator` shows the full,
   unchanged WordPress admin menu plus an entry point into EasyNote.
3. **Tree:** categories nest to arbitrary depth in the sidebar; posts appear as
   leaves under their assigned category.
4. **Category page:** clicking a category shows its title, its description text,
   and a clickable list of its child categories and posts.
5. **Note:** clicking a post opens it in the Gutenberg editor; a note can contain
   text and an embedded image, and saves correctly.
6. **Create-in-place:** a note or sub-category created from a category page is
   parented to that category.
7. **Inbox:** a note created without a category appears under the virtual Inbox
   node at the tree root.
8. **Trash:** deleting a note moves it to Trash and it can be recovered.
9. **Front end:** an editor visiting the public front end is redirected into the
   EasyNote admin app.
10. **Security:** an editor cannot reach WordPress settings/plugins/users/tools by
    direct URL.
11. **Core integrity:** no file outside `wp-content/plugins/easynote/` is
    modified; deactivating the plugin restores stock WordPress with all
    notes/categories intact (data lives in standard posts/terms).

---

## 11. Future work

- **Classic (legacy) editor** option, per the original brief, as a plugin
  setting.
- **Rich category bodies** (blocks/HTML) instead of plain-text term descriptions.
- **Manual drag-and-drop ordering** of tree siblings (term/post meta order index).
- **Search** UI across notes.
- **Multiple workspaces.**
- **Sharing / public note pages** with a designed front end.
- **Version history** surfaced in the EasyNote UI (WordPress revisions already
  exist under the hood). Trash is already in the MVP (FR-5a).
- **Move / reparent** notes and categories via drag-and-drop.

---

## 12. Resolved decisions

All initial open questions have been resolved with the project owner:

| # | Decision point | Resolution |
|---|----------------|------------|
| 1 | Delivery vehicle | **Plugin** (not theme) — see §7.1. |
| 2 | Sidebar depth | **Custom collapsible tree** via CSS/JS, arbitrary depth — see §6.3. |
| 3 | Category page routing | **Single screen** `admin.php?page=easynote&node=<term_id>` — see §7.3. |
| 4 | Editor front-end access | **Redirect editors** into the admin app — see §7.4. |
| 5 | Note deletion | **Trash** (recoverable) — see FR-5a. |
| 6 | Uncategorized notes | **Virtual "Inbox"** node at the tree root — see §5.3. |
| 7 | Editor | **Gutenberg** for the first version — see §3. |
