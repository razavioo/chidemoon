const fs=require('fs'),crypto=require('crypto'),path=require('path');
const dir=path.resolve('output/catalog-performance-2026-11-07');
const source=JSON.parse(fs.readFileSync(dir+'/source-before.json'));
const wp=JSON.parse(fs.readFileSync(dir+'/wp-records.json'));
const checked=fs.readFileSync(dir+'/source-results.jsonl','utf8').trim().split(/\r?\n/).map(JSON.parse);
const q=v=>"'"+String(v).replaceAll("'","''")+"'";
function canonical(v){if(Array.isArray(v))return'['+v.map(canonical).join(',')+']';if(v&&typeof v==='object')return'{'+Object.keys(v).sort().map(k=>JSON.stringify(k)+':'+canonical(v[k])).join(',')+'}';return JSON.stringify(v)}
let sql='BEGIN;\n',items=[],legacy=[],remove=[];
for(const c of checked.filter(c=>c.state==='verified')){
 const w=wp.find(w=>w.id===c.id);if(!w||String(w.record.identity.product)!==String(c.productId))throw Error('Identity mismatch');
 const old=source.find(x=>x.record.sourceKey===w.record.sourceKey);
 const bad=!c.isSaleable||!c.isAvailable||!c.isShowable||c.vendorStatus?.value!==2987;
 if(bad)remove.push(c.id);
 const r=structuredClone(old?.record.payload||w.record);
 const current=String(c.price),previous=c.primaryPrice>c.price?String(c.primaryPrice):null;
 r.observations.price={current,previous,currency:'IRR',observedAt:c.at};
 r.observations.availability={status:bad?'out_of_stock':'in_stock',observedAt:c.at};
 if(old){
  r.revision=Math.max(old.record.revision,w.record.revision)+1;
  items.push(r);
  const {revision,...body}=r;const fingerprint=crypto.createHash('sha256').update(canonical(body)).digest('hex');
  sql+=`UPDATE catalog_source_records SET revision=${revision},fingerprint=${q(fingerprint)},payload=${q(JSON.stringify(r))}::jsonb,"updatedAt"=now() WHERE id=${q(old.record.id)} AND "organizationId"=${q(old.record.organizationId)} AND "connectorId"=${q(old.record.connectorId)} AND revision=${old.record.revision};\n`;
  const snapshot={price:c.price,currency:'IRR',stock:bad?0:c.inventory,merchantAudit:{at:c.at,isSaleable:c.isSaleable,isAvailable:c.isAvailable,isShowable:c.isShowable,vendorStatus:c.vendorStatus,evidenceHash:c.evidenceHash}};
  sql+=`UPDATE product_listings SET price=${c.price},currency='IRR',inventory=${bad?0:Math.max(0,Number(c.inventory)||0)},"lastCapturedAt"=${q(c.at)}::timestamptz,"updatedAt"=now(),"rawSnapshot"=COALESCE("rawSnapshot",'{}'::jsonb)||${q(JSON.stringify(snapshot))}::jsonb WHERE id=${q(old.record.listingId)} AND "organizationId"=${q(old.record.organizationId)};\n`;
 }else legacy.push({id:c.id,observations:r.observations});
}
sql+='COMMIT;\n';
fs.writeFileSync(dir+'/reconcile-source.sql',sql);
fs.writeFileSync(dir+'/reconciliation-plan.json',JSON.stringify({items,legacy,remove},null,2));
console.log({versioned:items.length,legacy:legacy.length,remove:remove.length});
