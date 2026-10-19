"""Build the approved direction 03. Requires Pillow, aggdraw and fonttools.

The committed JSON contains the approved native paths, not font glyphs or a trace.
Run from any directory; generated website assets are committed with the plugin.
"""
from copy import deepcopy
from pathlib import Path
import html
import json
import math
import struct
import zipfile

import aggdraw
from fontTools.pens.boundsPen import BoundsPen
from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
DEST = ROOT / 'plugins/chidemoon-core/assets/brand'
SOURCE = json.loads((ROOT / 'design-system/chidemoon/brand-source.json').read_text(encoding='utf-8'))


def transform(elements, scale=1, dx=0, dy=0, color=None):
    result = deepcopy(elements)
    for e in result:
        if color:
            e['color'] = color
        if 'circle' in e:
            x, y, r = e['circle']
            e['circle'] = [x*scale+dx, y*scale+dy, r*scale]
        else:
            e['cmds'] = [[c[0], *[n*scale+(dx if i % 2 == 0 else dy) for i, n in enumerate(c[1:])]] for c in e['cmds']]
            e['stroke'] *= scale
    return result


def bounds(elements):
    result = []
    for e in elements:
        if 'circle' in e:
            x, y, r = e['circle']
            result.append((x-r, y-r, x+r, y+r))
            continue
        pen = BoundsPen(None)
        for c in e['cmds']:
            if c[0] == 'M': pen.moveTo(c[1:])
            elif c[0] == 'L': pen.lineTo(c[1:])
            elif c[0] == 'C': pen.curveTo(c[1:3], c[3:5], c[5:7])
            elif c[0] == 'Z': pen.closePath()
        a, b, c, d = pen.bounds
        r = e['stroke']/2
        result.append((a-r, b-r, c+r, d+r))
    return min(x[0] for x in result), min(x[1] for x in result), max(x[2] for x in result), max(x[3] for x in result)


def fit(elements, width, height, inset=0):
    a, b, c, d = bounds(elements)
    k = min((width-2*inset)/(c-a), (height-2*inset)/(d-b))
    return transform(elements, k, (width-(c-a)*k)/2-a*k, (height-(d-b)*k)/2-b*k)


def svg(elements, width, height, title):
    parts = [f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {width:.6f} {height:.6f}" role="img"><title>{html.escape(title)}</title>']
    for e in elements:
        if 'circle' in e:
            x, y, r = e['circle']
            parts.append(f'<circle cx="{x:.6f}" cy="{y:.6f}" r="{r:.6f}" fill="{e["color"]}"/>')
        else:
            path = ' '.join(c[0]+' '+' '.join(f'{n:.6f}' for n in c[1:]) for c in e['cmds'])
            attrs = f'fill="{e["color"]}"' if not e['stroke'] else f'fill="none" stroke="{e["color"]}" stroke-width="{e["stroke"]:.6f}" stroke-linecap="round" stroke-linejoin="round"'
            parts.append(f'<path d="{path}" {attrs}/>')
    return ''.join(parts)+'</svg>\n'


def render(elements, width, height, background=None, ss=3):
    canvas = Image.new('RGBA', (width*ss, height*ss), background or (255, 255, 255, 0))
    for e in transform(elements, ss):
        ctx = aggdraw.Draw(canvas)
        if 'circle' in e:
            x, y, r = e['circle']
            ctx.ellipse((x-r, y-r, x+r, y+r), aggdraw.Brush(e['color']))
            ctx.flush()
            continue
        if not e['stroke']:
            p = aggdraw.Path()
            for c in e['cmds']:
                if c[0] == 'M': p.moveto(*c[1:])
                elif c[0] == 'L': p.lineto(*c[1:])
                elif c[0] == 'C': p.curveto(*c[1:])
                elif c[0] == 'Z': p.close()
            ctx.path(p, aggdraw.Brush(e['color']))
            ctx.flush()
            continue
        current = start = None
        joins = []
        for c in e['cmds']:
            if c[0] == 'M':
                current = start = c[1:]
                joins.append(current)
                continue
            p = aggdraw.Path()
            p.moveto(*current)
            if c[0] == 'L': p.lineto(*c[1:]); current = c[1:]
            elif c[0] == 'C': p.curveto(*c[1:]); current = c[5:7]
            elif c[0] == 'Z': p.lineto(*start); current = start
            ctx = aggdraw.Draw(canvas)
            ctx.path(p, None, aggdraw.Pen(e['color'], e['stroke']))
            ctx.flush()
            joins.append(current)
        ctx = aggdraw.Draw(canvas)
        r = e['stroke']/2
        for x, y in joins:
            ctx.ellipse((x-r, y-r, x+r, y+r), aggdraw.Brush(e['color']))
        ctx.flush()
    return canvas.resize((width, height), Image.Resampling.LANCZOS)


def main():
    DEST.mkdir(parents=True, exist_ok=True)
    manifest = {'direction': '03', 'palette': SOURCE['palette'], 'assets': {}}
    for name, elements in SOURCE['assets'].items():
        a, b, c, d = bounds(elements)
        # Tiny raster safety margin only. Layout clear space belongs to CSS.
        elements = transform(elements, dx=1-a, dy=1-b)
        w, h = c-a+2, d-b+2
        for suffix, color in [('', None), ('-mono', SOURCE['palette']['ink']), ('-white', '#FFFFFF')]:
            stem = f'chidemoon-{name}{suffix}'
            art = transform(elements, color=color)
            (DEST / (stem+'.svg')).write_text(svg(art, w, h, 'Chidemoon — '+name+' — 03'), encoding='utf-8')
            width = 1600 if name.startswith('logo') else 1200
            height = math.ceil(width*h/w)
            render(fit(art, width, height, 1), width, height).save(DEST / (stem+'.png'))
            manifest['assets'][stem] = {'width': round(w, 6), 'height': round(h, 6)}
    symbol = SOURCE['assets']['symbol']
    for size in [16, 32, 48, 180, 192, 512]:
        optical = deepcopy(symbol)
        if size == 16:
            optical[0]['stroke'] = 20  # Same contour; one-pixel optical weight.
        render(fit(optical, size, size, size*.10), size, size, 'white', ss=4).save(DEST / f'chidemoon-icon-{size}.png')
    render(fit(SOURCE['assets']['logo-fa'], 1200, 630, 150), 1200, 630, 'white').convert('RGB').save(DEST / 'chidemoon-share.jpg', quality=96)
    sizes = [16, 32, 48]
    images = [(DEST / f'chidemoon-icon-{size}.png').read_bytes() for size in sizes]
    offset = 6+16*len(sizes)
    entries = []
    for size, data in zip(sizes, images):
        entries.append(struct.pack('<BBBBHHII', size, size, 0, 0, 1, 32, len(data), offset))
        offset += len(data)
    (DEST / 'favicon.ico').write_bytes(struct.pack('<HHH', 0, 1, len(sizes))+b''.join(entries)+b''.join(images))
    (DEST / 'manifest.json').write_text(json.dumps(manifest, indent=2)+'\n', encoding='utf-8')
    out = ROOT / 'output/brand-03'
    out.mkdir(parents=True, exist_ok=True)
    board = Image.new('RGB', (1600, 1100), 'white')
    for name, rect in [('logo-fa', (100, 80, 1400, 310)), ('logo-en', (100, 470, 1400, 260)), ('symbol', (120, 840, 230, 180)), ('wordmark-fa', (500, 830, 550, 180)), ('wordmark-en', (1100, 850, 410, 100))]:
        x, y, w, h = rect
        layer = render(fit(SOURCE['assets'][name], w, h, 10), w, h)
        board.paste(layer, (x, y), layer)
    board.save(out / 'approved-03.png')
    with zipfile.ZipFile(out / 'chidemoon-brand-03.zip', 'w', zipfile.ZIP_DEFLATED) as package:
        for asset in sorted(DEST.iterdir()):
            package.write(asset, 'assets/'+asset.name)
        package.write(ROOT / 'design-system/chidemoon/BRANDING.md', 'BRANDING.md')
        package.write(ROOT / 'design-system/chidemoon/brand-source.json', 'brand-source.json')
        package.write(out / 'approved-03.png', 'preview.png')
    print('Built approved direction 03:', len(list(DEST.iterdir())), 'assets and manifest.')


if __name__ == '__main__':
    main()
