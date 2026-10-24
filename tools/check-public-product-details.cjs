const fs=require('fs');
const read=p=>JSON.parse(fs.readFileSync(p,'utf8'));
(async()=>{
 const sample=read('audit/publish-2026-10-22/result.json').published[0];
 const res=await fetch(sample.url);const body=await res.text();
 fs.writeFileSync('.deploy-artifacts/public-product-details.html',body);
 console.log(JSON.stringify({id:sample.id,status:res.status,sourceDescription:body.includes('ch-source-description'),taggedAs:body.includes('tagged_as'),publicTagLink:/<a\b[^>]*href=["'][^"']*\/product-tag\//.test(body)},null,2));
})().catch(e=>{console.error(e);process.exit(1)});
