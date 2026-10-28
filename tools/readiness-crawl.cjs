const fs=require('fs');
const dir='audit/readiness-2026-10-28';
const inventory=JSON.parse(fs.readFileSync(`${dir}/inventory.json`,'utf8').replace(/^\uFEFF/,''));
const targets=[...inventory.pages,...inventory.categories,...inventory.products,...inventory.looks];
for(let page=2;page<=34;page++)targets.push({type:'shop-pagination',url:`https://chidemoon.com/shop/page/${page}/`});
const norm=url=>{const u=new URL(url);return u.origin+decodeURI(u.pathname).replace(/\/$/,'');};
const results=[];let cursor=0;
async function worker(){while(cursor<targets.length){const target=targets[cursor++];try{
 const response=await fetch(target.url,{signal:AbortSignal.timeout(45000)});const html=await response.text();
 const main=html.match(/<main\b[^>]*>([\s\S]*?)<\/main>/i)?.[1]||html;
 const links=[...html.matchAll(/<a\b[^>]*\bhref=["']([^"']*)["'][^>]*>/gi)].map(m=>m[1].replace(/&amp;/g,'&'));
 const internal=[...new Set(links.map(h=>{try{const u=new URL(h,response.url);return u.origin==='https://chidemoon.com'&&!u.search&&!/\.(webp|png|jpg|jpeg|svg|pdf)$/i.test(u.pathname)?norm(u.href):null;}catch{return null;}}).filter(Boolean))];
 results.push({...target,status:response.status,finalUrl:response.url,title:html.match(/<title>(.*?)<\/title>/s)?.[1],h1:(main.match(/<h1\b/g)||[]).length,breadcrumbs:(main.match(/class="[^"]*\bch-breadcrumb\b[^"-]/g)||[]).length,wooBreadcrumbs:(main.match(/class="[^"]*woocommerce-breadcrumb/g)||[]).length,internal,placeholderLinks:links.filter(h=>h==='#'||/example\.(com|org)|localhost|\.test\//i.test(h)),mainProductLinks:(main.match(/href="https:\/\/chidemoon.com\/product\//g)||[]).length});
 }catch(e){results.push({...target,error:e.message});}
 if(results.length%100===0)console.log(`Checked ${results.length}/${targets.length}`);
}}
(async()=>{await Promise.all(Array.from({length:5},worker));
 const known=new Set(results.map(r=>norm(r.url)));for(const row of results){const key=norm(row.url);row.incoming=results.filter(r=>r!==row&&r.internal?.includes(key)).map(r=>r.url);row.outgoingKnown=row.internal?.filter(u=>known.has(u));}
 fs.writeFileSync(`${dir}/crawl.json`,JSON.stringify(results,null,2));
 const summary={checked:results.length,failed:results.filter(r=>r.error||r.status>=400).map(r=>({url:r.url,status:r.status,error:r.error})),redirects:results.filter(r=>r.finalUrl&&norm(r.url)!==norm(r.finalUrl)).map(r=>({url:r.url,to:r.finalUrl})),noIncoming:results.filter(r=>!r.incoming.length).map(r=>({url:r.url,type:r.type})),placeholders:results.filter(r=>r.placeholderLinks?.length).map(r=>({url:r.url,links:r.placeholderLinks}))};
 fs.writeFileSync(`${dir}/crawl-summary.json`,JSON.stringify(summary,null,2));console.log(JSON.stringify(summary));})();
