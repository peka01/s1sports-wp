# S1 Sports WordPress

Production site: https://s1sports.se (Swedish) and https://s1sports.co (English), one WordPress install with WPML, one domain per language.

Read `AGENTS.md` before changing code; its rules apply here too.

## Repo

- Fork of github.com/maxloman97/s1sports-wp. `origin` is the fork (peka01/s1sports-wp), `upstream` is Max's original.
- `s1sports-theme/` is the active theme, `s1sports-content/` the content plugin. Nothing deploys automatically: a change reaches the site only when the theme or plugin is uploaded to WordPress.
- `scripts/wp.mjs` is a REST API client for the live site. Credentials live in `.env` in the repo root (an Application Password, admin rights). `.env` is gitignored; never print, commit or copy its contents.

## Where each kind of change lives

- Page text, images and layout for the 18 mirrored pages: static HTML in `s1sports-theme/snapshots/` plus `assets/mirror.css`. Editing these pages in wp-admin or through the API does not change what visitors see.
- Athletes (`atleter` post type), media and the Artiklar/Articles pages: live WordPress data, editable in wp-admin or with `scripts/wp.mjs`.
- Live athlete ACF fields are `namn`, `sport`, `skola`, `bio`, `profilbild`, `om_atleten` (field group "Atleter", created in wp-admin, not in code), plus `s1_featured` and `s1_display_order` from the plugin. The READMEs' `s1_sport`/`s1_college` names are out of date.
- The editor (Emma) works only in wp-admin. New features should be editable there.

## Rules

- The live site is production. Ask for an explicit go-ahead before every change to it, and say exactly what will change.
- `scripts/wp.mjs` refuses anything but GET unless `--write` is passed. `--en` targets the English domain.
- Swedish and English versions are separate WPML-linked posts with different IDs; change both when content should match.
- After theme changes run `node scripts/verify.mjs` and `node --check s1sports-theme/assets/mirror.js`.

## Examples

    node scripts/wp.mjs check
    node scripts/wp.mjs GET "/wp/v2/atleter?per_page=100&_fields=id,slug,title,acf"
