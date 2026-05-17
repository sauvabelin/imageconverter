<?php

declare(strict_types=1);

namespace OCA\ImageConverter\Service;

final class ConversionPlan {
	public function __construct(
		public readonly ?int $maxLongEdge,
		public readonly int $initialQuality,
		public readonly ?int $fallbackQuality,
		public readonly int $targetBytes,
	) {
	}
}
