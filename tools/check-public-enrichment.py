import concurrent.futures, html, json, re, sys, urllib.request
data=json.load(open(sys.argv[1] if len(sys.argv)>1 else '/tmp/enrichment-verification.json',encoding='utf-8'))
def norm(s):return re.sub(r'\s+',' ',html.unescape(re.sub('<[^>]+>',' ',s)).replace('\u200c',' ').replace('\u0654','').replace('\u0655','').replace('ي','ی').replace('ك','ک')).strip()
def check(p):
    row={'id':p['id'],'url':p['url']}
    try:
        with urllib.request.urlopen(urllib.request.Request(p['url'],headers={'User-Agent':'Mozilla/5.0','Cache-Control':'no-cache'}),timeout=40) as r:body=r.read().decode('utf-8');row['status']=r.status
        blocks=re.findall(r'<dl\b[^>]*class=["\'][^"\']*ch-product-facts[^"\']*["\'][^>]*>(.*?)</dl>',body,re.S)
        facts={norm(k):norm(v) for b in blocks for k,v in re.findall(r'<dt[^>]*>(.*?)</dt>\s*<dd[^>]*>(.*?)</dd>',b,re.S)}
        missing=[{'field':k,'value':v} for k,names in p['expect'].items() for v in names if norm(v) not in facts.get(norm(k),'')]
        row.update(missing=missing,publicTagLinks=bool(re.search(r'<a\b[^>]*href=["\'][^"\']*/product-tag/',body)),ok=not missing and bool(facts))
    except Exception as e:row.update(ok=False,error=str(e))
    return row
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:results=list(pool.map(check,data['targets']))
json.dump(results,open(sys.argv[2] if len(sys.argv)>2 else '/tmp/public-enrichment-verification.json','w'),ensure_ascii=False,indent=2)
print(json.dumps({'checked':len(results),'issues':[r for r in results if not r['ok']]}))
