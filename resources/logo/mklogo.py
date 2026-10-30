import sys
words = open(sys.argv[1]).read().split('\n')
_, _, width = words[0].split(); scan, kai = words[1], words[2]
NAVY, BLUE = '#0b2140', '#04a3ef'

def wave(y, amp, period=80, x0=-240, x1=720):
    d = f'M{x0},{y}'
    x = x0
    while x < x1:
        d += f' q{period/4},{-amp} {period/2},0 q{period/4},{amp} {period/2},0'
        x += period
    return d

def icon(animated, uid, fade=.12):
    anim = ''
    cls1 = cls2 = ''
    if animated:
        anim = f'''<style>
  @keyframes sk-wave-{uid} {{ from {{ transform: translateX(0) }} to {{ transform: translateX(-80px) }} }}
  .sk-w1-{uid} {{ animation: sk-wave-{uid} 24s linear infinite; }}
  .sk-w2-{uid} {{ animation: sk-wave-{uid} 36s linear infinite reverse; }}
  @media (prefers-reduced-motion: reduce) {{ .sk-w1-{uid}, .sk-w2-{uid} {{ animation: none; }} }}
</style>'''
        cls1, cls2 = f' class="sk-w1-{uid}"', f' class="sk-w2-{uid}"'
    return f'''{anim}
<defs>
  <linearGradient id="sk-fade-{uid}" x1="80" x2="385" gradientUnits="userSpaceOnUse">
    <stop offset="0" stop-color="#fff" stop-opacity="0"/><stop offset="{fade}" stop-color="#fff"/>
    <stop offset="{1-fade}" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity="0"/>
  </linearGradient>
  <mask id="sk-mask-{uid}" maskUnits="userSpaceOnUse" x="70" y="370" width="330" height="70">
    <rect x="70" y="370" width="330" height="70" fill="url(#sk-fade-{uid})"/>
  </mask>
</defs>
<g fill="{NAVY}">
  <rect x="106" y="326" width="30" height="60" rx="10"/>
  <rect x="334" y="326" width="30" height="60" rx="10"/>
  <rect x="116" y="362" width="238" height="20" rx="4"/>
  <rect x="146" y="380" width="10" height="26"/><rect x="196" y="380" width="10" height="26"/>
  <rect x="264" y="380" width="10" height="26"/><rect x="314" y="380" width="10" height="26"/>
</g>
<path d="M164,212 H262 L310,260 V356 H164 Z" fill="#fff" stroke="{NAVY}" stroke-width="16" stroke-linejoin="round"/>
<path d="M262,206 V250 a12,12 0 0 0 12,12 H318 Z" fill="{BLUE}" stroke="{BLUE}" stroke-width="4" stroke-linejoin="round"/>
<rect x="186" y="270" width="74" height="14" rx="7" fill="{NAVY}"/>
<rect x="186" y="296" width="64" height="13" rx="6.5" fill="{NAVY}"/>
<rect x="186" y="324" width="74" height="15" rx="7.5" fill="{BLUE}"/>
<g mask="url(#sk-mask-{uid})" fill="none" stroke="{BLUE}" stroke-linecap="round">
  <path{cls2} d="{wave(393, 7)}" stroke-width="5"/>
  <path{cls1} d="{wave(409, 9)}" stroke-width="14"/>
</g>'''

S_ = 150/705
full = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="78 190 1150 240" role="img" aria-label="ScanKai">
<title>ScanKai</title>{icon(True, 'l')}
<g transform="translate(418 415) scale({S_:.5f})">
  <path fill="{NAVY}" d="{scan}"/>
  <path fill="{BLUE}" d="{kai}"/>
</g>
</svg>
'''
icon_svg = lambda animated, uid, fade=.12: f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="95 172 280 280" role="img" aria-label="ScanKai">
<title>ScanKai</title>{icon(animated, uid, fade)}
</svg>
'''
open('logo-scankai.svg','w').write(full)
open('logo-scankai-icon.svg','w').write(icon_svg(True, 'i'))
open('favicon.svg','w').write(icon_svg(False, 'f', .02))
