\set ON_ERROR_STOP on
BEGIN;
CREATE TEMP TABLE price_repairs ON COMMIT DROP AS SELECT value AS repair FROM jsonb_array_elements(pg_read_file('/tmp/chidemoon-price-plan.json')::jsonb) WHERE value->>'listingId' IS NOT NULL;
DO $$ BEGIN IF (SELECT count(*) FROM price_repairs) <> 520 THEN RAISE EXCEPTION 'Expected 520 verified source prices'; END IF; END $$;
CREATE TEMP TABLE price_backup ON COMMIT DROP AS SELECT l.* FROM product_listings l JOIN price_repairs p ON l.id=p.repair->>'listingId'
WHERE l."organizationId"='81742f20-f6d8-4a3f-aa32-4bdc11aee0cc' AND l.platform='BASALAM' AND l."externalProductId"=p.repair#>>'{identity,product}' AND l."listingUrl"=p.repair->>'sourceUrl';
DO $$ BEGIN IF (SELECT count(*) FROM price_backup) <> 520 THEN RAISE EXCEPTION 'Price identity mismatch'; END IF; END $$;
\copy (SELECT to_jsonb(b) FROM price_backup b) TO '/tmp/chidemoon-price-listings-before.jsonl'
UPDATE product_listings l SET price=(p.repair->>'current')::numeric,currency=p.repair->>'currency',"lastCapturedAt"=(p.repair->>'observedAt')::timestamp,
"rawSnapshot"=coalesce(l."rawSnapshot",'{}'::jsonb)||jsonb_build_object('price',(p.repair->>'current')::numeric,'currency',p.repair->>'currency','originalPrice',null,'discountPercent',null,'availability',p.repair->>'availability','rating',p.repair->'rating'),
metadata=coalesce(l.metadata,'{}'::jsonb)||jsonb_build_object('chidemoonPriceReview20261022',p.repair),"updatedAt"=CURRENT_TIMESTAMP
FROM price_repairs p WHERE l.id=p.repair->>'listingId' AND l.id IN(SELECT id FROM price_backup);
COMMIT;
