const fs=require('fs');const r=JSON.parse(fs.readFileSync('audit/drafts-details-2026-10-22/publish-result.json','utf8'));
const targets=[...r.products,{id:4447,url:'https://chidemoon.com/?p=4447'},{id:455,url:'https://chidemoon.com/?p=455'},{id:451,url:'https://chidemoon.com/?p=451'}];
(async()=>{
 const results=[];let next=0;
 async function worker(){while(next<targets.length){const p=targets[next++];try{const response=await fetch(p.url,{signal:AbortSignal.timeout(45000)});const body=await response.text();results.push({id:p.id,status:response.status,sourceDescription:body.includes('ch-source-description'),tagRow:/<dt[^>]*>\s*برچسب/.test(body),tagLink:/<a\b[^>]*href=["'][^"']*\/product-tag\//.test(body)});}catch(e){results.push({id:p.id,error:e.message});}}}
 await Promise.all([worker(),worker(),worker(),worker()]);
 fs.writeFileSync('audit/drafts-details-2026-10-22/public-verification.json',JSON.stringify(results,null,2));
 const failed=results.filter(x=>x.status!==200||x.tagRow||x.tagLink||x.id!==6423&&!x.sourceDescription);
 console.log(JSON.stringify({checked:results.length,failed},null,2));if(failed.length)process.exitCode=1;
})().catch(e=>{console.error(e);process.exit(1)});
