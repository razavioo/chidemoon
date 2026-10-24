BEGIN READ ONLY;
SELECT set_config('app.current_org_id','81742f20-f6d8-4a3f-aa32-4bdc11aee0cc',true);
SELECT intent, count(*) FROM publication_catalog_items WHERE "organizationId"='81742f20-f6d8-4a3f-aa32-4bdc11aee0cc' GROUP BY intent;
SELECT p.status,l.status,l.platform,count(*) FROM products p JOIN product_listings l ON l."productId"=p.id WHERE p."organizationId"='81742f20-f6d8-4a3f-aa32-4bdc11aee0cc' AND NOT EXISTS(SELECT 1 FROM publication_catalog_items i WHERE i."productId"=p.id) GROUP BY p.status,l.status,l.platform;
SELECT id,name,"publicOrigin" FROM publication_catalog_connectors WHERE "organizationId"='81742f20-f6d8-4a3f-aa32-4bdc11aee0cc';
SELECT count(DISTINCT p.id) AS eligible_external_products_without_target
FROM products p JOIN product_listings l ON l."productId"=p.id AND l."organizationId"=p."organizationId"
WHERE p."organizationId"='81742f20-f6d8-4a3f-aa32-4bdc11aee0cc' AND p.status IN ('active','inactive') AND l.status IN ('ACTIVE','OUT_OF_SYNC','INACTIVE')
AND lower(l."storeIdentity") NOT IN ('chidemoon.com','www.chidemoon.com') AND l."listingUrl" !~* '^https://(www\.)?chidemoon\.com([/:?]|$)'
AND NOT EXISTS(SELECT 1 FROM publication_catalog_items i WHERE i."productId"=p.id AND i."connectorId"='72aeea4c-0c33-4eb6-af69-89a3c661269f');
SELECT count(*) AS destination_mirrors_selected FROM publication_catalog_items i JOIN product_listings l ON l.id=i."primaryListingId" WHERE i."connectorId"='72aeea4c-0c33-4eb6-af69-89a3c661269f' AND l."storeIdentity"='chidemoon.com';
SELECT withdrawn,count(*) FROM catalog_source_records WHERE "connectorId"='72aeea4c-0c33-4eb6-af69-89a3c661269f' GROUP BY withdrawn;
COMMIT;

