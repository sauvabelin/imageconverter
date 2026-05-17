<?php

declare(strict_types=1);

namespace OCA\ImageConverter\Tests\Unit\Service;

use Imagick;
use ImagickException;
use OCA\ImageConverter\Exception\UnsupportedFormatException;
use OCA\ImageConverter\Service\ConversionPlan;
use OCA\ImageConverter\Service\ImageConverter;
use PHPUnit\Framework\TestCase;

final class ImageConverterTest extends TestCase {
	private ImageConverter $converter;
	private string $fixturesDir;

	protected function setUp(): void {
		if (!extension_loaded('imagick')) {
			self::markTestSkipped('ext-imagick required');
		}
		$this->converter = new ImageConverter();
		$this->fixturesDir = __DIR__ . '/../../fixtures';
	}

	public function testPresetConvertsLargeJpegBelowTargetSize(): void {
		$blob = $this->readFixture('photo-8mp.jpg');
		$plan = new ConversionPlan(
			maxLongEdge: 2048,
			initialQuality: 85,
			fallbackQuality: 78,
			targetBytes: 1_048_576,
		);

		$result = $this->converter->convert($blob, $plan);

		self::assertLessThanOrEqual((int)round(1_048_576 * 1.1), $result->bytesOut);
		self::assertLessThanOrEqual(2048, max($result->widthOut, $result->heightOut));
		self::assertContains($result->qualityUsed, [85, 78]);
	}

	public function testCustomModeRespectsExactParams(): void {
		$blob = $this->readFixture('photo-8mp.jpg');
		$plan = new ConversionPlan(
			maxLongEdge: 800,
			initialQuality: 50,
			fallbackQuality: null,
			targetBytes: 1_048_576,
		);

		$result = $this->converter->convert($blob, $plan);

		self::assertLessThanOrEqual(800, max($result->widthOut, $result->heightOut));
		self::assertSame(50, $result->qualityUsed);
	}

	public function testSmallPngSkipsResize(): void {
		$blob = $this->readFixture('photo-2mp.png');
		$plan = new ConversionPlan(
			maxLongEdge: null,
			initialQuality: 85,
			fallbackQuality: 78,
			targetBytes: 1_048_576,
		);

		$result = $this->converter->convert($blob, $plan);

		self::assertLessThanOrEqual(1_048_576, $result->bytesOut);
		self::assertSame(85, $result->qualityUsed);
	}

	public function testSvgInputIsRejected(): void {
		$blob = $this->readFixture('not-an-image.svg');
		$plan = new ConversionPlan(
			maxLongEdge: 2048,
			initialQuality: 85,
			fallbackQuality: 78,
			targetBytes: 1_048_576,
		);

		$this->expectException(UnsupportedFormatException::class);
		$this->converter->convert($blob, $plan);
	}

	public function testCorruptBlobThrows(): void {
		$plan = new ConversionPlan(
			maxLongEdge: null,
			initialQuality: 85,
			fallbackQuality: 78,
			targetBytes: 1_048_576,
		);

		$this->expectException(ImagickException::class);
		$this->converter->convert('not actually image bytes', $plan);
	}

	public function testHeicConversionProducesJpeg(): void {
		$heicPath = $this->fixturesDir . '/photo-hd.heic';
		if (!is_readable($heicPath)) {
			self::markTestSkipped('photo-hd.heic fixture missing');
		}
		try {
			$probe = new Imagick();
			$probe->readImageBlob((string)file_get_contents($heicPath));
			$probe->clear();
		} catch (ImagickException) {
			self::markTestSkipped('Imagick build lacks HEIC delegate');
		}

		$plan = new ConversionPlan(
			maxLongEdge: null,
			initialQuality: 85,
			fallbackQuality: 78,
			targetBytes: 1_048_576,
		);

		$result = $this->converter->convert((string)file_get_contents($heicPath), $plan);

		// JPEG SOI marker
		self::assertSame("\xFF\xD8", substr($result->blob, 0, 2));
	}

	private function readFixture(string $name): string {
		$path = $this->fixturesDir . '/' . $name;
		if (!is_readable($path)) {
			self::markTestSkipped("Fixture missing: $name — add a CC0 image at tests/fixtures/$name");
		}
		$contents = file_get_contents($path);
		self::assertNotFalse($contents, "Could not read fixture: $path");
		return $contents;
	}
}
