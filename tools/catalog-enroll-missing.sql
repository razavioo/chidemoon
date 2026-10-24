\set ON_ERROR_STOP on
BEGIN;
SELECT set_config('app.current_org_id','81742f20-f6d8-4a3f-aa32-4bdc11aee0cc',true);
-- Enroll external source products for delivery as drafts; never approve publication.
INSERT INTO publication_catalog_items (id,"organizationId","connectorId","productId","primaryListingId","publicHandle","updatedAt")
SELECT gen_random_uuid()::text,p."organizationId",'72aeea4c-0c33-4eb6-af69-89a3c661269f',p.id,l.id,'source-'||p.id,CURRENT_TIMESTAMP
FROM products p JOIN LATERAL (SELECT id FROM product_listings WHERE "productId"=p.id AND "organizationId"=p."organizationId" AND status IN ('ACTIVE','OUT_OF_SYNC','INACTIVE') AND lower("storeIdentity") NOT IN ('chidemoon.com','www.chidemoon.com') AND "listingUrl" !~* '^https://(www\.)?chidemoon\.com([/:?]|$)' ORDER BY id LIMIT 1) l ON true
WHERE p."organizationId"='81742f20-f6d8-4a3f-aa32-4bdc11aee0cc' AND p.status IN ('active','inactive')
AND NOT EXISTS(SELECT 1 FROM publication_catalog_items i WHERE i."productId"=p.id AND i."connectorId"='72aeea4c-0c33-4eb6-af69-89a3c661269f')
ON CONFLICT ("organizationId","connectorId","productId") DO NOTHING;
COMMIT;
