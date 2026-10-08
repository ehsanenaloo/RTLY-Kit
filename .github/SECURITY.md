# Security Policy

## Supported versions

Only the latest minor release line of RTLY-Kit receives security fixes. Before 1.0 that means the newest `0.x` minor; please upgrade before reporting an issue against an older one.

## Reporting a vulnerability

Report privately through GitHub Security Advisories: open the repository's Security tab at <https://github.com/ehsanenaloo/RTLY-Kit/security/policy> and choose "Report a vulnerability".

Do not open a public issue or pull request for a suspected vulnerability. Include the affected version, a minimal reproduction, and the impact you expect.

## What to expect

| Step | Target |
|------|--------|
| Acknowledgement | within 3 business days |
| Fix or mitigation, Critical | within 7 days |
| Fix or mitigation, High | within 30 days |
| Fix or mitigation, Medium | within 90 days |
| Low severity | next regular release |

Fixes ship as a patch release with an advisory crediting the reporter (unless you prefer to stay anonymous).

## Scope

In scope:

- Denial of service from crafted input (unbounded CPU or memory use, catastrophic regex backtracking).
- Validators or converters returning a result that could bypass a check the caller reasonably relies on (for example a bad checksum accepted).
- Unsafe behaviour in the Laravel integration (service provider, validation rules, Eloquent cast) or the Carbon macros.
- Supply-chain issues in this repository (CI workflow, release archives).

Out of scope:

- Vulnerabilities in PHP, Laravel, Carbon or other dependencies (report them upstream).
- Out-of-date or incomplete reference data (bank BINs, Sheba codes, national-code prefixes, calendar tables) with no security impact. These are ordinary bugs; file a public issue.
- Issues that need the application to pass attacker-controlled input to an `@internal` API.

## Known limitations

- **Callers must limit input length.** The library caps some inputs (number strings, digit counts) but not every string it accepts. Apply length limits at your trust boundary.
- **Validators check format and checksum only.** A valid national code, Sheba, bank card or mobile number is well formed. It does not prove that the person, account or card exists or belongs to the user. Never use a validator as authentication or identity proof.
- **Reference data is best-effort.** Lookup tables (bank names, operators, place-of-issue prefixes, prayer-time cities, holidays, the Umm al-Qura table) come from public sources listed in `DATA-SOURCES.md`. They can be incomplete or stale and must not drive legal or financial decisions.
- **Prayer times and lunar calendars are calculated**, not observed or officially published.
