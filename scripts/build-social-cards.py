"""Optional offline renderer for the code-native brand cards (requires Pillow)."""
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[1]
font_paths = [('C:/Windows/Fonts/arial.ttf', 'C:/Windows/Fonts/arialbd.ttf'), ('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf')]
font, bold = next(pair for pair in font_paths if Path(pair[0]).is_file())
for locale in ['ro', 'en']:
    im = Image.new('RGB', (1200, 630), '#0b171e'); draw = ImageDraw.Draw(im)
    def text(x, y, value, size, colour='#edf7f4', heavy=False):
        draw.text((x,y),value,font=ImageFont.truetype(bold if heavy else font,size),fill=colour)
    for x in range(700,1200,32): draw.line((x,0,x,630),fill='#132a2f')
    for y in range(0,630,32): draw.line((700,y,1200,y),fill='#132a2f')
    for i in reversed(range(7)):
        y=155+i*45
        draw.polygon([(750,y),(935,y-80),(1130,y),(940,y+85)],fill='#1b4148',outline='#45716d',width=2)
        draw.polygon([(750,y),(940,y+85),(940,y+99),(750,y+14)],fill='#183238',outline='#325651')
        draw.polygon([(940,y+85),(1130,y),(1130,y+14),(940,y+99)],fill='#102b32',outline='#325651')
    draw.line([(840,200),(840,432),(940,478),(1040,432),(1040,290)],fill='#8af0d2',width=3)
    for x,y in [(840,300),(940,478),(1040,385)]: draw.ellipse((x-5,y-5,x+5,y+5),fill='#8af0d2')
    text(60,45,'GNOSIX',33,heavy=True); text(61,88,'BUILDING MANAGEMENT',12,'#9bb2b8')
    if locale=='en':
        text(60,208,'Buildings have',62); text(60,280,'a life.',62); text(60,364,'Give them a memory.',54,'#8af0d2')
        text(60,535,'THE DIGITAL MEMORY OF BUILDINGS',16,'#9bb2b8'); text(60,572,'gnosix.zion3d.ro / Pilot partner programme',15,'#8af0d2')
    else:
        text(60,208,'Clădirile au',62); text(60,280,'o viață.',62); text(60,364,'Dă-le o memorie.',62,'#8af0d2')
        text(60,535,'MEMORIA DIGITALĂ A CLĂDIRILOR',16,'#9bb2b8'); text(60,572,'gnosix.zion3d.ro / Program parteneri pilot',15,'#8af0d2')
    name = 'gnosix-social-en.png' if locale=='en' else 'gnosix-social.png'
    im.save(ROOT/'public/images'/name,optimize=True)
print('Rendered Romanian and English 1200x630 social cards.')
