import concurrent.futures, html, json, re, urllib.request
targets=json.load(open('/tmp/public-price-targets.json',encoding='utf-8-sig'))
digits=str.maketrans('۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩','01234567890123456789')
def check(p):
    out={'id':p['id'],'url':p['url'],'expectedPrice':p['price']}
    try:
        req=urllib.request.Request(p['url'],headers={'User-Agent':'Mozilla/5.0','Cache-Control':'no-cache'})
        with urllib.request.urlopen(req,timeout=35) as r: body=r.read().decode('utf-8');out['status']=r.status
        blocks=re.findall(r'<span\b[^>]*class=["\'][^"\']*woocommerce-Price-amount[^"\']*["\'][^>]*>(.*?)</bdi>\s*</span>',body,re.S)
        amounts=[html.unescape(re.sub('<[^>]+>','',b)).translate(digits) for b in blocks]
        unit=p.get('currencyLabel','ریال')
        wrong='ریال' if unit=='تومان' else 'تومان'
        found=any(re.sub(r'[^0-9]','',a)==p['price'] and unit in a for a in amounts)
        out.update(priceVisible=found,wrongCurrencyLabel=any(wrong in a for a in amounts),tagLinks=bool(re.search(r'<a\b[^>]*href=["\'][^"\']*/product-tag/',body)),ok=found and not any(wrong in a for a in amounts))
    except Exception as e: out.update(error=str(e),ok=False)
    return out
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool: result=list(pool.map(check,targets))
json.dump(result,open('/tmp/public-price-verification.json','w'),ensure_ascii=False,indent=2)
print(json.dumps({'checked':len(result),'issues':[r for r in result if not r['ok']]}))
