\pset tuples_only on
\pset format unaligned
SELECT coalesce(json_agg(x),'[]'::json) FROM (
SELECT l.id,l."externalProductId",l."listingUrl",l.title,l.description,l."imageUrl",l."rawSnapshot",l.specs,l.metadata,l.price,l.currency,l."lastCapturedAt",l."updatedAt"
FROM product_listings l
WHERE l."organizationId"='81742f20-f6d8-4a3f-aa32-4bdc11aee0cc' AND l.platform='BASALAM'
) x;
