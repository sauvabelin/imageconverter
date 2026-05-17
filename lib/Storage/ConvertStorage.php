<?php

declare(strict_types=1);

namespace OCA\ImageConverter\Storage;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final class ConvertStorage {
	public function __construct(
		private readonly IRootFolder $rootFolder,
		private readonly IUserSession $userSession,
		private readonly LoggerInterface $logger,
	) {
	}

	public function getFileById(int $id): File {
		return $this->resolveNode($id);
	}

	public function getFileContentById(int $id): string {
		$content = $this->getFileById($id)->getContent();
		if ($content === '') {
			throw new RuntimeException('File content is empty: ' . $id);
		}
		return $content;
	}

	public function saveNewImage(int $originalFileId, string $newBasename, string $content): int {
		if ($content === '') {
			throw new RuntimeException('Refusing to write empty converted image');
		}

		$parent = $this->resolveNode($originalFileId)->getParent();
		if (!$parent instanceof Folder) {
			throw new RuntimeException('Parent is not a folder');
		}
		if (!$parent->isCreatable()) {
			throw new NotPermittedException('Parent folder is not writable');
		}

		$finalName = $parent->nodeExists($newBasename)
			? $this->uniqueName($parent, $newBasename)
			: $newBasename;

		$newFile = $parent->newFile($finalName);
		$newFile->putContent($content);
		return $newFile->getId();
	}

	/**
	 * Best-effort move to trash. Logs and returns false on failure but does not throw,
	 * because the conversion has already succeeded and the user's new file is saved.
	 */
	public function trashOriginal(int $id): bool {
		try {
			$this->resolveNode($id)->delete();
			return true;
		} catch (Throwable $e) {
			$this->logger->warning('Failed to move original to trash: ' . $e->getMessage(), [
				'id' => $id,
				'exception' => $e,
			]);
			return false;
		}
	}

	private function resolveNode(int $id): File {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new RuntimeException('No authenticated user');
		}
		$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		$nodes = $userFolder->getById($id);
		if ($nodes === []) {
			throw new NotFoundException('File not found: ' . $id);
		}
		$node = $nodes[0];
		if (!$node instanceof File) {
			throw new RuntimeException('Node is not a file: ' . $id);
		}
		return $node;
	}

	private function uniqueName(Folder $parent, string $candidate): string {
		$info = pathinfo($candidate);
		$stem = $info['filename'];
		$ext = isset($info['extension']) ? '.' . $info['extension'] : '';

		for ($i = 1; $i < 100; $i++) {
			$name = sprintf('%s (%d)%s', $stem, $i, $ext);
			if (!$parent->nodeExists($name)) {
				return $name;
			}
		}
		throw new RuntimeException('Could not find a free filename for ' . $candidate);
	}
}
