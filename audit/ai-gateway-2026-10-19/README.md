# Nano Banana gateway integration — 2026-10-19

Production uses the existing ai.irkodalg.shop gateway. Credentials are in the server environment only; no key is stored in plugin options or this report.

- Base: `https://ai.irkodalg.shop/antigravity/v1`.
- Native image model: `gemini-3.1-flash-image-preview`.
- Text, vision and structured content review: `gemini-3-flash`.
- Native transport maps `/v1` to `/v1beta/models/{model}:generateContent`. Standard image routes on this gateway return 404.
- Product photos are included as inline image parts, individually labelled; optional style references follow with separate instructions. Missing product photos stop generation with an actionable error.
- Generated images still pass the existing local file validation and review workflow. Gemini structured multimodal review replaces the unavailable OpenAI moderation endpoint for this configuration; invalid, incomplete or unavailable decisions fail closed. This is a prompted model classifier, not OpenAI's dedicated moderation service.
- Requests do not follow redirects or send credentials to the browser. Native responses are bounded to 20 MiB.
- Existing OpenAI image generation remains supported for OpenAI models.

## Verification

Actual browser submission through Look Studio, product 455 (HG 799 desk lamp), minimal home office: job **1**, attachment **3982**. The standard Action Scheduler processed it. Input and output review passed, generated image persisted as JPEG, vision returned one hotspot, and state became `review_required`. The preview loaded successfully in the browser Review Queue. No public Look was created or published.

Nine mocked transport/failure checks passed in `tests/ai-gemini-runtime.php`: native route, image decoding and usage, generation options, text-only image failure, rate-limit failure, incomplete moderation rejection, positive category rejection, remote input rejection, and existing OpenAI route regression. These checks make no billable API calls and do not write database records.

PHP lint passed for the four deployed PHP files. WordPress was recreated with the new environment; the existing scheduler reads the same Compose environment.

Backup: `/opt/chidemoon/backups/ai-gateway-20261019/` contains the prior environment and affected existing plugin files. Restore those files and recreate only the WordPress service to roll back. Preserve environment file permissions (0600).

The estimated costs in the plugin are budget estimates, not verified gateway billing rates. Generation is asynchronous; the installed host scheduler runs every five minutes.
