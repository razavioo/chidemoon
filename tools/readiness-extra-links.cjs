const fs=require('fs');const dir='audit/readiness-2026-10-28';const rows=JSON.parse(fs.readFileSync(`${dir}/crawl.json`));
const norm=s=>{const u=new URL(s);return u.origin+decodeURI(u.pathname).replace(/\/$/,'');};
const known=new Set(rows.map(r=>norm(r.url)));const urls=[...new Set(rows.flatMap(r=>r.internal||[]))].filter(u=>!known.has(u)&&!u.includes('/go/'));let cursor=0;const out=[];
async function worker(){while(cursor<urls.length){const url=urls[cursor++];try{const r=await fetch(url,{signal:AbortSignal.timeout(45000)});out.push({url,status:r.status,finalUrl:r.url});await r.body?.cancel();}catch(e){out.push({url,error:e.message});}}}
(async()=>{await Promise.all(Array.from({length:5},worker));fs.writeFileSync(`${dir}/extra-links.json`,JSON.stringify(out,null,2));console.log(JSON.stringify({checked:out.length,failed:out.filter(r=>r.error||r.status>=400)}));})();
