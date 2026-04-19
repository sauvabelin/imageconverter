<?php

declare(strict_types=1);

namespace OCA\ImageConverter\AppInfo;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\ImageConverter\Listener\LoadAdditionalListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

final class Application extends App implements IBootstrap {
	public const APP_ID = 'imageconverter';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		// SizeTargeter, ImageConverter, and ConvertStorage are autowired from
		// their type-hinted constructor dependencies (IRootFolder, IUserSession,
		// LoggerInterface). No manual factory needed.
		$context->registerEventListener(
			LoadAdditionalScriptsEvent::class,
			LoadAdditionalListener::class,
		);
	}

	public function boot(IBootContext $context): void {
	}
}
