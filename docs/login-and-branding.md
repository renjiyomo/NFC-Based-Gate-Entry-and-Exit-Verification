# Login and university wording

Completed locally on November 9, 2026, following the user's login review and request to use **Bicol University Polangui** without “campus” throughout the interface.

The login now uses one framed layout with a quiet university identity panel and a focused sign-in area. The numbered feature list, extra overline, tagline and repeated footer were removed. The existing seal, NFCheck logo, blue actions, portfolio light/dark palette and circular theme transition remain. The left panel uses restrained typography and a solid, lightly tinted surface. On phones, it becomes a compact university-name band above the form.

Username/password behavior and administrator/gate-account sign-in are unchanged. Inputs have larger spacing, the password eye has a 44 px target, and mobile inputs use 16 px text. “Sign in” is the page's primary heading. Required-field behavior, processing/error states, preview restrictions and synthetic-workspace labels are retained. No email activation or registration step was added.

University branding is consistent in the login, account forms, sidebar, mobile drawer and footer. Current headings, search labels, feedback, server messages and export headings use university/gate wording instead of “campus.” The validation display name is “university number”; the underlying `campus_number`, catalogue identifiers and legacy recording-method values remain unchanged. Saved historical notes and summaries were not rewritten. The provider prompt's generic description was shortened without adding an institution name or sending a request.

The sign-in styles are isolated in `resources/css/login.css`. Forty-six unused selectors from the previous login presentation were removed from the shared stylesheet after checking they had no remaining React references.

## Verification

- **211 PHP tests / 2,337 assertions** and **36 frontend tests** passed. Production build/TypeScript, PHP style, formatting and the existing 34 selected palette contrast checks passed. Dependencies and database schema were unchanged; concurrency checks from the previous revision were not rerun for this presentation/copy change.
- Browser checks covered desktop dark/light, configured 320 × 740 and 393 × 852 phone views, and a 768 × 1024 tablet view. Checked pages had no horizontal overflow. Checked phone inputs used 16 px type and measured at least 48 px high.
- Required empty submission focused Username without sending a login. Password reveal/conceal was checked using disposable text, then cleared. Existing hardware administrator and gate-account sign-in/sign-out passed. No private password was revealed or changed.
- Recovery-page branding was checked at phone width without submitting a request. Read-only checks covered the overview, students, credentials, gates, reports, analytics, NFC setup and gate scanner; their current visible text used the full university name without “campus.” No browser warning/error log entries were observed.
- The viewport was reset, the main login theme restored to dark, and test sessions signed out. Existing business counts and selected employee HW-TAG-08 / student HW-TAG-09 were verified unchanged. No coin write/revocation, saved review/correction, new passage, live AI call, service restart, push or deployment occurred.

These checks concern the login and wording; they do not add physical NFC, speaker or iOS Safari acceptance. Earlier workflow and deployment limits remain in force.

[Sanitized evidence](evidence/login-and-branding.json) records the results. The ignored local previews are `.tools/artifacts/login-new-dark.png`, `login-new-light.png` and `login-new-mobile-light.png`.
