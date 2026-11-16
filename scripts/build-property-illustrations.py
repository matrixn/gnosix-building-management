"""Generate original lightweight property illustrations; no third-party images."""
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT/'public/illustrations/properties'
OUT.mkdir(parents=True,exist_ok=True)

def p(x,y,z=0): return (320+(x-y)*1.25, 250+(x+y)*.43-z)
def pts(values): return ' '.join(f'{x:.1f},{y:.1f}' for x,y in values)
def polygon(values,fill,stroke='#9b8abb',extra=''):
 return f'<polygon points="{pts(values)}" fill="{fill}" stroke="{stroke}" stroke-width="1.2" {extra}/>'
def line(a,b,color='#bafa59',width=1):
 return f'<path d="M{a[0]:.1f} {a[1]:.1f}L{b[0]:.1f} {b[1]:.1f}" stroke="{color}" stroke-width="{width}" fill="none"/>'
def box(x,y,w,d,h,rows=2,cols=4,roof=False,balconies=False,glass=False):
 parts=[]
 parts.append(polygon([p(x,y+d),p(x+w,y+d),p(x+w,y+d,h),p(x,y+d,h)],'url(#front)'))
 parts.append(polygon([p(x+w,y),p(x+w,y+d),p(x+w,y+d,h),p(x+w,y,h)],'url(#side)'))
 parts.append(polygon([p(x,y,h),p(x+w,y,h),p(x+w,y+d,h),p(x,y+d,h)],'#4a416e','#c7b1ef'))
 for row in range(rows):
  z=12+row*(h-12)/max(1,rows)
  height=min(20,(h-12)/max(1,rows)*.55)
  for col in range(cols):
   left=x+8+col*(w-12)/max(1,cols); ww=(w-18)/max(1,cols)*.6
   parts.append(polygon([p(left,y+d,z),p(left+ww,y+d,z),p(left+ww,y+d,z+height),p(left,y+d,z+height)],'#69dfe077' if glass or row==0 else '#bafa5977','#bafa59' if not glass else '#69dfe0'))
  for col in range(max(1,int(d/25))):
   yy=y+8+col*(d-12)/max(1,int(d/25))
   parts.append(polygon([p(x+w,yy,z),p(x+w,yy+10,z),p(x+w,yy+10,z+height),p(x+w,yy,z+height)],'#a993ff77','#b5a2e5'))
  if balconies: parts.append(line(p(x,y+d+6,z-3),p(x+w,y+d+6,z-3),'#d6c8f8',2))
 if roof:
  ridge=h+42
  parts.append(polygon([p(x,y,h),p(x,y+d,h),p(x+w/2,y+d,ridge),p(x+w/2,y,ridge)],'#74618b','#b8a3d3'))
  parts.append(polygon([p(x+w,y,h),p(x+w,y+d,h),p(x+w/2,y+d,ridge),p(x+w/2,y,ridge)],'#424559','#c4dba1'))
  parts.append(polygon([p(x,y+d,h),p(x+w,y+d,h),p(x+w/2,y+d,ridge)],'#54486d','#ba9cdd'))
  parts.append(polygon([p(x+w/2-7,y+d,h+8),p(x+w/2+7,y+d,h+8),p(x+w/2+7,y+d,h+23),p(x+w/2-7,y+d,h+23)],'#bafa5977','#bafa59'))
 return ''.join(parts)
def tree(x,y,tall=False):
 a=p(x,y);height=58 if tall else 34;top=p(x,y,height)
 parts=[line(a,top,'#aa96a8',3)]
 if tall:
  for dx,dy in [(-22,-5),(23,-7),(-18,9),(21,10),(0,-16)]:
   parts.append(f'<path d="M{top[0]:.1f} {top[1]:.1f}q{dx/2} {-20+dy} {dx} {dy}" stroke="#bafa59" stroke-width="3" fill="none"/>')
 else:
  parts.append(f'<ellipse cx="{top[0]:.1f}" cy="{top[1]:.1f}" rx="19" ry="22" fill="#38523d" stroke="#a4c87b"/>')
 return ''.join(parts)
def scene(kind):
 ground=polygon([p(-120,-90,-3),p(150,-90,-3),p(150,115,-3),p(-120,115,-3)],'#20243b','#4b4569')
 parts=[ground]
 if kind=='house':
  parts += [box(-65,-40,125,85,65,1,3,True),tree(-88,60),tree(100,35)]
 elif kind=='cabin':
  x,y,w,d=-60,-32,105,85
  parts += [polygon([p(x,y+d),p(x+w,y+d),p(x+w/2,y+d,118)],'#55466d','#c5aadf'),polygon([p(x+w,y),p(x+w,y+d),p(x+w/2,y+d,118),p(x+w/2,y,118)],'#394953','#cee9a2')]
  parts += [polygon([p(-24,y+d,4),p(8,y+d,4),p(8,y+d,48),p(-24,y+d,48)],'#bafa5944','#bafa59')]
  parts += [line(p(x,y+d,1),p(x+w/2,y+d,118),'#d8f8a6',3),tree(-95,20),tree(90,30),tree(92,-45)]
 elif kind=='guesthouse':
  parts += [box(-75,-38,145,82,86,2,4,True,True),tree(-100,50),tree(100,42)]
 elif kind=='hostel':
  parts += [box(-100,-35,190,70,105,3,8,False,True),polygon([p(-40,35,34),p(20,35,34),p(20,55,28),p(-40,55,28)],'#a993ff','#ddc7ff'),tree(120,30)]
 elif kind=='hotel':
  parts += [box(-78,-45,140,90,155,5,5),polygon([p(-27,45,32),p(20,45,32),p(20,69,26),p(-27,69,26)],'#a993ff','#d6c2fc'),tree(-95,70),tree(105,35)]
 elif kind=='resort':
  parts += [box(-100,-48,70,58,65,1,2,True),box(10,-62,90,58,80,2,3,True)]
  parts += [polygon([p(-55,40,0),p(55,40,0),p(55,88,0),p(-55,88,0)],'#336e83','#69dfe0')]
  for i in range(3):parts.append(line(p(-43+i*28,46,1),p(-43+i*28,80,1),'#83e0d7',1))
  parts += [tree(-110,85,True),tree(110,65,True),tree(115,-18,True)]
 elif kind=='apartment':
  parts += [box(-47,-42,88,83,204,7,3,False,True),box(41,-42,32,83,150,5,1),tree(-90,45),tree(104,60)]
 elif kind=='mixed':
  parts += [box(-95,-45,170,95,42,1,5,False,False,True),box(-62,-35,100,67,155,4,3,False,True),polygon([p(-95,50,45),p(75,50,45),p(75,65,42),p(-95,65,42)],'#9b77cb','#ceabea'),tree(115,30)]
 else:
  parts += [box(-90,-45,80,65,100,2,2,False,False,True),box(12,-30,78,70,65,1,3),tree(-85,70),tree(100,70)]
 parts.append(line(p(-110,105,1),p(125,105,1),'#bafa5944',1))
 return ''.join(parts)
for kind in ['house','cabin','guesthouse','hostel','hotel','resort','apartment','mixed','other']:
 svg=f'''<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360"><defs><radialGradient id="sky"><stop stop-color="#3d3158"/><stop offset="1" stop-color="#14172c"/></radialGradient><linearGradient id="front" x2="1" y2="1"><stop stop-color="#514269"/><stop offset="1" stop-color="#292b44"/></linearGradient><linearGradient id="side"><stop stop-color="#31394c"/><stop offset="1" stop-color="#20283b"/></linearGradient><pattern id="grid" width="32" height="32" patternUnits="userSpaceOnUse"><path d="M32 0H0V32" fill="none" stroke="#a993ff" stroke-opacity=".08"/></pattern></defs><rect width="640" height="360" fill="url(#sky)"/><rect width="640" height="360" fill="url(#grid)"/><ellipse cx="335" cy="295" rx="220" ry="35" fill="#080a1a" opacity=".5"/>{scene(kind)}<path d="M35 35h30M35 35v30M605 325h-30M605 325v-30" fill="none" stroke="#a993ff" opacity=".55"/><circle cx="574" cy="42" r="4" fill="#bafa59"/></svg>'''
 (OUT/f'{kind}.svg').write_text(svg,encoding='utf-8')
print(f'Generated 9 original property illustrations in {OUT}')
