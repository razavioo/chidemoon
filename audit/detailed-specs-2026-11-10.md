# Product detail enrichment — 2026-11-10

Reviewed all 780 published/draft products (718 published). Fetched and identity-verified 777 Basalam product pages using the public product-scoped NEXT_DATA object. The two Digikala API requests were blocked by redirects; their previously reviewed, explicit product descriptions supplied lamp specifications, without adding the known-invalid weight. One draft has no merchant URL.

Saved 2,754 evidence-backed detail entries on 761 products, including 706 published products. Every entry retains its merchant URL, exact source excerpt, extraction method and observation timestamp in `_chidemoon_detailed_specs_v1`. 19 products (12 published) have no qualifying additional detail. Existing classifications remain intact.

Excluded 222 suspicious/mislabelled candidates, and deliberately ignored marketplace netWeight fields: sampled rugs had 200g and food processors 100g shipping placeholders. No dimensions are converted or axes inferred from unlabeled numeric pairs. Variant choices are explicitly labelled and restricted to positive-stock variants. Product dimensions and shipping/package dimensions are not merged. Seller-provided statements are evidence of the listing, not an independent physical test.

The comparison table previously discarded detailed WooCommerce attributes/source specifications when reviewed enrichment existed. It now merges the reviewed detail record and visible custom attributes. The same record is exposed in WooCommerce product additional information. No merchant network calls occur during page rendering. Prices, publication status, titles, descriptions and source payloads were checked unchanged. Original records backed up on server at wp-content/uploads/detailed-specs-before-20261110.json.

Verification: PHP syntax checks passed; all 780 products and 2,754 detail entries passed persistence, evidence and comparison/product-display checks with zero errors. Actual browser comparison 769/855 correctly displays 1500/1350W, 15/20bar and 1.8/1L. Product 855 additional information displays its technical details. Viewports 320, 390, 768 and 1440px have no page overflow. Public cache invalidated.

Published products requiring better merchant documentation: 770, 2995, 4498, 5120, 5255, 5440, 7319, 7327, 7343, 7383, 7726, 7732. They keep their existing reviewed classifications; no invented technical values were inserted.

Reproducible artifacts: output/detailed-specs-before.json, output/detailed-specs-fetched.jsonl, output/plan-detailed-specs.cjs, output/detailed-specs-plan.json, output/detailed-specs-rejected.json, output/apply-detailed-specs.php, output/verify-detailed-specs.php.
