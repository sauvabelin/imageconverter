# Image Converter & Compressor for Nextcloud

Right-click images in the Files app and convert them to JPEG. By default the plugin targets **~1 MB** per output file by resizing and tuning JPEG quality; an **"options"** menu lets you set custom dimensions and quality instead.

## Features

- Converts JPEG, PNG, WebP, GIF, BMP, TIFF, HEIC/HEIF, and AVIF sources to JPEG.
- Preset mode targets ~1 MB using a two-stage resize → quality-tune pipeline.
- Custom mode: pick your own max long edge and JPEG quality.
- Optional: move original to trash after successful conversion.
- Batch-safe: bounded concurrency (4 in-flight requests) keeps the server responsive.

## Requirements

- Nextcloud 32–34.
- ImageMagick with delegates for the formats you care about (HEIC, WebP, AVIF as needed).
- PHP Imagick extension enabled.

See [this tutorial](https://medium.com/@eplt/5-minutes-to-install-imagemagick-with-heic-support-on-ubuntu-18-04-digitalocean-fe2d09dcef1) for installing ImageMagick with HEIC support on Ubuntu. The official [Docker Nextcloud image](https://github.com/nextcloud/docker) already ships the Imagick extension with HEIC support.

## Development

```bash
composer install
npm ci
npm run build
composer test:unit
npm run lint
```

Bug reports: <https://github.com/major-mayer/imageConverter/issues>

License: AGPL-3.0-or-later.
