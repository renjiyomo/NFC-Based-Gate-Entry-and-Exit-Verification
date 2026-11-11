# Activity checks and AI summaries

Administrators review activity flags and create summaries. Gate accounts continue using the scanner; neither feature asks a guard to confirm a passage manually.

## Activity checks

New automatic scan requests run these checks when saved:

| Pattern | Trigger | Result |
| --- | --- | --- |
| Repeated scans | Three allowed requests for the same person and gate in less than 60 seconds | One review flag with three supporting attempts. Suppressed duplicate requests count as attempts, never as extra passages. |
| Revoked coin | First rejected request for a revoked credential | A review flag; no passage. Further matching alerts within 60 seconds are grouped. |
| Unavailable access | An expired, cancelled, pending or replaced credential, or an inactive person | A review flag; no passage. |
| Repeated rejections | Five denied server requests at one gate within 60 seconds | A gate-level flag. Unknown references are not attributed to a person. |
| Missing Exit | An effective automatic Entry has no following effective Exit before its operating-day cutoff | An incomplete-sequence flag. No Exit time is invented. |

For a repeated-scan demonstration, use a registered test coin. Scan once, remove it, wait more than ten seconds, scan again, remove it, wait more than ten seconds and scan a third time. Finish within one minute. The scanner intentionally coalesces repeated radio readings while a coin stays near the phone; those readings do not become separate server requests. Every distinct accepted scan outside the duplicate window toggles Entry/Exit, so conduct this only as a clearly identified test.

Open **Activity checks** and select the dates containing the scans. New scan flags appear without clicking Run checks. Open **Review record** to see the evidence and save a review status with a reason. A flag calls for review; it does not establish misconduct or automatically revoke access.

**Check earlier records → Run checks** processes up to 31 days and 1,000 attempts, including overdue Missing Exit records whose cutoff falls in the selection. Running it again does not duplicate existing flags. A later scan also checks the person's overdue Entry. Detecting Missing Exit while nobody is scanning or viewing the system requires the separate scheduler described in [deployment](deployment.md).

Different-gate timing checks are disabled by default for the single-gate deployment. After-hours checks stay disabled until operating hours are configured.

## AI summaries

Open **AI summaries**, select Daily or Weekly and a date, then **Apply period**. The four cards show the recorded figures for that selection. Click **Create draft summary** and wait for the result. A separate online worker is unnecessary.

Gemini receives counts, dates, fixed measurement descriptions and anonymous gate aliases. It does not receive names, university numbers, photos, coin references, guard names or notes. It selects and orders facts from the supplied set. The server rejects unknown facts, invented numbers and invalid output. The saved draft separates gate activity from activity checks, with the source numbers beneath it. Review the draft and save an approval or rejection with a reason.

The configured provider is Gemini Free Tier, with no paid provider/model fallback. Keep Google billing disabled; application settings cannot control billing changes made in the Google account. Each workspace permits at most three claimed generation attempts per UTC day, including failures, with a 12-second request timeout and 600 output-token limit. Google quota limits can be lower. Failed or uncertain requests are not automatically retried. Check Saved summaries after a connection error before making another request.

The feature is optional on a new groupmate installation. Enable it only with an approved data scope, a private provider key and a Free Tier project. Missing keys or exhausted quotas disable generation without stopping the scanner. Original local and online databases have independent summary histories and quotas.

For the configured online deployment, import the private `.tools/secrets/vercel-ai.env` file into Vercel's Production environment, replacing matching AI_SUMMARIES_* entries. It contains only the six provider settings. Keep the API key Secret and redeploy after saving. Never commit or share that file. Local development reads the approved private `.tools/secrets/ai-provider.json` instead.
