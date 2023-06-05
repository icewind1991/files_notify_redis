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

namespace OCA\FilesNotifyRedis\Tests\Notify;

use OC\Files\Storage\Temporary;
use OCA\FilesNotifyRedis\Change\Change;
use OCA\FilesNotifyRedis\Change\RenameChange;
use OCA\FilesNotifyRedis\Notify\ChangeHandler;
use OCP\Files\Mount\IMountManager;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\Notify\IChange;
use OCP\Files\Storage\IStorage;
use OCP\IUser;
use OCP\IUserManager;
use Test\TestCase;

/**
 * @group DB
 */
class ChangeHandlerTest extends TestCase {
	private IUserManager $userManager;
	private IMountManager $mountManager;
	private IStorage $storage;

	protected function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->mountManager = $this->createMock(IMountManager::class);
		$this->storage = new Temporary();

		$this->userManager->method('get')
			->willReturnCallback(function (string $userId) {
				$user = $this->createMock(IUser::class);
				$user->method('getUID')
					->willReturn($userId);
				return $user;
			});
		$mount = $this->createMock(IMountPoint::class);
		$mount->method('getStorage')
			->willReturn($this->storage);
		$mount->method('getInternalPath')
			->willReturnCallback(function (string $path) {
				return substr($path, strlen("/user1/"));
			});
		$this->mountManager->method('find')
			->willReturn($mount);
	}

	public function testWriteChangeNoMeta() {
		$handler = new ChangeHandler($this->userManager, $this->mountManager);
		$change = new Change(IChange::MODIFIED, "user1/files/path");

		$cache = $this->storage->getCache();
		$this->storage->mkdir('');
		$this->storage->mkdir('files');
		$this->storage->getScanner()->scan('');
		$this->assertTrue($cache->inCache('files'));
		$this->storage->file_put_contents('files/path', 'asd');
		$this->assertFalse($cache->inCache('files/path'));

		$handler->applyChange($change);

		$this->assertTrue($cache->inCache('files/path'));
	}

	public function testWriteChangeDoesntExist() {
		$handler = new ChangeHandler($this->userManager, $this->mountManager);
		$change = new Change(IChange::MODIFIED, "user1/files/path");

		$cache = $this->storage->getCache();
		$this->storage->mkdir('');
		$this->storage->mkdir('files');
		$this->storage->getScanner()->scan('');
		$this->assertTrue($cache->inCache('files'));
		$this->assertFalse($cache->inCache('files/path'));

		$handler->applyChange($change);

		$this->assertFalse($cache->inCache('files/path'));
	}

	public function testRenameChangeNoMeta() {
		$handler = new ChangeHandler($this->userManager, $this->mountManager);
		$change = new RenameChange(IChange::RENAMED, "user1/files/path", "user1/files/target");

		$cache = $this->storage->getCache();
		$this->storage->mkdir('');
		$this->storage->mkdir('files');
		$this->storage->file_put_contents('files/path', 'asd');
		$this->storage->getScanner()->scan('');
		$this->assertTrue($cache->inCache('files/path'));
		$this->assertFalse($cache->inCache('files/target'));

		$handler->applyChange($change);

		$this->assertFalse($cache->inCache('files/path'));
		$this->assertTrue($cache->inCache('files/target'));
	}

	public function testRenameChangeDoesntExist() {
		$handler = new ChangeHandler($this->userManager, $this->mountManager);
		$change = new RenameChange(IChange::RENAMED, "user1/files/path", "user1/files/target");

		$cache = $this->storage->getCache();
		$this->storage->mkdir('');
		$this->storage->mkdir('files');
		$this->storage->getScanner()->scan('');
		$this->assertFalse($cache->inCache('files/path'));
		$this->assertFalse($cache->inCache('files/target'));

		$handler->applyChange($change);

		$this->assertFalse($cache->inCache('files/path'));
		$this->assertFalse($cache->inCache('files/target'));
	}

	public function testDeleteChangeNoMeta() {
		$handler = new ChangeHandler($this->userManager, $this->mountManager);
		$change = new Change(IChange::REMOVED, "user1/files/path");

		$cache = $this->storage->getCache();
		$this->storage->mkdir('');
		$this->storage->mkdir('files');
		$this->storage->file_put_contents('files/path', 'asd');
		$this->storage->getScanner()->scan('');
		$this->assertTrue($cache->inCache('files/path'));

		$handler->applyChange($change);

		$this->assertFalse($cache->inCache('files/path'));
	}

	public function testDeleteChangeDoesntExist() {
		$handler = new ChangeHandler($this->userManager, $this->mountManager);
		$change = new Change(IChange::REMOVED, "user1/files/path");

		$cache = $this->storage->getCache();
		$this->storage->mkdir('');
		$this->storage->mkdir('files');
		$this->storage->getScanner()->scan('');
		$this->assertFalse($cache->inCache('files/path'));

		$handler->applyChange($change);

		$this->assertFalse($cache->inCache('files/path'));
	}
}
