from pathlib import Path
import pymupdf as fitz
sheet=fitz.open()
for p in Path('requirement_docs/SAMPLE DWGS').glob('*.pdf'):
 d=fitz.open(p); pix=d[0].get_pixmap(matrix=fitz.Matrix(0.75,0.75)); out=Path('assets/images/portfolio')/(p.stem.lower().replace(' ','-')+'.png'); pix.save(out)
 page=sheet.new_page(width=800,height=600); page.insert_text((20,25),p.name); page.insert_image(fitz.Rect(20,40,780,580),pixmap=pix)
sheet.save('tools/source-previews.pdf')
for i,p in enumerate(sheet): p.get_pixmap().save(f'tools/preview-{i}.png')
b=Path('requirement_docs/PROFILE PSES.doc').read_bytes(); pos=0; i=0
while True:
 start=b.find(b'\xff\xd8\xff',pos)
 if start<0: break
 end=b.find(b'\xff\xd9',start)
 if end<0: break
 Path(f'tools/profile-image-{i}.jpg').write_bytes(b[start:end+2]); pos=end+2; i+=1
print('Extracted profile images:',i)
