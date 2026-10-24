\set ON_ERROR_STOP on
BEGIN;
CREATE TEMP TABLE chidemoon_image_repairs ON COMMIT DROP AS SELECT value AS repair FROM jsonb_array_elements(pg_read_file('/tmp/chidemoon-image-repair-plan.json')::jsonb);
DO $$ BEGIN IF (SELECT count(*) FROM chidemoon_image_repairs) <> 4 THEN RAISE EXCEPTION 'Expected four reviewed image repairs'; END IF; END $$;
CREATE TEMP TABLE chidemoon_image_listing_backup ON COMMIT DROP AS
SELECT l.* FROM product_listings l JOIN catalog_source_records r ON r."listingId"=l.id JOIN chidemoon_image_repairs p ON p.repair->>'sourceKey'=r."sourceKey"
WHERE r."connectorId"='72aeea4c-0c33-4eb6-af69-89a3c661269f' AND r.payload #>> '{identity,provider}'='basalam' AND (r.payload #> '{content,images}') @> (p.repair->'images');
DO $$ BEGIN IF (SELECT count(*) FROM chidemoon_image_listing_backup) <> 4 THEN RAISE EXCEPTION 'Source identity or image revision changed'; END IF; END $$;
\copy (SELECT to_jsonb(b) FROM chidemoon_image_listing_backup b) TO '/tmp/chidemoon-image-listings-before.jsonl'
UPDATE product_listings l SET
 "imageUrl"=p.repair->'images'->>0,
 "rawSnapshot"=jsonb_set(coalesce(l."rawSnapshot",'{}'::jsonb),'{images}',p.repair->'images',true),
 metadata=jsonb_set(coalesce(l.metadata,'{}'::jsonb),'{chidemoonImageRepair20261021}',coalesce(l.metadata->'chidemoonImageRepair20261021',jsonb_build_object('checkedAt',p.repair->'checkedAt','excluded',p.repair->'excluded','originalImageUrl',l."imageUrl",'originalImages',l."rawSnapshot"->'images')),true),
 "updatedAt"=CURRENT_TIMESTAMP
FROM catalog_source_records r,chidemoon_image_repairs p
WHERE r."listingId"=l.id AND p.repair->>'sourceKey'=r."sourceKey" AND r."connectorId"='72aeea4c-0c33-4eb6-af69-89a3c661269f'
AND (l."imageUrl" IS DISTINCT FROM p.repair->'images'->>0 OR l."rawSnapshot"->'images' IS DISTINCT FROM p.repair->'images')
RETURNING l.id,l.title;
COMMIT;
