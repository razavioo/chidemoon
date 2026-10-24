const fs = require('fs');
const raw=fs.readFileSync('.deploy-artifacts/new-category-audit.json');
const input=JSON.parse(raw.toString(raw[0]===0xff&&raw[1]===0xfe?'utf16le':'utf8').replace(/^\uFEFF/,''));
const definitions=[
 ['blankets','پتو','bedding'],['sofa-throws','شال مبل','carpets'],
 ['furniture','مبلمان',null],['sofas','مبل راحتی','furniture'],
 ['dishwashers','ماشین ظرفشویی','home-appliance'],
 ['kitchen-faucets','شیرآلات آشپزخانه','kitchen-appliances'],
 ['appliance-covers','کاور لوازم آشپزخانه','kitchen-organizers'],
 ['haft-sin','ظروف هفت‌سین','serving'],
 ['import-needs-review','نیازمند بررسی اطلاعات کالا',null]
].map(([slug,name,parent])=>({slug,name,parent}));
const items=input.products.map(p=>{
 let slugs=['serving']; const t=p.title;
 if([4933,5005,5810,6551].includes(p.id)) slugs=['import-needs-review'];
 else if(p.id===4935) slugs=['sofas'];
 else if(p.id===6026) slugs=['dishwashers'];
 else if(p.id===6239) slugs=['kitchen-faucets'];
 else if(p.id===5255) slugs=['appliance-covers'];
 else if(/شال مبل/.test(t)) slugs=['sofa-throws','blankets'];
 else if(/پتو/.test(t) && !/روتختی/.test(t)) slugs=['blankets'];
 else if(/روتختی|رو تختی/.test(t)) slugs=['bedspread',...(/پتو/.test(t)?['blankets']:[])];
 else if(/چای ساز|چایساز|چایی ساز|سماور/.test(t)) slugs=['tea-maker',...(p.id===6256?['coffee-maker']:[])];
 else if(/هفت سین/.test(t)) slugs=['haft-sin'];
 else if(/سینی/.test(t)) slugs=['tray'];
 else if(/بانکه|جینجر جار/.test(t)) slugs=['canisters'];
 else if(/قندان|فنجان|قهوه خوری|لته خوری/.test(t)) slugs=['tea-sets'];
 else if(/لیوان|ماگ/.test(t)) slugs=['mug-jug'];
 else if(/جا قاشقی/.test(t)) slugs=['kitchen-organizers'];
 else if(/قاشق/.test(t) && [5010,5390].includes(p.id)) slugs=['spoon-fork-knife'];
 else if(/پیاله|کاسه/.test(t) && p.id!==5783) slugs=['bowl'];
 else if(/مربا خوری/.test(t)) slugs=['condiment-and-appetizer-dishes'];
 else if(/سرویس پذیرایی|سرویس پذیرای|سرویس غذاخوری/.test(t)) slugs=[/چینی/.test(t)?'porcelain-tableware':'dinnerware-sets'];
 else if(p.id===4883) slugs=['crystalware'];
 const c=JSON.parse(p.content);
 return {id:p.id,title:t,identity:p.identity,token:null,categories:slugs,evidence:c.sourceUrl,review:slugs.includes('import-needs-review')};
});
fs.mkdirSync('audit/categories-2026-10-22',{recursive:true});
fs.writeFileSync('audit/categories-2026-10-22/plan.json',JSON.stringify({definitions,items},null,2));
for(const i of items) console.log(`${i.id}\t${i.categories.join(',')}\t${i.title}`);
