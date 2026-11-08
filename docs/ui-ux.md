# UI/UX research and direction

Status: **observed reference plus proposed design, not implemented UI**. Inspected 7 November 2026: [previous NFCheck website](https://bup-enrollment.vercel.app/).

## What was actually inspected

Rendered role-selection and administrator login screens at a 1280 × 720 browser viewport, plus their computed styles. The site initially displayed a session-check loader and subsequently loaded the public screens. No credentials were entered. Authenticated navigation, tables, reports, admin dashboards and mobile behavior could not be inspected; their styles and interactions are not claimed as extracted.

Observed identity: compact logo/wordmark with “Tap. Check. Verify.”, a full-width dark header with a thin warm orange top border, an institutional seal, Inter-first typography, outlined controls, muted blue text, and blue focus/primary states. The role screen lists Staff, Faculty and Administrator because it serves the previous enrollment scope. Those roles are not carried into the new access-control model.

The live dark theme is **navy/blue with a warm orange institutional accent**. The brief's charcoal/lavender/teal description may reflect an earlier presentation. It is not the exact current website palette. Preserve the inspected identity as the default proposal; lavender may remain within the supplied logo. Do not describe proposed colors as extracted values.

## Exact values extracted from current dark-theme CSS

| Token | Value | Observed use |
| --- | --- | --- |
| `--bg` | `#0b1420` | Page background |
| `--surface` | `#142233` | Header/cards/controls |
| `--soft` | `#1c3048` | Secondary surfaces |
| `--text` | `#e9f0f8` | Primary text |
| `--muted` | `#b2c2d5` | Secondary text |
| `--border` | `#7890aa` | Form/control outlines |
| `--border-soft` | `#31465e` | Quiet dividers/cards |
| `--primary` / `--link` | `#a8c7fa` | Primary action and links |
| `--primary-hover` | `#c4d9fc` | Hover token |
| `--on-primary` | `#102a56` | Filled action text |
| `--institution-accent` | `#ffb77b` | Thin institutional accent |
| `--success` / background | `#81c995` / `#163824` | Success tokens |
| `--warning` / background | `#fdd663` / `#40351a` | Warning tokens |
| `--error` / background | `#ffb4ab` / `#482322` | Error tokens |

Computed font stack: `Inter, "Segoe UI", Roboto, system-ui, sans-serif`; font availability was not independently verified. Body size is 16px; small size token 14px, extra-small 12px. Inspected login fields and submit control are 48px high with 8px corners; back action 44px high. Role cards use 18px corners. These are measurements of those screens, not a complete reference design-system specification.

Both existing workspace images were visually inspected and preserved: the NFCheck phone/NFC illustration and Bicol University Polangui seal. Use appropriately sized copies in the future build without stretching; institutional permission for public deployment remains an institutional matter.

## Proposed system tokens

Start from the extracted dark palette. Centralize semantic tokens in `resources/css` in Phase 1, including normal/hover/active/disabled/focus and error states. Use a 4px spacing unit with 8/12/16/24/32px steps, 6px small and 8px standard controls, 12px panels, restrained borders and minimal shadows. These spacing/radius choices are **proposals**, not website extraction.

Typography proposal: locally available Inter when licensed/bundled, with the observed system fallbacks; 16px body, 14px table text, 20–24px page titles, 28px exceptional dashboard title. Use tabular numerals for counts and timestamps. Avoid oversized dashboard headings. Motion 120–180ms only for state feedback; honor reduced motion.

Proposed layout: 240px desktop sidebar, 64px top bar, content width up to 1440px; mobile single-column content with a drawer. Breakpoint proposals: below 640px phone, 640–1023px compact/tablet, 1024px+ desktop. Validate at 360/390/768/1280/1440px and 200% zoom before accepting Phase 1.

## Information architecture

Admin navigation groups: **Overview** (dashboard), **People** (students, employees), **Access** (credentials, guards, gates/assignments), **Monitoring** (history, incidents/anomalies when approved), **Insights** (reports, analytics, optional summaries), **System** (accounts/settings/audit as implemented). Use progressive disclosure to avoid a crowded sidebar. A section must not imply completed functionality before its phase.

Guard navigation: gate scanner and permitted recent activity; concern reporting remains in the scanner context. Gate and Entry/Exit selection stay visible. Use a shared login with no public signup; the server determines role after authentication. Administrator access does not create a guard assignment.

Phase 1 page shells: login, admin dashboard, students, employees, NFC credentials, security guards, gate management/assignment context, scanner, entry/exit history, reports, analytics and settings. Gates are required by the scope even though absent from the brief's short page-shell list. No invented metrics, synthetic charts presented as operational, or buttons that silently pretend to save. Label unavailable actions and explain the current phase.

## Scanner layout proposal

```text
NFCheck                       Connection status
Assigned gate selector        Signed-in guard
[ Entry ] [ Exit ]            Persistent direction label

Ready / Reading / Verifying / Awaiting review / Recorded / Unavailable
[ Large Start NFC scanning control ]

On recognized authorized reference:
Photo     Name                 Student / Employee
          Campus number        Program / Department
          Authorization result + clear reason
          Review expires in ...
[ Confirm observed passage ]   [ Identity mismatch / Cancel ]
[ Report concern ]

Recent permitted activity — recorded, denied, cancelled distinguished
```

“Credential authorized — confirm identity and passage” is different from “Passage recorded.” An allowed result alone must never show a completed passage. Keep retry feedback tied to the same operation after timeouts. Identity mismatch and denial states must be textual and announced; confirmation clears or expires the identity panel so the next person's details are not confused. Missing photo must trigger the approved alternate review procedure, never an invented identity assurance.

Minimum proposed touch target is 44 × 44px; primary scanner/confirmation actions should be at least 48px high. Optional sound/haptic feedback supplements visible status, with user control and no dependency on it. Do not request NFC permissions until a deliberate scan/write action.

## Reusable component inventory

Build components as they are used: Button, labeled Input/Select, FieldError, Badge, StatusBanner with live region, DataTable, Pagination, Search/FilterBar, ConfirmDialog, Toast, Panel, EmptyState, ErrorState, Loading/Skeleton, AppNavigation and ScannerIdentityCard. Charts are introduced only with actual report queries in Phase 7. Use one consistent icon set and explicit accessible names.

Tables need meaningful headers, loading/error/empty states, keyboard-operable filters and pagination, stable widths for dates/status, and responsive handling. On narrow screens prefer concise identity/activity rows and a details disclosure; avoid hiding critical authorization information in horizontal scrolling.

## Verification plan and limits

Phase 1 must check keyboard navigation, focus order/restoration, modal focus handling, form errors, aria-live announcements, contrast, touch targets, 200% zoom, reduced motion and real mobile widths. Empty states must reflect missing backend modules honestly. A light theme is optional and needs its own contrast review; no light-theme values were extracted.

This phase performed visual research, not responsive or WCAG certification of the old website or future app. Reference graph/table/hover/disabled states remain unverified. The proposed premium direction relies on hierarchy, useful density, clear forms and restrained accents; decorative gradients, glow, fake charts and excessive glass effects are excluded.
