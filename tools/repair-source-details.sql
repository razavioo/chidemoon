\set ON_ERROR_STOP on
BEGIN;
CREATE TEMP TABLE details_repairs ON COMMIT DROP AS SELECT value AS repair FROM jsonb_array_elements(pg_read_file('/tmp/chidemoon-source-repair-plan.json')::jsonb) WHERE value->>'listingId' IS NOT NULL;
DO $$ BEGIN IF (SELECT count(*) FROM details_repairs) <> 17 THEN RAISE EXCEPTION 'Expected 17 source listing repairs'; END IF; END $$;
CREATE TEMP TABLE details_backup ON COMMIT DROP AS SELECT l.* FROM product_listings l JOIN details_repairs p ON l.id=p.repair->>'listingId'
WHERE l."organizationId"='81742f20-f6d8-4a3f-aa32-4bdc11aee0cc' AND l.platform='BASALAM' AND l."externalProductId"=p.repair#>>'{identity,product}' AND l."listingUrl"=p.repair->>'sourceUrl';
DO $$ BEGIN IF (SELECT count(*) FROM details_backup) <> 17 THEN RAISE EXCEPTION 'Identity mismatch'; END IF; END $$;
\copy (SELECT to_jsonb(b) FROM details_backup b) TO '/tmp/chidemoon-details-listings-before.jsonl'
UPDATE product_listings l SET title=p.repair->>'title',description=nullif(p.repair->>'description',''),"imageUrl"=p.repair->'images'->>0,
"rawSnapshot"=coalesce(l."rawSnapshot",'{}'::jsonb)||jsonb_build_object('title',p.repair->>'title','description',p.repair->>'description','images',p.repair->'images','imageUrl',p.repair->'images'->>0),
metadata=coalesce(l.metadata,'{}'::jsonb)||jsonb_build_object('chidemoonDetailsRepair20261022',jsonb_build_object('checkedAt',p.repair->>'checkedAt','sourceUrl',p.repair->>'sourceUrl')),
"updatedAt"=CURRENT_TIMESTAMP FROM details_repairs p WHERE l.id=p.repair->>'listingId' AND l.id IN(SELECT id FROM details_backup)
RETURNING l.id,l.title;
COMMIT;
