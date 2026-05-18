<?php

declare(strict_types=1);

namespace OCA\ImageConverter\Service;

use Imagick;
use ImagickException;
use OCA\ImageConverter\Exception\UnsupportedFormatException;

final class ImageConverter {
	private const SUPPORTED_MIMES = [
		'image/jpeg' => true,
		'image/pjpeg' => true,
		'image/png' => true,
		'image/webp' => true,
		'image/gif' => true,
		'image/bmp' => true,
		'image/x-ms-bmp' => true,
		'image/tiff' => true,
		'image/heic' => true,
		'image/heif' => true,
		'image/avif' => true,
	];

	/**
	 * @throws UnsupportedFormatException if the blob is not a supported raster image
	 * @throws ImagickException if Imagick fails to decode or encode
	 */
	public function convert(string $blob, ConversionPlan $plan): ConversionResult {
		$image = new Imagick();
		try {
			$image->readImageBlob($blob);
			$this->assertSupported($image);

			// Stage 0 — normalize.
			// Imagick::autoOrientImage() only exists with ImageMagick 7 + Imagick >=3.7;
			// PHP-Imagick 3.8 on Debian still uses the older Imagick::autoOrient() name.
			if (method_exists($image, 'autoOrientImage')) {
				$image->autoOrientImage();
			} else {
				$image->autoOrient();
			}
			$image->stripImage();

			// Stage 1 — resize.
			if ($plan->maxLongEdge !== null) {
				$longEdge = max($image->getImageWidth(), $image->getImageHeight());
				if ($longEdge > $plan->maxLongEdge) {
					if ($image->getImageWidth() >= $image->getImageHeight()) {
						$image->resizeImage($plan->maxLongEdge, 0, Imagick::FILTER_LANCZOS, 1);
					} else {
						$image->resizeImage(0, $plan->maxLongEdge, Imagick::FILTER_LANCZOS, 1);
					}
				}
			}

			// Stage 2 — quality tune.
			$image->setImageFormat('jpeg');
			$image->setInterlaceScheme(Imagick::INTERLACE_JPEG);
			$image->setSamplingFactors(['2x2', '1x1', '1x1']);

			$blobOut = $this->encode($image, $plan->initialQuality);
			$qualityUsed = $plan->initialQuality;

			if (
				$plan->fallbackQuality !== null
				&& strlen($blobOut) > (int)ceil($plan->targetBytes * 1.1)
			) {
				$blobOut = $this->encode($image, $plan->fallbackQuality);
				$qualityUsed = $plan->fallbackQuality;
			}

			return new ConversionResult(
				blob: $blobOut,
				widthOut: $image->getImageWidth(),
				heightOut: $image->getImageHeight(),
				qualityUsed: $qualityUsed,
				bytesOut: strlen($blobOut),
			);
		} finally {
			$image->clear();
		}
	}

	private function assertSupported(Imagick $image): void {
		$format = strtolower($image->getImageFormat());
		$mime = match ($format) {
			'jpeg', 'jpg', 'pjpeg' => 'image/jpeg',
			'png' => 'image/png',
			'webp' => 'image/webp',
			'gif' => 'image/gif',
			'bmp' => 'image/bmp',
			'tiff', 'tif' => 'image/tiff',
			'heic' => 'image/heic',
			'heif' => 'image/heif',
			'avif' => 'image/avif',
			default => 'image/' . $format,
		};
		if (!isset(self::SUPPORTED_MIMES[$mime])) {
			throw new UnsupportedFormatException($mime);
		}
	}

	private function encode(Imagick $image, int $quality): string {
		$image->setImageCompressionQuality($quality);
		return $image->getImageBlob();
	}
}
