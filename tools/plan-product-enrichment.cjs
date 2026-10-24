const fs=require('fs');
const data=JSON.parse(fs.readFileSync('.deploy-artifacts/product-enrichment-before.json','utf8'));
const fetched=JSON.parse(fs.readFileSync('.deploy-artifacts/price-fetched.json','utf8'));
const visual=fs.existsSync('audit/enrichment-2026-10-22/visual.json')?JSON.parse(fs.readFileSync('audit/enrichment-2026-10-22/visual.json','utf8')):{};
const norm=s=>String(s||'').replace(/[\u{10000}-\u{10FFFF}]/gu,' ').replace(/ي/g,'ی').replace(/ك/g,'ک').replace(/[\u200c\u200f]/g,' ').replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim();
const definitions={style:['فانتزی','وینتیج'],color:['زرد','نارنجی','مسی','صدفی','چندرنگ'],material:['مس','چینی','چدن','آلومینیوم','سیلیکون','پنبه','ویسکوز','پلی‌استر','ابریشم','کریستال','ملامین','آرکوپال','نخ بافتنی','نخ','ساتن'],features:['مناسب پذیرایی','سرو نوشیدنی','تهیه نوشیدنی گرم','آماده‌سازی غذا','پخت‌وپز','نظم‌دهی آشپزخانه','خواب و استراحت','مطالعه و کار','گرمایش فضا','سرمایش فضا','تزئین دکوراسیون','مناسب اتاق کودک','طرح گلدار','طرح هندسی','بافت دستباف','قابل سفارش در اندازه دلخواه','مناسب سفر','نصب گیره‌ای','تنظیم جهت نور','گرم‌نگهدارنده نوشیدنی','دسته چوبی','استند چوبی','قابل شست‌وشو در ماشین ظرفشویی','شست‌وشوی ظروف']};
function existing(p,f){const a=p.approved[f];return a?.state==='value'&&a.value?.some(id=>data.vocabulary[f].find(t=>t.id===id&&t.name!=='None'));}
const colors=[['سبز پاستیلی',/سبز پاست[یي]ل[یي]?/],['سرمه‌ای',/سرمه ?ای/],['فیروزه‌ای',/فیروزه ?ای/],['قهوه‌ای',/قهوه ?ای|گردویی/],['نقره‌ای',/نقره ?ای|سیلور/],['طوسی',/طوسی|توسی|خاکستری/],['مشکی',/مشکی|سیاه/],['سفید',/سفید/],['کرم',/کرم(?![بپ])/],['بژ',/بژ/],['قرمز',/قرمز/],['صورتی',/صورتی/],['آبی',/آبی|ابی(?=\s|$)/],['سبز',/سبز|مغز پسته/],['طلایی',/طلایی|طلائی|لب طلا|طلاکوب/],['مسی',/رنگ مسی/],['دودی',/دودی/],['زرشکی',/زرشکی|شرابی/],['لاکی',/لاکی/],['فیلی',/فیلی(?=\s|$)/],['گلبهی',/گلبهی/],['بنفش',/بنفش|یاسی/],['زیتونی',/زیتونی/],['زرد',/زرد/],['نارنجی',/نارنجی/],['صدفی',/صدفی/],['آجری',/آجری/]];
const materials=[['سرامیک',/سرامیک[ی]?/],['استیل',/استیل|استنلس/],['چوب',/چوب[ی]?|گردو|راش/],['بامبو',/بامبو/],['شیشه',/شیشه ?ای|شیشه|پیرکس|بلور/],['مخمل',/مخمل/],['پشم',/پشم[ی]?/],['اکریلیک',/اکر[وی]لیک|آکر[وی]لیک|اکریلیک/],['پلی‌پروپیلن',/پلی پروپیلن|پلیپروپیلن|\bBCF\b/i],['نمد',/نمد[ی]?/],['مس',/مسی|تمام مس|جنس مس/],['چینی',/ظروف چینی|سرویس چینی|چینی کشکول|جنس.{0,8}چینی/],['پلاستیک',/پلاستیک[ی]?/],['فلز',/فلزی|فلز(?=\s|$)|بدنه فلز|جنس.{0,8}فلز/],['چدن',/چدن[ی]?/],['سنگ',/دیزی سنگی|قابلمه سنگی|جنس.{0,8}سنگ/],['MDF',/\bmdf\b|ام دی اف/i],['برنز',/برنز[ی]?/],['سیلیکون',/سیلیکون[ی]?/],['کتان',/کتان[ی]?/],['پنبه',/پنبه/],['نخ',/صد ?در ?صد نخ|تمام نخ|نخی|میکرو نخ/],['ویسکوز',/ویسکوز/],['پلی‌استر',/پلی ?استر/],['ابریشم',/گل ابریشم|تمام ابریشم|چله ابریشم|جنس.{0,8}ابریشم/],['کریستال',/کریستال[ی]?/],['ملامین',/ملامین[ی]?/],['آرکوپال',/آرکوپال|ارکوپال/],['ساتن',/ساتن/],['حصیر',/حصیر[ی]?/],['چرم',/چرم[ی]?/]];
const plan=[];
for(const p of data.products.filter(p=>p.status==='publish')){
 const fresh=fetched.find(x=>x.id===p.id);const title=norm(p.source?.title||p.title),desc=norm(fresh?.fullDescription||p.description||p.source?.description),body=norm(p.body),text=title+' '+desc,cat=p.categories.join(' ');const fields={},notes={};
 const evidenceBase=p.source?.sourceUrl||p.url;
 function put(f,names,why,confidence=.92){names=[...new Set(names)].filter(Boolean);if(!names.length||existing(p,f)&&f!=='features')return;fields[f]={state:'value',names,evidence:[evidenceBase,norm(why).slice(0,1600)],confidence};notes[f]=names;}
 const isRug=/فرش|قالیچه|گلیم/.test(title),isBedding=/روتختی|رو تختی|پتو|ملحفه|روتشکی/.test(title),isChild=/کودک|نوزاد|کارتونی|الس[ااآ]|تک شاخ|پرنسس/.test(title),isLamp=/چراغ مطالعه/.test(title),isHeater=/بخاری|هیتر/.test(title),isDrink=/چای ?ساز|چایی ?ساز|چاییساز|قهوه ?ساز|قهوهساز|اسپرسو|قهوه ?جوش|شیرجوش|دمنوش ساز|سماور/.test(title),isFood=/غذاساز|غذا ساز|خرد ?کن|آسیاب|سالاد ?ساز|نایسر/.test(title),isOrganizer=/بانکه|ادویه|حبوبات|پاسماوری|جابرنجی|جا ?نونی|جای نان|جا قاشقی|جا کاردی|داخل کشو/.test(title),isCook=/قابلمه|ماه[یي] ?تابه|دیزی|محافظ.*روغن/.test(title),isDish=/ظروف|سینی|قاشق|قندان|پیاله|فنجان|لیوان|خوری|کاسه|بشقاب|سوفله|هفت سین|پارچ/.test(title)||/ظروف|سرویس پذیرایی|سرویس قاشق|فنجان/.test(cat),isSofa=/مبل/.test(title)&&!isRug&&!isDish&&!/شال مبل/.test(title);
 let room=[],feature=[];
 if(isRug){room=/آشپزخانه/.test(title)?['آشپزخانه']:/راهرو|کناره/.test(title)?['ورودی خانه']:isChild?['اتاق کودک']:['پذیرایی و نشیمن'];feature.push(isChild?'مناسب اتاق کودک':'تزئین دکوراسیون');}
 else if(isBedding){room=isChild?['اتاق کودک']: /شال مبل|رومبلی/.test(title)?['پذیرایی و نشیمن','اتاق خواب']:['اتاق خواب'];feature.push('خواب و استراحت');if(isChild)feature.push('مناسب اتاق کودک');}
 else if(isLamp){room=['اتاق کار','گوشه مطالعه'];feature.push('مطالعه و کار');}
 else if(isFood){room=['آشپزخانه'];feature.push('آماده‌سازی غذا');}
 else if(isDrink){room=['آشپزخانه'];feature.push('تهیه نوشیدنی گرم');}
 else if(isHeater){room=['پذیرایی و نشیمن'];feature.push('گرمایش فضا');}
 else if(/کولر/.test(title)){room=['پذیرایی و نشیمن'];feature.push('سرمایش فضا');}
 else if(/ماشین ظرفشویی/.test(title)){room=['آشپزخانه'];feature.push('شست‌وشوی ظروف');}
 else if(isOrganizer||/جینجر جار/.test(title)){room=['آشپزخانه'];feature.push('نظم‌دهی آشپزخانه');}
 else if(isCook){room=['آشپزخانه'];feature.push('پخت‌وپز');}
 else if(/شیر.*ظرفشویی|شیرآلات/.test(title)){room=['آشپزخانه'];feature.push('شست‌وشوی ظروف');}
 else if(isDish||/ماگ/.test(title)){room=['ناهارخوری','آشپزخانه'];feature.push('مناسب پذیرایی');if(/چای|فنجان|لیوان|قهوه|پارچ|ماگ/.test(title))feature.push('سرو نوشیدنی');}
 else if(isSofa||/تابلو|ساعت|شمعدان|جاشمع|سطل|جادستمال|جا دستمال|شال مبل/.test(title)){room=['پذیرایی و نشیمن'];feature.push('تزئین دکوراسیون');}
 put('room',room,'پیشنهاد فضای استفاده بر اساس نوع محصول: '+title,.94);
 // Factual colors: prioritize the product title; description only in an explicit color statement.
 let colorText=title;let segments=[...desc.matchAll(/(?:رنگ(?:بندی)?|زمینه)\s*[:：]?\s*[^.\n!؟]{0,100}/g)].map(m=>m[0]);
 let colorNames=colors.filter(([,rx])=>rx.test(colorText)).map(([n])=>n);
 if(!colorNames.length){colorText=segments.join(' | ');colorNames=colors.filter(([,rx])=>rx.test(colorText)).map(([n])=>n);}
 if(colorNames.includes('سبز پاستیلی')||colorNames.includes('زیتونی'))colorNames=colorNames.filter(n=>n!=='سبز');
 put('color',colorNames,'رنگ ذکرشده در عنوان/توضیح مبدأ: '+colorText,.96);
 // Materials may describe components; do not infer composition from a photograph or brand.
 let materialText=title+' '+[...desc.matchAll(/(?:جنس|ساخته شده از|بدنه|الیاف|بافته شده از|تولید شده از)[^.\n!؟]{0,130}/g)].map(m=>m[0]).join(' | ');
 let materialNames=materials.filter(([,rx])=>rx.test(materialText)).map(([n])=>n);
 if(!materialNames.length&&isBedding&&/بافتنی|الیز پافی/.test(text)){materialNames=['نخ بافتنی'];materialText=title+' '+desc.slice(0,300);}
 put('material',materialNames,'جنس/اجزای ذکرشده در مبدأ: '+materialText,.94);
 let styles=[];let styleWhy='قضاوت دکوراتیو بر پایه عنوان و توصیف محصول: '+title;
 if(/مینیمال|مینیمالیست/.test(text))styles=['مینیمال'];
 else if(/اسکاندیناوی/.test(text))styles=['اسکاندیناوی'];
 else if(/بوهو/.test(title))styles=['بوهو'];
 else if(/نئو ?کلاسیک/.test(title))styles=['نئوکلاسیک'];
 else if(/مدرن/.test(title))styles=['مدرن'];
 else if(/وینت[یی]ج|وینتیج|وینتج/.test(title))styles=['وینتیج'];
 else if(/کلاسیک|سلطنتی|لب طلا|لب طلایی|لیمونژ/.test(title))styles=['کلاسیک'];
 else if(isRug&&/دستبافت|بلوچ|قشقایی|شاه عباسی|افشان|ریز ماهی|سنتی|لچک|ترنج/.test(title))styles=['سنتی'];
 else if(isChild)styles=['فانتزی'];
 else if(/قلم ?کار|قلم ?زن|مسی.*گل|مس.*زنجان/.test(title))styles=['سنتی'];
 else if(/چوبی|چوب گردو|بامبو/.test(title)&&isDish)styles=['روستیک'];
 else if(/شگی|مراکشی/.test(title))styles=['بوهو'];
 // Visual review below supplies style judgments where text is insufficient.
 if(visual[p.id]?.style){styles=visual[p.id].style;styleWhy='بازبینی مستقیم تصویر محصول: '+p.image;}
 put('style',styles,styleWhy,.87);
 if(!existing(p,'color')&&!fields.color&&visual[p.id]?.color)put('color',visual[p.id].color,'رنگ قابل مشاهده در تصویر محصول: '+p.image,.88);
 for(const [name,rx] of [['تاشو',/تاشو/],['طرح گلدار',/گلدار|طرح گل|گل برجسته/],['طرح هندسی',/طرح هندسی|طرح لوزی|طرح راه راه/],['بافت دستباف',/دستبافت|دست بافت/],['قابل سفارش در اندازه دلخواه',/سایز موردنظر|اندازه دلخواه|ابعاد سفارشی|هر سایزی قابل سفارش/],['مناسب سفر',/مسافرتی|مناسب.{0,12}سفر/],['نصب گیره‌ای',/پایه گیره|نصب گیره|گیره ?ای/],['تنظیم جهت نور',/بازوی انعطاف|بازوی مفصلی|گردنی انعطاف/],['گرم‌نگهدارنده نوشیدنی',/گرم نگهدارنده|گرمکن دار/],['دسته چوبی',/دسته چوبی/],['استند چوبی',/استند چوبی/]])if(rx.test(text+(isLamp?' '+body:'')))feature.push(name);
 if(/قابل شست\s*(?:و\s*)?شو/.test(text)&&!/غیر ?قابل شست/.test(text))feature.push('قابل شست‌وشو');
 if(/قابل.{0,15}شست.{0,15}ماشین ظرفشویی/.test(text)&&!/غیر ?قابل.{0,15}شست/.test(text))feature.push('قابل شست‌وشو در ماشین ظرفشویی');
 if(existing(p,'features'))feature.push(...p.approved.features.value.map(id=>data.vocabulary.features.find(t=>t.id===id)?.name).filter(Boolean));
 put('features',feature,'کاربرد پیشنهادی بر اساس نوع کالا؛ ویژگی‌های ساخت از متن مبدأ: '+title+' | '+desc.slice(0,800),.91);
 if(isLamp&&/گیره/.test(text+body))put('suitability',['کوچک'],'پایه گیره‌ای فضای سطح میز را اشغال نمی‌کند؛ پیشنهاد برای میزهای کوچک',.9);
 else if(/مناسب.{0,15}(?:فضا|اتاق).{0,8}کوچک|فضای کم/.test(desc))put('suitability',['کوچک'],'توضیح مبدأ درباره استفاده در فضای کوچک: '+desc.match(/.{0,40}(?:فضا|اتاق).{0,40}/)?.[0],.85);
 if(Object.keys(fields).length)plan.push({id:p.id,title:p.title,token:p.token,status:p.status,fields});
}
fs.mkdirSync('audit/enrichment-2026-10-22',{recursive:true});
fs.writeFileSync('audit/enrichment-2026-10-22/plan.json',JSON.stringify({definitions,products:plan},null,2));
fs.writeFileSync('audit/enrichment-2026-10-22/review.txt',plan.map(p=>`${p.id} ${p.title}\n`+Object.entries(p.fields).map(([f,v])=>`  ${f}: ${v.names.join('، ')}`).join('\n')).join('\n'));
console.log(JSON.stringify({products:plan.length,fields:Object.fromEntries(['style','room','color','material','features','suitability'].map(f=>[f,plan.filter(p=>p.fields[f]).length])),missingColor:data.products.filter(p=>p.status==='publish'&&!existing(p,'color')&&!plan.find(x=>x.id===p.id)?.fields.color).map(p=>p.id),missingRoom:data.products.filter(p=>p.status==='publish'&&!existing(p,'room')&&!plan.find(x=>x.id===p.id)?.fields.room).map(p=>[p.id,p.title])},null,2));
