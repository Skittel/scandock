"""Generates multi-page demo PDFs (fictitious company "Muster GmbH") for the
ScanDock demo tenant. Usage: python3 mkdemo.py <output dir>"""
import random, sys, os, textwrap, datetime

out = sys.argv[1]
WORDS = ("lorem ipsum dolor sit amet consectetur adipiscing elit sed do eiusmod tempor incididunt ut labore et dolore "
         "magna aliqua enim ad minim veniam quis nostrud exercitation ullamco laboris nisi aliquip ex ea commodo "
         "consequat duis aute irure in reprehenderit voluptate velit esse cillum fugiat nulla pariatur excepteur sint "
         "occaecat cupidatat non proident sunt culpa qui officia deserunt mollit anim id est laborum curabitur pretium "
         "tincidunt lacus nulla gravida orci a odio nullam varius turpis et commodo pharetra est eros bibendum elit "
         "nec luctus magna felis sollicitudin mauris integer in mauris eu nibh euismod gravida duis ac tellus et risus "
         "vulputate vehicula donec lobortis risus a elit etiam tempor ut ullamcorper ligula eu tempor congue").split()
KINDS = ["Rechnung", "Lieferschein", "Angebot", "Auftragsbestaetigung", "Protokoll", "Vertrag", "Gutschrift",
         "Mahnung", "Wartungsbericht", "Stundenzettel"]
COLORS = [(0.03, 0.17, 0.33), (0.0, 0.6, 0.93), (0.2, 0.5, 0.3), (0.6, 0.2, 0.2), (0.4, 0.3, 0.6)]

def esc(s):
    return s.replace('\\', '\\\\').replace('(', '\\(').replace(')', '\\)')

def sentence(rng):
    words = [rng.choice(WORDS) for _ in range(rng.randint(6, 16))]
    return ' '.join(words).capitalize() + '.'

def paragraph(rng):
    return ' '.join(sentence(rng) for _ in range(rng.randint(3, 7)))

def page_stream(rng, title, doc_no, page, pages, color, date):
    r, g, b = color
    ops = [f"{r} {g} {b} rg 0 792 595 50 re f",                      # header bar
           f"BT /F2 18 Tf 1 1 1 rg 50 810 Td ({esc(title)}) Tj ET",
           f"BT /F1 9 Tf 1 1 1 rg 400 812 Td (Nr. {doc_no}  |  {date}) Tj ET"]
    y = 760
    if page == 1:
        ops.append(f"BT /F2 13 Tf 0 0 0 rg 50 {y} Td (Betreff: {esc(sentence(rng)[:60])}) Tj ET"); y -= 28
        # small table on the first page
        ops.append("0.85 g 50 %d 495 18 re f" % (y - 4))
        ops.append(f"BT /F2 9 Tf 0 g 56 {y + 1} Td (Pos.) Tj 50 0 Td (Bezeichnung) Tj 300 0 Td (Menge) Tj 80 0 Td (Betrag) Tj ET")
        y -= 20
        for pos in range(1, rng.randint(3, 7)):
            ops.append(f"BT /F1 9 Tf 0 g 56 {y} Td ({pos}) Tj 50 0 Td ({esc(' '.join(rng.choice(WORDS) for _ in range(3)).title())}) Tj "
                       f"300 0 Td ({rng.randint(1, 40)}) Tj 80 0 Td ({rng.randint(10, 9999)},{rng.randint(0, 99):02d} EUR) Tj ET")
            y -= 15
        y -= 15
    while y > 110:
        lines = textwrap.wrap(paragraph(rng), 92)
        if y - 13 * len(lines) < 90:
            lines = lines[: max(1, (y - 90) // 13)]
        ops.append(f"BT /F1 10 Tf 0 g 13 TL 50 {y} Td")
        ops += [f"({esc(l)}) '" for l in lines]
        ops.append("ET")
        y -= 13 * len(lines) + 12
        if rng.random() < 0.2 and y > 200:
            ops.append(f"{r} {g} {b} RG 1 w 50 {y} m 545 {y} l S"); y -= 16
    ops.append(f"BT /F1 8 Tf 0.4 g 50 40 Td (Muster GmbH - Demo-Dokument {doc_no}) Tj 430 0 Td (Seite {page} von {pages}) Tj ET")
    return '\n'.join(ops).encode('latin-1')

def build(path, seed):
    rng = random.Random(seed)
    kind = KINDS[seed % len(KINDS)]
    doc_no = f"{2026}-{1000 + seed * 37}"
    date = (datetime.date(2026, 10, 1) + datetime.timedelta(days=seed)).strftime('%d.%m.%Y')
    color = COLORS[seed % len(COLORS)]
    pages = rng.randint(2, 6)
    objs = {1: b"<< /Type /Catalog /Pages 2 0 R >>",
            3: b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
            4: b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>"}
    kids, n = [], 5
    for p in range(1, pages + 1):
        stream = page_stream(rng, f"{kind}", doc_no, p, pages, color, date)
        objs[n] = b"<< /Length %d >>\nstream\n" % len(stream) + stream + b"\nendstream"
        objs[n + 1] = (f"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents {n} 0 R "
                       f"/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> >>").encode()
        kids.append(n + 1); n += 2
    objs[2] = f"<< /Type /Pages /Kids [{' '.join(f'{k} 0 R' for k in kids)}] /Count {pages} >>".encode()
    data, offsets = b"%PDF-1.4\n%\xe2\xe3\xcf\xd3\n", {}
    for i in sorted(objs):
        offsets[i] = len(data)
        data += b"%d 0 obj\n" % i + objs[i] + b"\nendobj\n"
    xref = len(data)
    size = max(objs) + 1
    data += b"xref\n0 %d\n0000000000 65535 f \n" % size
    data += b"".join(b"%010d 00000 n \n" % offsets[i] for i in range(1, size))
    data += b"trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n" % (size, xref)
    open(path, 'wb').write(data)
    return kind, pages

for i in range(1, 21):
    names = ['Rechnung_10442', 'Lieferschein_7781', 'SCAN0001', 'Angebot_2026-118', 'Protokoll Teambesprechung', 'SCAN0002',
             'Vertrag Wartung', 'Gutschrift_552', 'Mahnung_Kunde_3', 'Wartungsbericht Heizung', 'Stundenzettel KW44', '20261029-081512',
             'Rechnung_10443', 'Lieferschein_7782', 'SCAN0003', 'Auftragsbestätigung 2026-77', 'Protokoll Gesellschafter', '20261030-073001',
             'Vertrag Leasing', 'Rechnung_10444']
    kind, pages = build(os.path.join(out, names[i - 1] + '.pdf'), i)
    print(f"{i:02d} {kind:22s} {pages} Seiten")
