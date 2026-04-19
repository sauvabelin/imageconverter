<?php

declare(strict_types=1);

namespace OCA\ImageConverter\Listener;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\ImageConverter\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * @implements IEventListener<LoadAdditionalScriptsEvent>
 */
final class LoadAdditionalListener implements IEventListener {
	public function handle(Event $event): void {
		if (!$event instanceof LoadAdditionalScriptsEvent) {
			return;
		}
		Util::addScript(Application::APP_ID, 'imageconverter-main', 'files');
	}
}
