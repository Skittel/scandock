# ScanDock logo

Design by the project owner. `mklogo.py` generates from it:

- `logo-scandock.svg` – full logo, the wave undulates very slowly (20 s cycle, SMIL)
- `logo-scandock-icon.svg` – square icon, animated
- `favicon.svg` – square icon, static (also rendered to `favicon.ico` and `apple-touch-icon.png`)

The wordmark ("Scan" navy, "Kai" blue gradient) is converted to outlines using
Liberation Sans Bold (metric-compatible with Arial, SIL Open Font License), so
it looks the same on every device.

```bash
python3 mklogo.py
for n in 16 32 48; do rsvg-convert -w $n -h $n favicon.svg -o f$n.png; done
convert f16.png f32.png f48.png favicon.ico
rsvg-convert -w 180 -h 180 -b white favicon.svg -o apple-touch-icon.png
```
