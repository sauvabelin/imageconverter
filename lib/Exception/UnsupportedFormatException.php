<?php

declare(strict_types=1);

namespace OCA\ImageConverter\Exception;

use RuntimeException;
use Throwable;

final class UnsupportedFormatException extends RuntimeException {
	public function __construct(string $mime, ?Throwable $previous = null) {
		parent::__construct(sprintf('%s is not a supported raster image format.', $mime), 0, $previous);
	}
}
