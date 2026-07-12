# EasyNote app (React source)

This directory holds the **human-readable source** for EasyNote's front-end single-page app. The files shipped in `build/` are compiled from `src/` here — nothing is obfuscated.

## Build

Requires Node 20+ (the WordPress container does **not** include Node; build on the host).

```bash
cd wp-content/plugins/easynote/app
npm install          # or: npm ci  (uses package-lock.json)
npm run build        # production build → app/build/*.js + *.css + *.asset.php
```

During development:

```bash
npm run start        # webpack watch; rebuilds app/build/* on save
```

Because the plugin directory is served directly by WordPress, a rebuilt `build/`
is live immediately — reload the browser tab at `/notes`. There is no HMR; you
reload manually.

## What gets built

Two entry points, each emitting a `*.js`, `*.css` and a `*.asset.php` dependency
manifest that the plugin's PHP enqueues:

- `index` — the logged-in app (`/notes`, `/notes/{id}`).
- `public` — the read-only public note view (`/n/{slug}`).

`react`/`react-dom` are **not** bundled; `@wordpress/scripts` externalizes them to
WordPress core's registered handles, and `app/build/*.asset.php` lists them as
dependencies.

## Distribution

`build/` and `node_modules/` are git-ignored. For a WordPress.org release the
compiled `build/` **is** included in the ZIP (WordPress.org plugins must run with
no build step) alongside this `src/` — see `bin/build-release.sh`.

## Notes on dependencies

- Yoopta core/blocks/marks are pinned to `6.0.5`. The separate slash-menu/toolbar
  tool packages are not yet compatible with Yoopta 6.x and are intentionally
  omitted; block insertion works via markdown shortcuts (`#`, `-`, `>`).
- The `@yoopta/code` block is excluded on purpose: it bundles Shiki with every
  language grammar (~11 MB). Inline code is still available via the code mark.
