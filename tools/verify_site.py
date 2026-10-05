from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import urlsplit,unquote
import hashlib,json,urllib.request,urllib.error,http.cookiejar,time
root=Path(__file__).resolve().parent.parent
class Page(HTMLParser):
 def __init__(self): super().__init__(); self.refs=[]; self.h1=0; self.titles=0; self.descriptions=0; self.images=[]
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='h1': self.h1+=1
  if tag=='title': self.titles+=1
  if tag=='meta' and a.get('name')=='description': self.descriptions+=1
  if tag=='img': self.images.append(a)
  for key in ['href','src','action']:
   if key in a: self.refs.append(a[key])
count=0
for file in list(root.glob('*.html'))+list((root/'portfolio').glob('*.html')):
 p=Page(); p.feed(file.read_text(encoding='utf-8-sig'))
 assert p.h1==1 and p.titles==1 and p.descriptions==1,file
 for image in p.images: assert image.get('alt') and image.get('width') and image.get('height'),(file,image)
 for ref in p.refs:
  u=urlsplit(ref)
  if not ref or u.scheme or ref.startswith('#'): continue
  assert (file.parent/unquote(u.path)).is_file(),(file,ref)
  count+=1
 assert '<iframe' not in file.read_text(encoding='utf-8'),file
print(f'PASS: six HTML pages, headings, image attributes and {count} local references; no eager PDF iframes.')
projects=json.loads((root/'assets/js/projects.json').read_text())
for project in projects:
 original=root/'requirement_docs/SAMPLE DWGS'/project['file']; copied=root/'assets/pdfs'/project['file']
 assert hashlib.sha256(original.read_bytes()).digest()==hashlib.sha256(copied.read_bytes()).digest()
 assert (root/f"assets/images/portfolio/{project['id']}.png").is_file()
print('PASS: seven PDF copies byte-identical to sources; all project thumbnails exist.')
base='http://127.0.0.1:8080/'
jar=http.cookiejar.CookieJar(); client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
def request(path,data=None):
 try:
  r=client.open(base+path,None if data is None else urllib.parse.urlencode(data).encode()); code=r.status
 except urllib.error.HTTPError as e: r=e; code=e.code
 return code,json.loads(r.read())
assert request('contact.php')[0]==405
assert request('contact.php',{'token':'wrong'})[0]==403
code,response=request('contact.php?action=token'); assert code==200 and len(response['token'])==64
token=response['token']; valid=dict(token=token,name='Test Visitor',email='visitor@example.com',phone='+91 99999 99999',company='Test',subject='Engineering enquiry',message='This is a local validation check.',website='')
assert request('contact.php',valid)[0]==429
assert request('contact.php',dict(valid,website='spam.example'))[0]==422
time.sleep(3.1)
assert request('contact.php',dict(valid,name=''))[0]==422
assert request('contact.php',dict(valid,email='invalid'))[0]==422
assert request('contact.php',dict(valid,email='a@example.com\r\nBcc: x@example.com'))[0]==422
assert request('contact.php',dict(valid,message='short'))[0]==422
assert request('contact.php',dict(valid,phone='not a phone'))[0]==422
assert request('contact.php',dict(valid,subject='x'*181))[0]==422
code,response=request('contact.php',valid); assert code==503 and response['success']==False
print('PASS: method, session token, honeypot, server timing, required fields, email, header injection, message, phone, length and unconfigured mail responses.')
print('Email delivery was not attempted: a verified hosting-domain sender is required.')

