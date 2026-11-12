# Affiliate clicks and configurable AI — 2026-11-12

Scope: descriptive search deferred by the owner. Affiliate tracking is click reporting only.

Local changes add a capability-gated click report for rolling 7/30/90-day windows, UTC timestamps, total events and the top 100 product/merchant combinations. Existing redirect tracking and DNT behavior remain in place. Database errors are distinguished from empty reports. No purchase or commission claim is made.

AI settings add encrypted personal connection profiles for text, images, vision and moderation. Profiles take precedence only when explicitly enabled. Text and image credentials are routed independently. Model lookup tests use saved settings and never generate assets. Existing host configuration remains the fallback.

## Verification

- Standalone affiliate persistence/reporting tests pass, including DNT, host-only referrers, IP hashing, report bounds, permission denial and database error handling.
- Personal connection tests pass: encryption, blank-key preservation, invalid URL rejection, atomic validation, permission denial, corrupt ciphertext rejection, vision fallback and independent text/image routing.
- AI state machine and architecture contracts pass.
- Nine mocked Gemini transport/failure checks pass in the actual server WordPress runtime.
- Production DNS and SSH agree on the shared server. WordPress and all three Chidemoon plugins are active. The configured text/vision model is gemini-3-flash; image is gemini-3.1-flash-image-preview.
- Actual generation probe stops at input moderation. The configured gateway returns HTTP 401. Configuration presence alone is therefore not operational readiness. No generated article or image was published.
- Earlier full Node tests exposed existing comparison-shortcode contract drift and a macOS /var versus /private/var path assertion. These were not changed in this task.

New code has not been deployed. Graph index is dated 2026-10-16; changed-file conclusions were verified against local source. Admin browser rendering and live generation with new personal credentials remain unverified. No environment secrets were printed or copied into repository files.

## Authorized production rollout

The owner subsequently authorized complete server rollout and supplied a new provider credential. Live probes on the existing WordPress runtime passed Persian text generation with gpt-5.5, structured output validation, input/output content review, image generation with gpt-image-2.6-flare and multipart image editing with gpt-image-2.6-sunburst. Attachments 8926 and 8927 preserve the reviewed generation/edit evidence. Visual inspection confirmed the lamp changed from white to navy blue while the surrounding desk and room were retained. The gateway returned a 1536x1024 image despite the configured 1024x1024 request; exact dimension compliance is not claimed.

The gateway lacks dedicated moderation routing. Explicitly configured non-moderation models use a structured multimodal classifier with required boolean categories and fail-closed validation. This is model-assisted review, not OpenAI's dedicated moderation endpoint.

Three pre-existing production fixes (merchant inventory filters/display and approved dimension precedence) were imported into source before release to avoid regression. Existing tests were updated for current comparison DOM fixtures and persistent picker behavior; all 48 Node tests pass.

## Deployment result

Deployed sealed release `chidemoon-release-v0.1.0-135-g50c968b-50c968b43f65` using the repository deployment lane and matching verified offline Docker archive. WordPress and database health checks passed. Five encrypted profiles were saved and re-read from production with matching credentials; no key was printed.

Live click report returned 3,170 events over seven days and bounded grouped rows. A real `/go/` request for product 8860 returned HTTP 302 and persisted a click; a DNT request did not persist. The 30-day rendered report showed 9,693 recorded events at verification time (counts continue changing). Server-rendered settings and report HTML were exported privately and inspected in a local browser with WordPress styles; full authenticated browser form submission was not exercised.

GPT-5.5 answered `red` for a 64x64 opaque red image. The strict 1x1 image probe failed on GPT-5.5 and other tested models; that strict capability test is not claimed passed. Generated/edit output review and actual uploaded-image editing worked. Probe media remains marked review_required; no public article or Look was published.

The obsolete first candidate source/image bundles created during this session were removed after the final candidate superseded them. No live volume, upload, active release or retained rollback image was deleted. Host disk remained tight (approximately 2 GiB free at the last deployment checkpoint).
