import concurrent.futures,json,sys,urllib.request,urllib.parse
items=json.load(open(sys.argv[1],encoding='utf-8-sig'))
def fetch(i):
    q=urllib.parse.parse_qs(urllib.parse.urlsplit(i['url']).query)['q'][0]
    try:
        req=urllib.request.Request('https://search.basalam.com/ai-engine/api/v2.0/product/search',data=json.dumps({'q':q,'rows':100,'start':0}).encode(),headers={'Content-Type':'application/json','Accept':'application/json'})
        with urllib.request.urlopen(req,timeout=25) as r: data=json.load(r)
        return {'id':i['id'],'query':q,'data':data}
    except Exception as e: return {'id':i['id'],'error':str(e)}
with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool: results=list(pool.map(fetch,items))
json.dump(results,open(sys.argv[2],'w'),ensure_ascii=False,indent=2)
