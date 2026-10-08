## What and why

<!-- One focused change. Link the issue if there is one (for example "Fixes #123"). -->

## Type of change

- [ ] Bug fix
- [ ] New feature
- [ ] Data change (holidays, bank BINs, prefixes, tables)
- [ ] Documentation or tooling only

## Checklist

- [ ] `composer check` passes (PHPStan, PHPUnit, code style)
- [ ] Tests added or updated (known-answer values from an independent source)
- [ ] Docs updated (fragments in `tools/docs/content/`, then `composer docs`; `composer docs:check` passes) and a line added under **Unreleased** in `CHANGELOG.md`
- [ ] Data changes cite at least two sources and update `resources/data/SOURCES.md`
- [ ] Calendar entry points still throw only `RtlyKitException` for out-of-range input
- [ ] No new required dependency, and no breaking change (or it is called out above)
