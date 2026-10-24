import json, urllib.request
rows=[]
for product in ['563439','524436']:
    url='https://api.digikala.com/v2/product/'+product+'/'
    row={'product':product,'url':url}
    try:
        with urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'Mozilla/5.0'}),timeout=20) as r:
            body=r.read().decode('utf-8');row.update(status=r.status,finalUrl=r.url)
        try: row['response']=json.loads(body)
        except ValueError: row['error']='Non-JSON challenge response'
    except Exception as e: row['error']=str(e)
    rows.append(row)
json.dump(rows,open('/tmp/digikala-price-check.json','w'),ensure_ascii=False)
print(json.dumps([{k:v for k,v in r.items() if k!='response'} for r in rows]))
