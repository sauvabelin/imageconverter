<?php

declare(strict_types=1);

namespace OCA\ImageConverter\Controller;

use InvalidArgumentException;
use OCA\ImageConverter\Exception\UnsupportedFormatException;
use OCA\ImageConverter\Service\ConversionPlan;
use OCA\ImageConverter\Service\ImageConverter;
use OCA\ImageConverter\Service\SizeTargeter;
use OCA\ImageConverter\Storage\ConvertStorage;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use Throwable;

final class ConvertController extends Controller {
	private const DEFAULT_TARGET_BYTES = 1_048_576;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly ConvertStorage $storage,
		private readonly SizeTargeter $sizeTargeter,
		private readonly ImageConverter $imageConverter,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function convertImage(
		int $id,
		string $filename,
		string $mode,
		?int $targetBytes = null,
		?int $maxLongEdge = null,
		?int $quality = null,
		bool $deleteOriginal = true,
	): JSONResponse {
		try {
			$blob = $this->storage->getFileContentById($id);
			$bytesIn = strlen($blob);

			[$w, $h] = $this->probeDimensions($blob);

			$plan = match ($mode) {
				'preset' => $this->sizeTargeter->planPreset(
					$w,
					$h,
					$targetBytes ?? self::DEFAULT_TARGET_BYTES,
				),
				'custom' => $this->requireCustom($maxLongEdge, $quality, $targetBytes),
				default => throw new InvalidArgumentException('mode must be preset or custom'),
			};

			$result = $this->imageConverter->convert($blob, $plan);

			$newBasename = $this->jpegBasename($filename);
			$this->storage->saveNewImage($id, $newBasename, $result->blob);

			$trashed = false;
			if ($deleteOriginal) {
				$trashed = $this->storage->trashOriginal($id);
			}

			return new JSONResponse([
				'result' => 'converted',
				'newFilename' => $newBasename,
				'bytesIn' => $bytesIn,
				'bytesOut' => $result->bytesOut,
				'widthOut' => $result->widthOut,
				'heightOut' => $result->heightOut,
				'qualityUsed' => $result->qualityUsed,
				'originalTrashed' => $trashed,
			]);
		} catch (UnsupportedFormatException $e) {
			return new JSONResponse(
				['error' => 'Unsupported format', 'details' => $e->getMessage()],
				Http::STATUS_BAD_REQUEST,
			);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(
				['error' => 'Bad request', 'details' => $e->getMessage()],
				Http::STATUS_BAD_REQUEST,
			);
		} catch (Throwable $e) {
			$this->logger->error('Image conversion failed', ['exception' => $e]);
			return new JSONResponse(
				['error' => 'Conversion failed', 'details' => $e->getMessage()],
				Http::STATUS_INTERNAL_SERVER_ERROR,
			);
		}
	}

	private function requireCustom(?int $maxLongEdge, ?int $quality, ?int $targetBytes): ConversionPlan {
		if ($maxLongEdge === null || $quality === null) {
			throw new InvalidArgumentException('custom mode requires maxLongEdge and quality');
		}
		return $this->sizeTargeter->planCustom(
			$maxLongEdge,
			$quality,
			$targetBytes ?? self::DEFAULT_TARGET_BYTES,
		);
	}

	/**
	 * @return array{int, int}
	 *
	 * getimagesizefromstring() doesn't recognize HEIC/HEIF/AVIF — for those
	 * formats it returns false and we'd end up with [0, 0], which trips
	 * SizeTargeter's positivity check. Fall back to Imagick (which we already
	 * depend on for the actual conversion) when GD-style probing fails.
	 */
	private function probeDimensions(string $blob): array {
		$info = @getimagesizefromstring($blob);
		if ($info !== false) {
			return [(int)$info[0], (int)$info[1]];
		}
		try {
			$probe = new \Imagick();
			try {
				$probe->pingImageBlob($blob);
				return [(int)$probe->getImageWidth(), (int)$probe->getImageHeight()];
			} finally {
				$probe->clear();
			}
		} catch (\Throwable) {
			return [0, 0]; // ImageConverter will reject as unsupported
		}
	}

	private function jpegBasename(string $filename): string {
		$info = pathinfo($filename);
		$stem = isset($info['filename']) && $info['filename'] !== '' ? $info['filename'] : 'converted';
		// Avoid double-tagging if the user converts an already-resized file.
		if (!str_ends_with($stem, '_resized')) {
			$stem .= '_resized';
		}
		return $stem . '.jpg';
	}
}
