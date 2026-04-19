<?php

declare(strict_types=1);

namespace OCA\ImageConverter\Tests\Integration\Controller;

use OCP\App\IAppManager;
use OCP\AppFramework\App;
use Test\TestCase;

final class AppTest extends TestCase {
	private App $app;

	protected function setUp(): void {
		parent::setUp();
		$this->app = new App('imageconverter');
	}

	public function testAppInstalled(): void {
		$appManager = $this->app->getContainer()->get(IAppManager::class);
		self::assertTrue($appManager->isInstalled('imageconverter'));
	}
}
