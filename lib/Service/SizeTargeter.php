<?php

declare(strict_types=1);

namespace OCA\ImageConverter\Service;

use InvalidArgumentException;

final class SizeTargeter {
	private const INITIAL_QUALITY = 85;
	private const FALLBACK_QUALITY = 78;

	public function planPreset(int $width, int $height, int $targetBytes): ConversionPlan {
		if ($width <= 0 || $height <= 0) {
			throw new InvalidArgumentException('width and height must be positive');
		}
		if ($targetBytes <= 0) {
			throw new InvalidArgumentException('targetBytes must be positive');
		}

		$longEdge = max($width, $height);
		$megapixels = ($width * $height) / 1_000_000;

		$bucketLongEdge = match (true) {
			$megapixels > 12 => 2560,
			$megapixels > 6 => 2048,
			$megapixels > 2 => 1600,
			default => null,
		};

		// Skip resize if the source is already smaller than the bucket target.
		$maxLongEdge = ($bucketLongEdge !== null && $longEdge > $bucketLongEdge) ? $bucketLongEdge : null;

		return new ConversionPlan(
			maxLongEdge: $maxLongEdge,
			initialQuality: self::INITIAL_QUALITY,
			fallbackQuality: self::FALLBACK_QUALITY,
			targetBytes: $targetBytes,
		);
	}

	public function planCustom(int $maxLongEdge, int $quality, int $targetBytes): ConversionPlan {
		if ($maxLongEdge <= 0) {
			throw new InvalidArgumentException('maxLongEdge must be positive');
		}
		if ($quality < 1 || $quality > 100) {
			throw new InvalidArgumentException('quality must be in 1..100');
		}
		if ($targetBytes <= 0) {
			throw new InvalidArgumentException('targetBytes must be positive');
		}

		return new ConversionPlan(
			maxLongEdge: $maxLongEdge,
			initialQuality: $quality,
			fallbackQuality: null,
			targetBytes: $targetBytes,
		);
	}
}
