# Releasing RTLY-Kit

Maintainer checklist. Releases are cut from `main`; the tag triggers everything else.

## 1. Prepare

1. Decide the version ([Semantic Versioning](https://semver.org/)). Before 1.0, breaking changes bump the minor.
2. In `CHANGELOG.md` rename **Unreleased** to `## [X.Y.Z] - YYYY-MM-DD`, add a fresh empty `## [Unreleased]` above it, update the compare links at the bottom of the file (`[Unreleased]` now compares `vX.Y.Z...HEAD`, and add `[X.Y.Z]` comparing the previous tag with `vX.Y.Z`), and make sure the section is complete: the GitHub Release notes are extracted from it verbatim. The release workflow refuses to run if the newest released section does not match the tag.
3. Update any version-specific text in the docs (`UPGRADE.md` for breaking changes), then rebuild the guide: `composer docs`.
4. Run the full gate (Docker, no local PHP needed):

   ```bash
   docker compose -f tools/docker-compose.yml run --rm php composer validate --strict --no-check-lock
   docker compose -f tools/docker-compose.yml run --rm php composer check
   docker compose -f tools/docker-compose.yml run --rm php composer docs:check
   ```

5. Commit (`chore: release X.Y.Z changelog`), push to `main` through a pull request if branch protection requires one, and wait until the CI run on that exact commit is green. That run includes mutation testing, which can take about an hour on a push to `main`. The release workflow checks this itself: it takes the newest CI run for that commit that was started by a push and requires it to be green. A run that was cancelled by a later push, or a pull request run on the same commit, does not count; if the newest push run is not green, re-run it before tagging.

## 2. Public repository

If the public repository is fed from a private development repository, export the public tree with the maintainers' export helper into an empty directory, review the diff against the public repository, and commit and push it to `main` there. The tag must point at a commit that is on `main` of the repository that publishes the release.

## 3. Tag and push

Use an annotated, signed tag if you have a signing key:

```bash
git tag -a vX.Y.Z -m "RTLY-Kit X.Y.Z"      # add -s to sign
git push origin vX.Y.Z
```

Pre-releases use a SemVer suffix (`v0.2.0-rc.1`) and are marked as pre-release automatically.

Optional dry run, before tagging for real: Actions > Release > Run workflow, with an existing tag. It runs every check and builds the archive but publishes nothing; the archive is kept as a workflow artifact for 14 days.

## 4. Verify the release workflow

The tag starts the **Release** workflow:

| Job | What it does |
|-----|--------------|
| Verify tag | tag is valid SemVer, matches the newest CHANGELOG section, is on `main`, and the newest CI run on a push for the commit is green (mutation testing included) |
| Build release archive | `git archive` (honours `export-ignore`, the same content Composer installs), `.tar.gz` and `.zip`, `SHA256SUMS`, release notes |
| Attest build provenance | GitHub artifact attestation for both archives |
| Publish GitHub release | creates the Release with archives, checksums and notes |

Then check:

- The Release page shows the notes, two archives and `SHA256SUMS`.
- Provenance verifies: `gh attestation verify rtly-kit-X.Y.Z.tar.gz --repo ehsanenaloo/RTLY-Kit`.
- Checksums verify: `sha256sum --check SHA256SUMS`.

If a job fails before publishing, fix the cause on `main`, delete the tag locally and on the remote, and tag again. Never move a tag that has a published Release; ship a patch release instead.

## 5. After publishing

- **Packagist** updates automatically through its GitHub webhook. Confirm the new version appears at <https://packagist.org/packages/enaxon/rtly-kit>. If it does not, use "Update" on the package page and check the webhook under repository Settings > Webhooks.
- **GitHub Pages** (the guide at <https://ehsanenaloo.github.io/RTLY-Kit/>) builds from `/docs` on `main`; check the Pages deployment finished and the new text is live.
- Smoke test in a clean project: `composer require enaxon/rtly-kit:X.Y.Z` and run one call from the README.
- Announce if appropriate.

## Security releases

Prepare the fix in a private fork of the advisory (Security > Advisories), release as a patch, and publish the advisory right after the release. See [SECURITY.md](SECURITY.md).
