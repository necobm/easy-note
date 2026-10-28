# EasyNote

A self-hosted, Notion-like note-taking app built on WordPress. The goal is to
**own your data**: notes live in your own server and database, while keeping
Notion's ease of use and its "open it from any device" convenience.

All of EasyNote ships as one WordPress plugin,
[`wp-content/plugins/easynote`](wp-content/plugins/easynote). Activate it and
any standard WordPress install becomes the EasyNote app. **WordPress core files
are never modified**: everything works through standard hooks, so deactivating
the plugin leaves a stock WordPress site with every note intact.

> The full product spec is in
> [`docs/specs/easynote-mvp-spec.md`](docs/specs/easynote-mvp-spec.md).

## How it works

| Concept | Implementation |
|---------|----------------|
| Note | `easynote_note` custom post type. The body is stored as Yoopta editor JSON in the `_easynote_content` meta. |
| Folder (tree node) | `easynote_folder` hierarchical taxonomy, nestable to any depth. Like a Notion page, a folder has its own body (`_easynote_content` term meta). |
| Inbox | A virtual root node that lists notes with no folder. |
| App UI | A React SPA at `/notes` and `/notes/{id}`, using the [Yoopta](https://github.com/yoopta-editor/Yoopta-Editor) block editor. It talks to WordPress over the REST API (`easynote/v1` + core `wp/v2`). |
| Sharing | Notes are private and scoped to their author. If the author turns on sharing, a note becomes readable without login at `/n/{slug}`. |
| wp-admin | Stays stock. Administrators also get a fallback edit screen for notes. |

### Repository layout

```
.
├── Dockerfile, docker-compose.yml, .env.example   # dev environment
├── docs/specs/                                    # product spec
└── wp-content/plugins/easynote/
    ├── easynote.php        # plugin header + bootstrap
    ├── inc/                # PHP: post type, taxonomy, roles, REST API, app shell
    ├── app/                # React SPA source (src/) and build output (build/)
    ├── tests/              # WP-CLI smoke test + PHPUnit suite
    └── bin/build-release.sh  # builds a distributable ZIP
```

WordPress core is **not** committed. The `wordpress` Docker image provides it
and writes it into the bind-mounted project on first boot.

## Getting started

Requirements: Docker with Compose, and Node 20+ on the host to build the React
app. The container doesn't include Node.

```bash
cp .env.example .env            # then set the passwords in .env
docker compose up -d            # builds the image, starts WordPress + MySQL

# Build the front-end app
cd wp-content/plugins/easynote/app
npm install
npm run build                   # or `npm run start` to rebuild on save
cd -
```

1. Open `http://localhost:8080` (the port comes from `WP_PORT`) and finish the
   WordPress install wizard.
2. Activate the plugin:
   ```bash
   docker compose exec wordpress wp plugin activate easynote
   ```
3. Set **Settings → Permalinks** to anything except "Plain". The app routes need
   pretty permalinks.
4. While logged in, go to `http://localhost:8080/notes`.

### Useful commands

```bash
docker compose logs -f wordpress      # tail app logs
docker compose exec wordpress wp ...  # run WP-CLI
docker compose down                   # stop (keeps the database volume)
docker compose down -v                # stop and wipe the database
```

### Tests

```bash
docker compose exec wordpress wp eval-file \
  wp-content/plugins/easynote/tests/wpcli-smoke.php --allow-root
```

The container doesn't run a PHPUnit suite yet; the test tooling isn't
installed. [`tests/README.md`](wp-content/plugins/easynote/tests/README.md)
explains how to set it up.

### Release build

```bash
bash wp-content/plugins/easynote/bin/build-release.sh   # → dist/easynote.zip
```

## Roadmap

This roadmap is based on the
[MVP spec](docs/specs/easynote-mvp-spec.md). The first prototype changed the
spec's architecture in two ways:

- **UI:** the spec puts the app inside a restyled wp-admin and uses Gutenberg.
  The current build is a **headless React SPA** on the front end, uses the
  **Yoopta** editor, and leaves wp-admin stock.
- **Data model:** the spec reuses core posts and categories. The current build
  uses a dedicated post type (`easynote_note`) and taxonomy (`easynote_folder`).

The roadmap follows the current architecture. Spec sections are cited where they
still apply.

Legend: ✅ done · 🟡 partial · ⬜ not started

### Phase 1: MVP core (spec §5, §6, §10)

- ✅ Single, implicit workspace (FR-1)
- ✅ Folder tree that nests to any depth, with notes as leaves (FR-6–FR-8, AC 3)
- ✅ Folder page with its own body content (FR-9, §5.2). Folder bodies are already rich Yoopta content, not plain text.
- ✅ Create a note or sub-folder in place under the current folder (FR-4, FR-10, AC 6)
- ✅ Virtual Inbox for notes without a folder (§5.3, FR-5b, AC 7)
- ✅ Move a note or folder to a different parent (REST endpoints and a folder picker in the editor)
- ✅ Autosave while editing
- ✅ Every capability check goes through WordPress (`current_user_can`, REST nonces), with per-author note scoping (§9)
- 🟡 Text **and embedded images** in notes (FR-3, AC 5). Text blocks work. An image block backed by the Media Library isn't set up yet.
- 🟡 Delete to Trash with recovery (FR-5a, AC 8). Delete already moves notes to Trash, but recovery only works from wp-admin. The app needs its own Trash view and restore action.
- ⬜ Sort siblings in the tree alphabetically, folders first, then notes (§5.4)
- ⬜ Friendly empty states, e.g. "Add your first note" (§8)
- ⬜ Check the responsive layout on mobile browsers (§8)

### Phase 2: Roles and access (spec §4, §6.5)

- ✅ Administrators keep the full, unchanged wp-admin (FR-12, AC 2)
- ✅ Activation ensures the `editor` role exists and gives administrators the note capabilities
- ⬜ Give the `editor` role the note capabilities so editors can use the app
- ⬜ After login, send editors to `/notes` instead of the dashboard (FR-13, AC 1)
- ⬜ Send editors who visit the public front end into the app (§7.4, AC 9)
- ⬜ Keep editors out of wp-admin screens they shouldn't use (FR-14, AC 10)
- ⬜ Decide whether folders stay shared across the workspace or become private to each user

### Phase 3: Quality and release

- ✅ WP-CLI smoke test covering registration, capabilities, the tree, ownership and REST
- ✅ Release script that produces a WordPress.org-ready ZIP
- ⬜ Get the PHPUnit suite running in the Docker image (tests/README.md)
- ⬜ Jest tests for the React app (`@wordpress/scripts test-unit-js`)
- ⬜ End-to-end (Playwright) run of the full acceptance checklist (spec §10)
- ⬜ Uninstall that removes only EasyNote's own options and meta, never notes or folders (§7.6)

### Phase 4: Future work (spec §11)

- ✅ **Sharing / public note pages** at `/n/{slug}` (delivered early)
- ✅ **Rich folder bodies** (delivered early, see Phase 1)
- ⬜ **Search** across notes
- ⬜ **Drag-and-drop** to reorder and move items in the tree (with an order index in term and post meta)
- ⬜ **Version history** in the app, built on WordPress revisions
- ⬜ **Multiple workspaces**
- ⬜ Slash menu and floating toolbar once the Yoopta tool packages support v6
- ⬜ Code block with lazy-loaded syntax grammars (left out today because of bundle size)
- ⬜ ~~Classic editor option~~. Superseded now that Yoopta replaced Gutenberg.

## License

GPL-2.0-or-later. See [`wp-content/plugins/easynote/LICENSE`](wp-content/plugins/easynote/LICENSE).
