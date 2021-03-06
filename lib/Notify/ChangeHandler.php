<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\FilesNotifyRedis\Notify;

use OC\User\NoUserException;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Notify\IChange;
use OCP\Files\Notify\IRenameChange;
use OCP\Files\Storage\INotifyStorage;
use OCP\IUser;
use OCP\IUserManager;

class ChangeHandler {
	private $userManager;
	private $mountProviderCollection;

	public function __construct(IUserManager $userManager, IMountProviderCollection $mountProviderCollection) {
		$this->userManager = $userManager;
		$this->mountProviderCollection = $mountProviderCollection;
	}

	/**
	 * Update the filecache according to the incoming change
	 *
	 * @param IChange $change
	 * @throws NoUserException
	 */
	public function applyChange(IChange $change) {
		[$userId, $path] = explode('/', $change->getPath(), 2);
		$user = $this->userManager->get($userId);
		if (!$user) {
			throw new NoUserException("Unknown user $userId");
		} else {
			$this->handleUpdate($change, $user, $path);
		}
	}

	private function handleUpdate(IChange $change, IUser $user, $path) {
		$mount = $this->mountProviderCollection->getHomeMountForUser($user);
		$updater = $mount->getStorage()->getUpdater();

		switch ($change->getType()) {
			case INotifyStorage::NOTIFY_ADDED:
			case INotifyStorage::NOTIFY_MODIFIED:
				$updater->update($path);
				break;
			case INotifyStorage::NOTIFY_REMOVED:
				$updater->remove($path);
				break;
			case INotifyStorage::NOTIFY_RENAMED:
				/** @var IRenameChange $change */
				[, $targetPath] = explode('/', $change->getTargetPath(), 2);
				$updater->renameFromStorage($mount->getStorage(), $path, $targetPath);
				break;
		}
	}
}
