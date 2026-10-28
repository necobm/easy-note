# EasyNote

A self-hosted, Notion-like note-taking app built on WordPress. The goal is to
**own your data**: notes live in your own server and database, while keeping
Notion's ease of use and its "open it from any device" convenience.

All of EasyNote ships as one WordPress plugin,
[`wp-content/plugins/easynote`](wp-content/plugins/easynote). Activate it and
any standard WordPress install becomes the EasyNote app. **WordPress core files
are never modified**: everything works through standard hooks, so deactivating
the plugin leaves a stock WordPress site with every note intact.

> The full product spec, including why the UI moved from Gutenberg in wp-admin
> to a headless React app, is in
> [`docs/specs/easynote-mvp-spec.md`](docs/specs/easynote-mvp-spec.md).

## How it works

| Concept | Implementation |
|---------|----------------|
| Note | `easynote_note` custom post type. The body is stored as Yoopta editor JSON in the `_easynote_content` meta. |
| Folder (tree node) | `easynote_folder` hierarchical taxonomy, nestable to any depth. Like a Notion page, a folder can have its own body (`_easynote_content` term meta). |
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

This roadmap tracks the [MVP spec (v2)](docs/specs/easynote-mvp-spec.md). The
spec was revised after the first prototype: Gutenberg inside wp-admin was
replaced by a headless React app with the Yoopta editor (spec §3). References
below point to spec sections (FR = functional requirement, AC = acceptance
criterion in §10).

Legend: ✅ done · 🟡 partial · ⬜ not started

### Phase 1: MVP core

- ✅ Single, implicit workspace (FR-1)
- ✅ Single user is enough, even a lone admin; wp-admin stays stock (§4, FR-15, AC 1–2)
- ✅ `/notes` gated by the WordPress login, with a redirect back afterwards (§7.3, AC 3)
- ✅ Sidebar tree with folders nested to any depth, and notes as leaves (FR-6, AC 4)
- ✅ Notes open in place: the sidebar stays visible and `/notes/{id}` is bookmarkable (FR-5, FR-7, AC 5)
- ✅ Autosave (FR-5a)
- ✅ Virtual Inbox for notes without a folder; deleting a folder moves its notes there (§5.3, AC 9)
- ✅ Move a note to another folder from the editor (FR-5d)
- ✅ Per-author note scoping in both the tree and REST (§9, AC 11)
- ✅ Author-only public sharing at `/n/{slug}`, with 404 for private notes (§6.5, AC 12)
- 🟡 Text **and embedded images** (FR-3, AC 6). Text blocks work; a Media Library image block isn't set up yet.
- 🟡 Trash with recovery (FR-5b, AC 10). Deleting moves a note to Trash, but recovery only works from wp-admin. The app needs a Trash view.
- 🟡 Tree management (FR-8). Notes and root folders can be created, renamed and deleted. Creating sub-folders and reparenting folders from the UI is missing; the REST endpoint for reparenting exists.
- ⬜ Folder page showing the title, an editable body and a list of children (FR-9–FR-10, AC 7–8). The backend already stores the body (`_easynote_content` term meta).
- ⬜ Alphabetical sibling ordering (§5.4)
- ⬜ Friendly empty states and a mobile layout check (§8)

### Phase 2: Quality and release

- ✅ WP-CLI smoke test covering registration, capabilities, the tree, ownership and REST
- ✅ Uninstall that removes only EasyNote's option and capabilities, never notes or folders (§7.6)
- ✅ Release script that produces a WordPress.org-ready ZIP
- ⬜ Get the PHPUnit suite running in the Docker image (tests/README.md)
- ⬜ Jest tests for the React app (`@wordpress/scripts test-unit-js`)
- ⬜ End-to-end (Playwright) run of the full acceptance checklist (spec §10)

### Phase 3: Future work (spec §11)

- ⬜ Note capabilities for `editor` (or a dedicated role) on multi-user sites
- ⬜ Private folders, or a per-user folder tree
- ⬜ Search across notes
- ⬜ Drag-and-drop to reorder and reparent in the tree
- ⬜ Version history in the app, built on WordPress revisions
- ⬜ Multiple workspaces
- ⬜ Slash menu and floating toolbar (waiting for Yoopta v6 tool packages), plus a lazy-loaded code block
- ⬜ Designed public pages for shared notes

## License

GPL-2.0-or-later. See [`wp-content/plugins/easynote/LICENSE`](wp-content/plugins/easynote/LICENSE).
