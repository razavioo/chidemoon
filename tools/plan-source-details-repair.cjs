const fs=require('fs');
const read=p=>{const b=fs.readFileSync(p);return JSON.parse(b.toString(b[0]===255?'utf16le':'utf8').replace(/^\uFEFF/,''));};
const audit=read('.deploy-artifacts/drafts-description-audit.json');
const listings=read('.deploy-artifacts/source-details.json');
const fetched=read('.deploy-artifacts/source-fetched.json');
const plans=[];
for(const f of fetched){
 if(!f.product)continue;
 const d=audit.descriptions.find(x=>x.id===f.id),identity=JSON.parse(d.identity);
 if(String(f.product.productID)!==identity.product)throw Error('Source identity mismatch '+f.id);
 const imageEntries=Array.isArray(f.product.image)?f.product.image:[f.product.image];
 let images=[f.ogImage,...imageEntries.map(i=>typeof i==='string'?i:i?.contentUrl)].filter(i=>typeof i==='string'&&i.startsWith('https://statics.basalam.com/')&&!i.includes('default-image'));
 images=[...new Set(images)];
 if(!images.length)throw Error('No real image '+f.id);
 const listing=listings.find(l=>l.externalProductId===identity.product);
 plans.push({id:d.id,identity,listingId:listing?.id??null,sourceUrl:d.url,title:f.product.name,description:f.fullDescription||'',images,checkedAt:new Date().toISOString()});
}
fs.mkdirSync('audit/drafts-details-2026-10-22',{recursive:true});
fs.writeFileSync('audit/drafts-details-2026-10-22/source-repair-plan.json',JSON.stringify(plans,null,2));
console.log(plans.map(p=>({id:p.id,listing:!!p.listingId,images:p.images.length,description:p.description.length,title:p.title})));
