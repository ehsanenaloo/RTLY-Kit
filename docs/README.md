# RTLY-Kit documentation site

Static HTML guide for the `enaxon/rtly-kit` library, in Persian (`fa/`, right-to-left), English (`en/`) and Arabic (`ar/`, right-to-left). It needs no build step to read, no external services and no JavaScript to be complete. With JavaScript it adds site search (Persian and Arabic aware), a theme switch, guide filtering and copy buttons.

Start at [index.html](index.html) (language chooser), or go straight to the [Persian](fa/index.html), [English](en/index.html) or [Arabic](ar/index.html) home page.

## Layout

```
docs/
  index.html          language chooser
  styles.css          one stylesheet for both directions (CSS logical properties)
  app.js              theme, search, filter, copy buttons (progressive enhancement)
  llms.txt            English page index for tools
  sitemap.xml         every page with absolute URLs and language alternates
  robots.txt          allows all, points to sitemap.xml
  assets/             logo, favicon, social-preview.svg
  fa/                 index.html, all.html, <slug>.html, search-index.js, llms.txt
  en/                 index.html, all.html, <slug>.html, search-index.js
  ar/                 index.html, all.html, <slug>.html, search-index.js, llms.txt
```

## These files are generated

Do not edit anything in this directory by hand. The pages are produced by `tools/docs/build.php` from:

- `tools/docs/content/<lang>/<slug>.html` - the guide fragments (format in `tools/docs/CONTENT-SPEC.md`)
- `tools/docs/spec.php` - groups, slugs and reading order
- `tools/docs/strings.php` - navigation labels, group names and home page copy (fa, en and ar)
- `tools/docs/assets/` - stylesheet, script and images copied as they are

## Rebuild

From the repository root (PHP is run through Docker on machines without it):

```sh
docker compose -f tools/docker-compose.yml run --rm -T php php tools/docs/build.php
```

The build validates every fragment (header keys, allowed markup, unique ids, links to existing slugs and ids, fa/en/ar id parity) and stops with a non-zero exit code and a message for each problem. Useful options:

- `--allow-missing` build even if some pages from the spec are not written yet (they are listed as a warning)
- `--check` build in memory and fail if this directory differs from the result (for CI)
- `--content=DIR`, `--out=DIR`, `--spec=FILE` build from or into other locations
- `--quiet`, `--help`

## Local preview

Any static file server works, or open `index.html` from disk. For example, from the repository root:

```sh
docker compose -f tools/docker-compose.yml run --rm -T -p 8080:8080 php php -S 0.0.0.0:8080 -t docs
```

Then open http://localhost:8080/.

## Notes

- The statistics on the home page (calendars, validators, helpers, prayer-time methods, message languages, guides) are counted from the source tree at build time, not typed by hand.
- Search normalises Persian and Arabic text (alef, ya, kaf and heh variants, diacritics, tatweel, ZWNJ, digit sets), so `كتاب` finds `کتاب` and `۱۴۰۵` finds `1405`.
- `assets/social-preview.svg` is the artwork for the repository social preview; export it to a 1280x640 PNG to upload on GitHub.
- Every page carries a canonical link, `hreflang` alternates for the other languages and Open Graph tags; absolute URLs use the site address in `strings.php` (`site.url`, GitHub Pages).
- All prose, CSS and JavaScript here were written for RTLY-Kit. Fonts are the visitor's system fonts; nothing is loaded from other sites.
