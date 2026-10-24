\pset tuples_only on
\pset format unaligned
SELECT coalesce(json_agg(x),'[]'::json) FROM (
 SELECT id,platform,"externalProductId","listingUrl",price,currency,"lastCapturedAt","rawSnapshot"->'price' AS "capturedPrice"
 FROM product_listings WHERE "organizationId"='81742f20-f6d8-4a3f-aa32-4bdc11aee0cc'
 AND ("externalProductId" IN ('563439','524436','dkp-563439','dkp-524436') OR "listingUrl" LIKE '%dkp-563439%' OR "listingUrl" LIKE '%dkp-524436%')
) x;
