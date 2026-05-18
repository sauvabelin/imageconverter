<?php

declare(strict_types=1);

namespace OCA\ImageConverter\Service;

use InvalidArgumentException;

final class SizeTargeter {
	private const INITIAL_QUALITY = 90;
	private const FALLBACK_QUALITY = 85;
	private const TARGET_MEGAPIXELS = 12;

	public function planPreset(int $width, int $height, int $targetBytes): ConversionPlan {
		if ($width <= 0 || $height <= 0) {
			throw new InvalidArgumentException('width and height must be positive');
		}
		if ($targetBytes <= 0) {
			throw new InvalidArgumentException('targetBytes must be positive');
		}

		$sourceMP = ($width * $height) / 1_000_000;
		$longEdge = max($width, $height);

		// Scale toward 12 MP, preserving aspect ratio. Sources already at or
		// below 12 MP are kept untouched (maxLongEdge=null tells the converter
		// to skip the resize stage).
		$maxLongEdge = null;
		if ($sourceMP > self::TARGET_MEGAPIXELS) {
			$scale = sqrt(self::TARGET_MEGAPIXELS / $sourceMP);
			$maxLongEdge = (int)round($longEdge * $scale);
		}

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
