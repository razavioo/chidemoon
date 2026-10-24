"""Read public Basalam pages; extract product-scoped structured data only."""
import concurrent.futures, html, json, re, sys, urllib.request
from html.parser import HTMLParser
class Parser(HTMLParser):
    def __init__(self):
        super().__init__(); self.scripts=[]; self.current=None; self.description=[]; self.depth=0; self.ogImage=None
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if tag=='script' and a.get('type')=='application/ld+json': self.current=''
        if tag=='meta' and a.get('property')=='og:image': self.ogImage=a.get('content')
        if self.depth: self.depth+=1
        elif 'bs-read-more__text' in a.get('class','').split(): self.depth=1
    def handle_data(self,data):
        if self.current is not None: self.current+=data
        if self.depth: self.description.append(data)
    def handle_endtag(self,tag):
        if tag=='script' and self.current is not None: self.scripts.append(self.current); self.current=None
        if self.depth: self.depth-=1
def walk(x):
    if isinstance(x,list):
        for v in x: yield from walk(v)
    if isinstance(x,dict):
        yield x
        if '@graph' in x: yield from walk(x['@graph'])
def fetch(item):
    out={'id':item['id'],'requestedUrl':item['url']}
    try:
        req=urllib.request.Request(item['url'],headers={'User-Agent':'Mozilla/5.0'})
        with urllib.request.urlopen(req,timeout=25) as r:
            text=r.read().decode('utf-8'); out.update(status=r.status,finalUrl=r.url)
        p=Parser();p.feed(text);nodes=[]
        for s in p.scripts:
            try: nodes.extend(walk(json.loads(s)))
            except ValueError: pass
        product=next((n for n in nodes if n.get('@type')=='Product' or isinstance(n.get('@type'),list) and 'Product' in n['@type']),None)
        if product:
            out.update(product=product,fullDescription=''.join(p.description).strip(),ogImage=p.ogImage)
        else: out['error']='No product structured data'
        out['breadcrumbs']=[n for n in nodes if n.get('@type')=='BreadcrumbList']
    except Exception as e: out['error']=str(e)
    return out
if __name__=='__main__':
    items=json.load(open(sys.argv[1],encoding='utf-8-sig'))
    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool: results=list(pool.map(fetch,items))
    json.dump(results,open(sys.argv[2],'w'),ensure_ascii=False,indent=2)
