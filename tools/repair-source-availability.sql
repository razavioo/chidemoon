\set ON_ERROR_STOP on
BEGIN;
CREATE TEMP TABLE availability_repairs ON COMMIT DROP AS SELECT value AS repair FROM jsonb_array_elements(pg_read_file('/tmp/chidemoon-source-availability-plan.json')::jsonb);
CREATE TEMP TABLE availability_backup ON COMMIT DROP AS SELECT l.* FROM product_listings l JOIN availability_repairs p ON l.id=p.repair->>'listingId' WHERE l."organizationId"='81742f20-f6d8-4a3f-aa32-4bdc11aee0cc' AND l.platform='BASALAM' AND l."externalProductId"=p.repair->>'sourceProductId';
DO $$ BEGIN IF (SELECT count(*) FROM availability_backup) <> 4 THEN RAISE EXCEPTION 'Expected four verified unavailable source offers'; END IF; END $$;
\copy (SELECT to_jsonb(b) FROM availability_backup b) TO '/tmp/chidemoon-availability-listings-before.jsonl'
UPDATE product_listings l SET inventory=0,"rawSnapshot"=jsonb_set(coalesce(l."rawSnapshot",'{}'::jsonb),'{stock}','0'::jsonb,true),metadata=coalesce(l.metadata,'{}'::jsonb)||jsonb_build_object('chidemoonAvailabilityReview20261022',p.repair),"updatedAt"=CURRENT_TIMESTAMP FROM availability_repairs p WHERE l.id=p.repair->>'listingId' AND l.id IN(SELECT id FROM availability_backup);
COMMIT;
