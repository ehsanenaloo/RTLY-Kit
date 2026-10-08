# Security Policy

## Supported versions

RTLY-Kit is pre-1.0. Only the newest `0.x` minor release line receives security fixes; older lines do not. Upgrade before reporting an issue against an older one.

| Version | Supported |
|---------|-----------|
| latest `0.x` minor | Yes |
| older `0.x` minors | No |

After 1.0 this table will list the latest minor of each supported major.

## Reporting a vulnerability

Report privately through GitHub's private vulnerability reporting: open <https://github.com/ehsanenaloo/RTLY-Kit/security/advisories/new> (Security tab, "Report a vulnerability").

Do not open a public issue, pull request or discussion for a suspected vulnerability. Include the affected version, a minimal reproduction and the impact you expect. If you cannot use GitHub advisories, open a public issue that says only "I need to report a security problem privately" (no details) and a maintainer will arrange another channel.

## What to expect

RTLY-Kit is maintained by volunteers. These are targets, not guarantees.

| Step | Target |
|------|--------|
| Acknowledgement | within 3 business days |
| Assessment and severity | within 7 days |
| Fix or mitigation, Critical | within 7 days of confirmation |
| Fix or mitigation, High | within 30 days |
| Fix or mitigation, Medium | within 90 days |
| Low severity | next regular release |

Fixes ship as a patch release with a published GitHub advisory (and a CVE where appropriate) that credits the reporter unless you prefer to stay anonymous. Please keep the details private until the advisory is published; we will coordinate the date with you.

## Safe harbour

Good-faith research is welcome. If you stay within this policy (test only against your own installation, avoid privacy violations and service disruption, report promptly and give us reasonable time to fix before disclosure), we will not pursue or support legal action against you. This is a statement of intent by the maintainers of an open-source library, not a legal contract; RTLY-Kit runs no hosted service, so there is nothing of ours to attack beyond the code and this repository.

## Scope

In scope:

- Denial of service from crafted input (unbounded CPU or memory use, catastrophic regex backtracking).
- Validators or converters returning a result that could bypass a check the caller reasonably relies on (for example a bad checksum accepted).
- Unsafe behaviour in the Laravel integration (service provider, validation rules, Eloquent cast) or the Carbon macros.
- Supply-chain issues in this repository (CI workflows, release archives and their attestations).

Out of scope:

- Vulnerabilities in PHP, Laravel, Carbon or other dependencies (report them upstream).
- Wrong, stale or incomplete reference data (holidays, bank BINs, Sheba codes, national-code prefixes, calendar tables, prayer-time cities) with no security impact. Use the "Data correction" issue template instead; these are ordinary bugs.
- Issues that need the application to pass attacker-controlled input to an `@internal` API.
- Missing input-length limits at your own trust boundary (see below), and reports from automated scanners without a working proof of concept.

## Known limitations

- **Callers must limit input length.** The library caps some inputs (number strings, digit counts) but not every string it accepts. Apply length limits at your trust boundary.
- **Validators check format and checksum only.** A valid national code, Sheba, bank card or mobile number is well formed. It does not prove that the person, account or card exists or belongs to the user. Never use a validator as authentication or identity proof.
- **Reference data is best-effort.** Lookup tables (bank names, operators, place-of-issue prefixes, prayer-time cities, holidays, the Umm al-Qura table) come from public sources listed in `resources/data/SOURCES.md`. They can be incomplete or stale and must not drive legal or financial decisions.
- **Prayer times and lunar calendars are calculated**, not observed or officially published.
