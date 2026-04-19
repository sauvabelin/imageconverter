# Test Fixtures

The photo fixtures used by `ImageConverterTest` are not committed to the repo. Add them before running the unit tests, or the relevant tests will be skipped.

Required files (CC0 — use Unsplash / Pexels):

- `photo-8mp.jpg` — 3840×2160, quality 95. Typical phone photo.
- `photo-2mp.png` — 1600×1200. The "already small" fixture.
- `photo-hd.heic` — 1280×720 HEIC. Optional: if absent, the HEIC test is skipped.

Create them with ImageMagick:

```
magick source.jpg -resize 3840x2160 -quality 95 photo-8mp.jpg
magick source.jpg -resize 1600x1200 photo-2mp.png
```

`not-an-image.svg` is already committed (used for the "reject unsupported format" test).
