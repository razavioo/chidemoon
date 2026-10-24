const fs = require('fs');
function read(path) { const b=fs.readFileSync(path); return JSON.parse(b.toString(b[0]===255?'utf16le':'utf8').replace(/^\uFEFF/,'')); }
const before=read('.deploy-artifacts/price-before.json');
const fetched=read('.deploy-artifacts/price-fetched.json');
const listings=read('.deploy-artifacts/price-source-listings.json');
const observedAt=new Date().toISOString();
const plan=fetched.map(f=>{
 const p=before.products.find(p=>p.id===f.id), o=f.product?.offers;
 if(!p||p.identity?.provider!=='basalam'||String(f.product?.productID)!==p.identity.product||new URL(f.finalUrl).pathname!==new URL(p.sourceUrl).pathname||!Number.isSafeInteger(o?.price)||o.price<=0||o.priceCurrency!=='IRR'||!['InStock','OutOfStock'].includes(o.availability))throw Error('Unverified source '+f.id);
 const l=listings.find(l=>l.externalProductId===p.identity.product&&l.listingUrl===p.sourceUrl);
 const a=f.product.aggregateRating;
 const rating=a&&Number.isFinite(+a.ratingValue)&&Number.isFinite(+a.bestRating)&&Number.isInteger(+a.reviewCount)?{value:+a.ratingValue,scale:+a.bestRating,count:+a.reviewCount,provider:'basalam'}:null;
 return {id:p.id,identity:p.identity,sourceUrl:p.sourceUrl,listingId:l?.id??null,previousPrice:p.price,current:String(o.price),currency:'IRR',availability:o.availability==='InStock'?'in_stock':'out_of_stock',rating,observedAt};
});
if(plan.length!==532||plan.filter(p=>p.listingId).length!==520||new Set(plan.map(p=>p.id)).size!==532)throw Error('Unexpected coverage');
fs.mkdirSync('audit/prices-2026-10-22',{recursive:true});
fs.writeFileSync('audit/prices-2026-10-22/plan.json',JSON.stringify(plan,null,2));
fs.writeFileSync('audit/prices-2026-10-22/before.json',JSON.stringify(before,null,2));
console.log(JSON.stringify({verified:plan.length,sourceListings:plan.filter(p=>p.listingId).length,filled:plan.filter(p=>!p.previousPrice||+p.previousPrice<=0).length,amountChanged:plan.filter(p=>+p.previousPrice!==+p.current).length,example:plan.find(p=>p.id===6653),unverified:before.products.filter(p=>!plan.some(r=>r.id===p.id)).map(p=>({id:p.id,title:p.title,sourceUrl:p.sourceUrl,price:p.price}))},null,2));
