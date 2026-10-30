"""Builds the ScanKai logo files from the original design (logo design by the
project owner). The wordmark is converted to outlines with Liberation Sans Bold
(metric-compatible with Arial, SIL OFL) so it renders identically everywhere."""
from fontTools.ttLib import TTFont
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.pens.boundsPen import BoundsPen

FONT = '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf'
SIZE, X0, BASE, SPACING = 145, 390, 235, -5

font = TTFont(FONT)
gs, cmap, hmtx = font.getGlyphSet(), font.getBestCmap(), font['hmtx']
k = SIZE / font['head'].unitsPerEm

def outline(text, x):
    cmds, bounds = [], None
    for ch in text:
        g = cmap[ord(ch)]
        t = (k, 0, 0, -k, x, BASE)
        pen = SVGPathPen(gs); gs[g].draw(TransformPen(pen, t)); cmds.append(pen.getCommands())
        bp = BoundsPen(gs); gs[g].draw(TransformPen(bp, t))
        if bp.bounds:
            b = bp.bounds
            bounds = b if bounds is None else (min(bounds[0], b[0]), min(bounds[1], b[1]), max(bounds[2], b[2]), max(bounds[3], b[3]))
        x += hmtx[g][0] * k + SPACING
    return ' '.join(cmds), x, bounds

scan, x, _ = outline('Scan', X0)
kai, x_end, kb = outline('Kai', x)
right = round(kb[2] + 20)

WAVE_A = 'C75 {a} 115 {b} 180 {m} S285 {c} 350 {m}'
def wave(m, phase):
    # phase +1 = original shape, -1 = mirrored shape
    return f'M20 {m} ' + WAVE_A.format(a=m - 29 * phase, b=m + 26 * phase, c=m - 26 * phase, m=m)

def wave_el(m, stroke, animated):
    d = wave(m, 1)
    anim = ''
    if animated:
        # Very slow undulation: one full cycle every 20 s.
        anim = (f'\n    <animate attributeName="d" dur="20s" repeatCount="indefinite" calcMode="spline"'
                f' keyTimes="0;0.5;1" keySplines="0.45 0 0.55 1;0.45 0 0.55 1"'
                f' values="{wave(m, 1)};{wave(m, -1)};{wave(m, 1)}"/>\n  ')
    return f'  <path d="{d}" fill="none" stroke="{stroke}" stroke-width="22" stroke-linecap="round">{anim}</path>'

def icon(animated):
    return f'''  <defs>
    <linearGradient id="sk-blue" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#00BFFF"/>
      <stop offset="100%" stop-color="#0099EE"/>
    </linearGradient>
  </defs>
  <g fill="#082B55">
    <rect x="48" y="210" width="35" height="85" rx="8"/>
    <rect x="42" y="200" width="47" height="20" rx="10"/>
    <rect x="295" y="210" width="35" height="85" rx="8"/>
    <rect x="289" y="200" width="47" height="20" rx="10"/>
    <rect x="75" y="255" width="230" height="25" rx="3"/>
    <rect x="110" y="275" width="24" height="40"/>
    <rect x="245" y="275" width="24" height="40"/>
  </g>
  <path d="M115 50 Q115 30 135 30 H240 L295 85 V255 H115 Z" fill="#FFFFFF" stroke="#082B55" stroke-width="12" stroke-linejoin="round"/>
  <path d="M240 30 V72 Q240 85 253 85 H295 Z" fill="url(#sk-blue)"/>
  <rect x="145" y="112" width="110" height="18" rx="9" fill="#082B55"/>
  <rect x="145" y="150" width="85" height="18" rx="9" fill="#082B55"/>
  <rect x="145" y="188" width="95" height="18" rx="9" fill="url(#sk-blue)"/>
{wave_el(299, "#FFFFFF", animated)}
{wave_el(305, "url(#sk-blue)", animated)}'''

full = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 10 {right} 330" role="img" aria-label="ScanKai Logo">
  <title>ScanKai</title>
{icon(True)}
  <path fill="#082B55" d="{scan}"/>
  <path fill="url(#sk-blue)" d="{kai}"/>
</svg>
'''
square = lambda animated: f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="5 2 350 350" role="img" aria-label="ScanKai">
  <title>ScanKai</title>
{icon(animated)}
</svg>
'''
open('logo-scankai.svg', 'w').write(full)
open('logo-scankai-icon.svg', 'w').write(square(True))
open('favicon.svg', 'w').write(square(False))
print('viewBox width', right)
