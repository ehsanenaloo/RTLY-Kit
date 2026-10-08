# Contributing to RTLY-Kit

Thanks for helping. This guide gets you from clone to pull request. Contributions in English, Persian or Arabic are welcome. Please follow the [Code of Conduct](CODE_OF_CONDUCT.md).

## Set up (Docker)

You only need Docker. PHP and Composer run inside the container defined in `tools/docker-compose.yml`, so nothing is installed on your machine.

```bash
git clone https://github.com/ehsanenaloo/RTLY-Kit.git
cd RTLY-Kit
docker compose -f tools/docker-compose.yml run --rm php composer install
docker compose -f tools/docker-compose.yml run --rm php composer check    # PHPStan (level max) + PHPUnit + code style
```

Everyday commands (prefix each with `docker compose -f tools/docker-compose.yml run --rm php`):

| Command | What it does |
|---------|--------------|
| `composer test` | PHPUnit (both testsuites) |
| `composer phpstan` | PHPStan at level max, including `src/Laravel` |
| `composer cs` | php-cs-fixer dry run with a diff; fails if anything needs fixing |
| `composer cs-fix` | Apply the code style |
| `composer check` | PHPStan + tests + code style (what CI runs) |

Run one test file or try a snippet:

```bash
docker compose -f tools/docker-compose.yml run --rm php vendor/bin/phpunit tests/Unit/Calendar/JalaliTest.php
docker compose -f tools/docker-compose.yml run --rm php php -r 'require "vendor/autoload.php"; echo RtlyKit\Calendar\Jalali::make("2026-03-21")->format("Y/m/d");'
```

If you have PHP 8.2+ and Composer locally, the same `composer` scripts work without Docker.

## Code style

- Style is enforced by [php-cs-fixer](https://cs.symfony.com/) with the checked-in `.php-cs-fixer.dist.php` (PSR-12, PHP 8.2 migration rules, ordered imports, short arrays, trailing commas). Only formatting-level rules are enabled; the fixer must never change behaviour.
- Run `composer cs-fix` before committing; CI runs `composer cs` and fails on any difference.
- `.editorconfig` sets UTF-8, LF line endings and 4 spaces (2 for YAML, JSON and Markdown). Use an editor that honours it.

## Rules of the project

1. **Zero required dependencies.** `require` in `composer.json` contains only `php`. Optional integrations (Carbon, Laravel) go under `suggest` and `require-dev`, and must be loaded defensively.
2. **Modern, strict PHP.** Every file has `declare(strict_types=1);`. Classes are `final` and immutable. PHPStan must pass at level max.
3. **Do not copy the digit maps.** Convert Persian/Arabic digits with `RtlyKit\Number\Digits::toEnglish()`.
4. **Every bug fix ships with a test**, and every feature ships with tests and documentation.
5. **Known-answer tests only.** Test data must come from an independent source (official examples, published tables, values you verified by hand). Do not write tests that call the code under test to compute the expected value.
6. **Be accurate about accuracy.** State what a table was compared with and when. If data is not verified, say so in the docblock and in the docs, and prefer returning `null` over a guess. Wrong data is worse than none.
7. **Year-range rule.** Every calendar entry point (`make`, `create`, `createFromFormat`, timestamps, `add*` / `sub*`, helpers, Carbon macros) must throw only a `RtlyKitException` (in practice `InvalidDateException`) for out-of-range or overflowing input, never a `TypeError`, `ValueError` or `DateMalformed*` exception. Supported years: Jalali -620..9377, Hijri 1..9665, Hebrew 3762..13759 (each class exposes `MIN_YEAR` / `MAX_YEAR`; they correspond to Gregorian years 1..9999). Every new exception must extend `RtlyKitException`.
8. **Input caps.** Keep the existing limits on numeric input (4096 bytes for strings, 1000 characters for `Format::withSeparator`).

## Tests layout

- `tests/Unit/` holds unit tests and `tests/Integration/` holds the Laravel/Eloquent tests that run against real Illuminate components. They are two separate testsuites in `phpunit.xml.dist`.
- Tests mirror `src/`: the tests for `src/Calendar/Jalali.php` live in `tests/Unit/Calendar/JalaliTest.php`, and so on. Put new tests in the file that matches the class under test instead of creating `*ExtendedTest` or `*HardeningTest` files.
- Namespace: `RtlyKit\Tests\...`, matching the directory.

## Data-correction policy

Reference data (holidays, bank BINs, Sheba codes, national-code prefixes, mobile operator prefixes, the Umm al-Qura table, prayer-time cities) is compiled from public sources listed in [`resources/data/SOURCES.md`](../resources/data/SOURCES.md).

- **Every entry added or changed needs at least two independent sources.** Link both in the pull request and record them in `resources/data/SOURCES.md`.
- Sources that share an ancestor (copies of the same community dataset) count as one.
- Prefer official publications (central bank, regulator, observatory, ministry) over blogs and forks.
- If you cannot find two sources, open a data-correction issue instead and say what you found; do not add the entry.
- Add or update a known-answer test that pins the corrected value.

## Commit style

- Small, focused commits with an imperative subject of at most about 72 characters, prefixed with a type: `fix:`, `feat:`, `docs:`, `test:`, `refactor:`, `chore:`, `ci:` (for example `fix: reject Hebrew year 13760 in addYears`).
- Explain the why in the body when it is not obvious, and reference the issue (`Fixes #123`).
- Do not commit generated files, `vendor/` or `composer.lock`.

## Pull requests

- Branch from `main`, keep a PR focused on one change.
- Make sure `composer check` passes.
- Update the guide: edit the fragments in `tools/docs/content/{fa,en,ar}/` (the same page and the same ids in all three languages; maintainers will help translate) and run `composer docs` (never edit `docs/` by hand) and add a line under **Unreleased** in `CHANGELOG.md`.
- Describe what changed and why. For data changes link your sources.

## Documentation

- Code samples in the docs must run. Run them through Docker before you submit.
- Voice: second person, present tense, active. Use short sentences and everyday words. Say what was checked, how and when, in a calm tone, and keep the few limits in one short "Good to know" note.
- Primary language is Persian for the project, with English and Arabic translations; English docs are the reference for code samples.
- Write the project name as **RTLY-Kit** (not `RTLY-KIT` or `Rtly Kit`). Namespaces and package names (`RtlyKit`, `enaxon/rtly-kit`) keep their code spelling.

## Reporting bugs and vulnerabilities

Open an issue using the templates, with the smallest reproducing snippet, the expected result and the source for it (for example a published calendar page). Report security problems privately as described in [`SECURITY.md`](SECURITY.md).

## License

By contributing you agree that your contribution is licensed under the MIT License.
