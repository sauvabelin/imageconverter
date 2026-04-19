<?php

declare(strict_types=1);

namespace OCA\ImageConverter\Service;

final class ConversionResult {
	public function __construct(
		public readonly string $blob,
		public readonly int $widthOut,
		public readonly int $heightOut,
		public readonly int $qualityUsed,
		public readonly int $bytesOut,
	) {
	}
}
