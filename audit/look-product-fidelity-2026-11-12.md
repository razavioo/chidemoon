# Product-reference fidelity and gallery card repair

The GPT Image path previously sent product names but no product photographs when generating a Look. The Gemini path attached main product photographs. This transport regression allowed plausible substitute objects.

Product-based Looks now select the configured image-edit provider and transmit the product main image plus all gallery images through multipart `/images/edits`, with explicit ordered product/image mapping. The first image defines the variant and gallery images clarify details. References can be explicitly excluded through `_chidemoon_ai_reference_excluded`; product 3157's attachment 3166 is a video-poster graphic and was excluded after inspecting all 18 source images. The selected three products provide 17 actual photographs. Requests exceeding 32 references fail with an actionable error instead of silently dropping images.

A mocked request executed in WordPress verified all 17 images are present in the multipart request, the endpoint is `/images/edits`, the model is Sunburst 2.6, and identity constraints are supplied. Product-reference routing also has a standalone regression check.

Native Look gallery cards now render a linked scene image and a detail link, rather than inserting the full detail-page product layout into a narrow masonry card. Legacy interactive scenes remain unchanged. Publication visibility and hotspot approval remain separate.

Image synthesis can still alter details; byte transport alone is not fidelity evidence. New image output must be visually compared against references before replacing published media.
