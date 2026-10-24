const fs=require('fs');const read=p=>{const b=fs.readFileSync(p);return JSON.parse(b.toString(b[0]===255?'utf16le':'utf8').replace(/^\uFEFF/,''));};
const audit=read('.deploy-artifacts/drafts-description-audit.json');
const fetched=read('.deploy-artifacts/legacy-candidates-fetched.json');
const ids=[746,742,736,733,730,728,726,724,722,717];
const normalize=s=>s.replace(/<[^>]+>/g,' ').replace(/\s+/g,' ').trim();
const items=ids.map(id=>{
 const d=audit.drafts.find(x=>x.id===id),f=fetched.find(x=>x.id.startsWith(id+':'));
 if(!f?.product)throw Error('Missing source '+id);
 const description=normalize(f.fullDescription);
 if(!normalize(d.content).includes(description.slice(0,id===726?145:description.length)))throw Error('Description mismatch '+id);
 const images=[f.ogImage,...f.product.image.map(i=>typeof i==='string'?i:i.contentUrl)].filter(i=>i?.startsWith('https://statics.basalam.com/')&&!i.includes('default-image'));
 if(!images.length)throw Error('Missing source images '+id);
 return {id,expectedTitle:d.title,expectedContentHash:require('crypto').createHash('sha256').update(d.content).digest('hex'),identity:{provider:'basalam',store:'default',product:String(f.product.productID),variant:null},sourceUrl:f.requestedUrl,title:f.product.name.trim(),description:f.fullDescription,images:[...new Set(images)],seller:f.product.offers?.seller?.name||'',price:String(f.product.offers?.price||''),currency:f.product.offers?.priceCurrency,availability:String(f.product.offers?.availability||'').includes('OutOfStock')?'out_of_stock':'in_stock',category:id===717?'tray':id===726?'bread-bins':'canisters'};
});
fs.writeFileSync('audit/drafts-details-2026-10-22/legacy-recovery-plan.json',JSON.stringify(items,null,2));console.log(items.map(i=>({id:i.id,source:i.identity.product,images:i.images.length,description:i.description.length})));
