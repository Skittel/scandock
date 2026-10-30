"""ScanDock: screenshots of the demo tenant for the support wiki.

Runs on the server (its addresses must be in scandock.demoAllowedIps) with
Playwright installed in /opt/scandock-tools:
  PLAYWRIGHT_BROWSERS_PATH=/opt/scandock-tools/browsers \
  /opt/scandock-tools/venv/bin/python screenshots.py <out dir> <demo ftp file> <slow upload file> [main|secret|setup:<token>]

<demo ftp file>: "<public account public_id> <ftp user> <ftp password> <id> <private account public_id>"
(read from the database; never commit it). Run `scandock demo:seed` before and after.
"""
import sys, time, subprocess
from playwright.sync_api import sync_playwright

OUT = sys.argv[1]
PUB, FTPUSER, FTPPW, _ID, PRIV = open(sys.argv[2]).read().split()
B = 'https://demo.scandock.cloud'
BIG = sys.argv[3]
MODE = sys.argv[4] if len(sys.argv) > 4 else 'main'

def shot(pg, selector, path, pad=12, extra_h=0, max_h=None):
    el = pg.locator(selector).first
    el.scroll_into_view_if_needed(); pg.wait_for_timeout(150)
    box = el.bounding_box()
    h = box['height'] + 2 * pad + extra_h
    if max_h: h = min(h, max_h)
    pg.screenshot(path=f'{OUT}/{path}', clip={'x': max(box['x'] - pad, 0), 'y': max(box['y'] - pad, 0), 'width': box['width'] + 2 * pad, 'height': h})

with sync_playwright() as p:
    b = p.chromium.launch()
    ctx = b.new_context(viewport={'width': 1280, 'height': 860}, locale='de-DE')
    pg = ctx.new_page()
    sec = 'details.account:has-text("Kopierer Empfang")'

    if MODE == 'main':
        pg.goto(B + '/scans'); pg.wait_for_load_state('networkidle')
        pg.screenshot(path=f'{OUT}/01-uebersicht.png')
        pg.click('.user-menu > summary'); pg.wait_for_timeout(300)
        pg.screenshot(path=f'{OUT}/02-kopfzeile-menue.png', clip={'x': 0, 'y': 0, 'width': 1280, 'height': 330})
        pg.click('h1')
        shot(pg, sec + ' > summary', '03-kopf-ansicht.png', 10)
        pg.click(sec + ' [data-view="list"]'); pg.wait_for_timeout(400)
        boxes = pg.locator(sec + ' .scan-list input[name="ids[]"]')
        for i in range(3): boxes.nth(i).check()
        shot(pg, sec, '04-liste-auswahl.png', 8, max_h=430)
        for i in range(3): boxes.nth(i).uncheck()
        pg.click(sec + ' [data-view="grid"]'); pg.wait_for_timeout(400)
        shot(pg, sec + ' .flash.rejection', '05-hinweis-abgelehnt.png', 14)
        up = subprocess.Popen(['curl', '-s', '--limit-rate', '20k', '-T', BIG, f'ftp://{FTPUSER}:{FTPPW}@ftp.scandock.cloud/Rechnung_10445.pdf'])
        time.sleep(4); pg.reload(); pg.wait_for_selector(sec + ' .scan.pending', timeout=15000)
        shot(pg, sec + ' .grid', '06-wird-uebertragen.png', 10, max_h=370)
        up.wait()
        pg.goto(B + '/admin/ftp'); pg.wait_for_load_state('networkidle')
        pg.screenshot(path=f'{OUT}/07-ftp-konten.png', clip={'x': 0, 'y': 0, 'width': 1280, 'height': 430})
        pg.goto(B + '/admin/ftp/' + PUB); pg.wait_for_load_state('networkidle')
        shot(pg, 'section.card:has-text("Zugangsdaten für den Kopierer")', '08-ftp-zugangsdaten.png', 10)
        pg.goto(B + '/admin/ftp/' + PRIV); pg.wait_for_load_state('networkidle')
        shot(pg, 'section.card:has-text("Wer sieht die Scans?")', '09-wer-sieht.png', 10)
        pg.fill('.member-picker input[name="member_ref"]', 'be'); pg.wait_for_selector('.suggestions li:not(.note)', timeout=8000); pg.wait_for_timeout(300)
        pg.evaluate("document.querySelectorAll('.suggestions li.note').forEach(n => n.remove())")
        shot(pg, '.member-picker', '10-vorschlaege.png', 10, extra_h=80)
        pg.goto(B + '/admin/settings'); pg.wait_for_load_state('networkidle')
        shot(pg, '#branding', '11-einstellungen-firma.png', 10)
        pg.goto(B + '/admin/journal?category=ftp'); pg.wait_for_load_state('networkidle')
        pg.screenshot(path=f'{OUT}/12-journal.png', clip={'x': 0, 'y': 0, 'width': 1280, 'height': 560})
    elif MODE == 'secret':
        pg.goto(B + '/scans'); pg.wait_for_load_state('networkidle')
        pg.screenshot(path=f'{OUT}/13-clientschluessel-hinweis.png', clip={'x': 0, 'y': 0, 'width': 1280, 'height': 260})
    elif MODE.startswith('setup:'):
        pg.goto(B + '/setup/' + MODE[6:]); pg.wait_for_load_state('networkidle')
        pg.screenshot(path=f'{OUT}/14-einrichtung.png', clip={'x': 0, 'y': 0, 'width': 1280, 'height': 860})
    b.close()
print('ok', MODE)
