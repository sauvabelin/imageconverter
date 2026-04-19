<?php

declare(strict_types=1);

namespace OCA\ImageConverter\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\ImageConverter\Service\SizeTargeter;
use PHPUnit\Framework\TestCase;

final class SizeTargeterTest extends TestCase {
	private SizeTargeter $targeter;

	protected function setUp(): void {
		$this->targeter = new SizeTargeter();
	}

	/**
	 * @return array<string, array{int, int, ?int}>
	 */
	public static function presetBucketProvider(): array {
		return [
			// > 12 MP → 2560
			'24 MP (6000x4000)' => [6000, 4000, 2560],
			'just above 12 MP (4500x2800)' => [4500, 2800, 2560],
			// 6–12 MP → 2048
			'12 MP exactly (4000x3000)' => [4000, 3000, 2048],
			'8 MP (3840x2160)' => [3840, 2160, 2048],
			'just above 6 MP (3000x2100)' => [3000, 2100, 2048],
			// 2–6 MP → 1600
			'6 MP exactly (3000x2000)' => [3000, 2000, 1600],
			'3 MP (2048x1536)' => [2048, 1536, 1600],
			'just above 2 MP (1800x1200)' => [1800, 1200, 1600],
			// ≤ 2 MP → null (no resize)
			'2 MP exactly (2000x1000)' => [2000, 1000, null],
			'tiny (640x480)' => [640, 480, null],
		];
	}

	/**
	 * @dataProvider presetBucketProvider
	 */
	public function testPresetBucketMapping(int $width, int $height, ?int $expectedMaxEdge): void {
		$plan = $this->targeter->planPreset($width, $height, 1_048_576);
		self::assertSame($expectedMaxEdge, $plan->maxLongEdge);
		self::assertSame(85, $plan->initialQuality);
		self::assertSame(78, $plan->fallbackQuality);
		self::assertSame(1_048_576, $plan->targetBytes);
	}

	public function testPresetSkipsResizeWhenSourceBelowBucketTarget(): void {
		// Source long edge 1500 px, in the "2-6 MP → 1600 px" bucket → no resize.
		$plan = $this->targeter->planPreset(1500, 1500, 1_048_576);
		self::assertNull($plan->maxLongEdge);
	}

	public function testCustomPlanPassesValuesThrough(): void {
		$plan = $this->targeter->planCustom(800, 60, 1_048_576);
		self::assertSame(800, $plan->maxLongEdge);
		self::assertSame(60, $plan->initialQuality);
		self::assertNull($plan->fallbackQuality);
		self::assertSame(1_048_576, $plan->targetBytes);
	}

	public function testCustomRejectsNonPositiveMaxEdge(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->targeter->planCustom(0, 80, 1_048_576);
	}

	public function testCustomRejectsQualityOutOfRange(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->targeter->planCustom(1024, 101, 1_048_576);
	}

	public function testPresetRejectsNonPositiveDimensions(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->targeter->planPreset(-1, 100, 1_048_576);
	}

	public function testPresetRejectsNonPositiveTarget(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->targeter->planPreset(1000, 1000, 0);
	}
}
