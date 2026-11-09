# Interface redesign — November 9, 2026

The current interface adapts Mark Erick Serrano's portfolio palette and theme behaviour to NFCheck's working screens. Existing accounts, gates, people, NFC lifecycle, automatic recording, anomaly/review rules and AI restrictions retain their behaviour. No migration, service restart, deployment or live AI request was required.

## References and decisions

- The user's [portfolio](https://markerick.dev/) and its local `public/assets/css/style.css` / `public/assets/js/theme.js` were inspected. The live browser confirmed charcoal `#0B0D0E`, paper `#F4F2EC`, amber `#FFB000` / `#8A5200`, Instrument Sans and a 620 ms circular theme change. NFCheck uses those core colours, with stronger field boundaries and separate semantic status colours.
- [Linear's March 2026 interface review](https://linear.app/now/behind-the-latest-design-refresh) informed the quieter navigation, consistent action placement and reduced competing emphasis. The workspace remains an operational interface, with real values and useful data density.
- [Radix Themes](https://github.com/radix-ui/themes), its [table documentation](https://www.radix-ui.com/themes/docs/components/table) and [skeleton documentation](https://www.radix-ui.com/themes/docs/components/skeleton) informed table hierarchy and content-shaped loading states. No dashboard template or new component-library dependency was imported.
- W3C's [non-text contrast guidance](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html) and [target-size guidance](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html) informed control boundaries, focus states and touch sizing.

## What changed

The shell has a quieter sidebar, compact header and one account menu containing the full account name, role, password link and POST sign-out action. The mobile drawer keeps native modal focus handling and explicitly focuses its close button. Escape/outside interaction dismisses the account menu. Existing logos and the campus seal are retained.

Typography, spacing, borders, buttons, form fields, native selection arrows, status badges, tables and charts now use shared semantic colours. Instrument Sans and JetBrains Mono are hosted locally; both font licences are included under `public/fonts`. No remote font origin was added to the security policy. Unused decorative icons and repeated headings/explanations were removed. Longer statistical definitions remain accessible in expandable details. Historical reason codes have readable labels.

People and gate directories share cleaner table/filter styling. Phone filters collapse by default so records appear first; desktop filters remain open. Program/department choices, older-value filtering, active-coin visibility and all current form requirements remain intact. The gate scanner keeps large direction/scan controls, with sound presented as a smaller secondary control. Existing warning/denial/duplicate distinctions remain semantic in both palettes.

Skeletons follow real foreground Inertia GET start/finish events after a 180 ms threshold, and initial gate-activity requests. Fast responses do not deliberately wait. Existing content is hidden/inert during the navigation placeholder, rather than being destroyed to simulate loading. Form submissions and background refreshes keep their own processing states. No fabricated metrics or fake loading timer was introduced.

## Theme behaviour

Dark is the first-visit default, matching the portfolio. The chosen theme is saved under `nfcheck.theme`; a small same-origin head script applies it before the React bundle paints. It survives normal navigation, reload and sign-out. The header/login toggle has an accessible light-theme switch state.

Light expands from the toggle through the native View Transition; dark closes the light snapshot back toward it. Both use the portfolio's 620 ms duration and easing. Unsupported browsers use the portfolio-style circular wave/fade fallback. Reduced motion switches immediately. Storage or transition failures do not prevent a later theme change. Theme changes do not submit forms or restart NFC work.

## Verification

- Full check: **210 PHP tests / 2,313 assertions, 33 frontend tests and 11 real MySQL concurrency scenarios passed**, along with Composer/npm audits, formatting, TypeScript and production build. Final layout/copy adjustments passed a fresh build and the 33 frontend tests again.
- Five new theme tests cover initial preference/defaults, denied storage, reduced motion, unsupported/throwing transitions, cleanup/recovery and circle coverage. The 25 NFC adapter and three scan-feedback tests still pass.
- **34 selected palette pairs** were calculated: the lowest checked small-text contrast is **4.61:1**; the lowest checked control-boundary/focus pair is **3.41:1**. This is a bounded palette check, not an exhaustive accessibility audit of every possible state.
- Real hardware-workspace administrator and gate-account sign-in/sign-out passed. Browser checks covered both themes, both native sweep directions/duration, preference persistence, account-menu dismissal, modal close focus, exact program filtering/empty state, directory clearing, student/employee/gate forms, credentials, dashboard, reports and review layouts.
- Viewports checked: 320 × 740, 393 × 852, 768 × 1024 and the default desktop viewport. The checked page bodies had no horizontal overflow. Wide tables retain deliberate horizontal scrolling. Phone form inputs used 16 px text and at least 44 px height. The viewport was reset, the portfolio preference restored to dark, its temporary tab closed and test sessions signed out.
- No warning/error console entries were observed on the checked local screens. Skeleton wiring follows actual request state; fast local requests often finish before its delay threshold. Interaction checks used the completed theme state before continuing ordinary navigation.

Screenshots in ignored `.tools/artifacts`: `ui-login-dark.png`, `ui-login-light.png`, `ui-login-mobile-light.png`, `ui-students-light.png`, `ui-students-mobile-light.png`, `ui-dashboard-dark.png`, `ui-scanner-mobile-dark.png`. [Sanitized verification data](evidence/interface-redesign.json) records test/contrast and retained source-state evidence.

These are browser/viewport checks, not physical Android/iOS/Safari acceptance. The revised NFC/speaker phone test remains pending because the phone was unavailable. Responsive page viewing and NFC hardware capability are separate: scanning still requires the compatible Android Chrome/Web NFC setup. Existing physical student HW-TAG-09 and employee HW-TAG-08 were not rewritten or revoked.
