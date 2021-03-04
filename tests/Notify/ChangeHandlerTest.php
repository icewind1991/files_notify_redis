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

use OCA\FilesNotifyRedis\Change\Change;
use OCA\FilesNotifyRedis\Change\RenameChange;
use OCA\FilesNotifyRedis\Notify\ChangeHandler;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Notify\IChange;
use OCP\IUserManager;
use Test\TestCase;
use Test\Traits\UserTrait;

/**
 * @group DB
 */
class ChangeHandlerTest extends TestCase {
	use UserTrait;

	/** @var IUserManager */
	private $userManager;
	/** @var IMountProviderCollection */
	private $mountProviderCollection;

	protected function setUp(): void {
		parent::setUp();

		$this->userManager = \OC::$server->query(IUserManager::class);
		$this->mountProviderCollection = \OC::$server->query(IMountProviderCollection::class);

		$this->createUser("user1", "");
	}

	protected function tearDown(): void {
		$homeMount = $this->mountProviderCollection->getHomeMountForUser($this->userManager->get("user1"));
		$storage = $homeMount->getStorage();
		$storage->rmdir('files');
		$storage->getCache()->clear();

		parent::tearDown();
	}


	public function testWriteChangeNoMeta() {
		$handler = new ChangeHandler($this->userManager, $this->mountProviderCollection);
		$change = new Change(IChange::MODIFIED, "user1/files/path");

		$homeMount = $this->mountProviderCollection->getHomeMountForUser($this->userManager->get("user1"));
		$storage = $homeMount->getStorage();
		$cache = $storage->getCache();
		$storage->mkdir('');
		$storage->mkdir('files');
		$storage->getScanner()->scan('');
		$this->assertTrue($cache->inCache('files'));
		$storage->file_put_contents('files/path', 'asd');
		$this->assertFalse($cache->inCache('files/path'));

		$handler->applyChange($change);

		$this->assertTrue($cache->inCache('files/path'));
	}

	public function testWriteChangeDoesntExist() {
		$handler = new ChangeHandler($this->userManager, $this->mountProviderCollection);
		$change = new Change(IChange::MODIFIED, "user1/files/path");

		$homeMount = $this->mountProviderCollection->getHomeMountForUser($this->userManager->get("user1"));
		$storage = $homeMount->getStorage();
		$cache = $storage->getCache();
		$storage->mkdir('');
		$storage->mkdir('files');
		$storage->getScanner()->scan('');
		$this->assertTrue($cache->inCache('files'));
		$this->assertFalse($cache->inCache('files/path'));

		$handler->applyChange($change);

		$this->assertFalse($cache->inCache('files/path'));
	}

	public function testRenameChangeNoMeta() {
		$handler = new ChangeHandler($this->userManager, $this->mountProviderCollection);
		$change = new RenameChange(IChange::RENAMED, "user1/files/path", "user1/files/target");

		$homeMount = $this->mountProviderCollection->getHomeMountForUser($this->userManager->get("user1"));
		$storage = $homeMount->getStorage();
		$cache = $storage->getCache();
		$storage->mkdir('');
		$storage->mkdir('files');
		$storage->file_put_contents('files/path', 'asd');
		$storage->getScanner()->scan('');
		$this->assertTrue($cache->inCache('files/path'));
		$this->assertFalse($cache->inCache('files/target'));

		$handler->applyChange($change);

		$this->assertFalse($cache->inCache('files/path'));
		$this->assertTrue($cache->inCache('files/target'));
	}

	public function testRenameChangeDoesntExist() {
		$handler = new ChangeHandler($this->userManager, $this->mountProviderCollection);
		$change = new RenameChange(IChange::RENAMED, "user1/files/path", "user1/files/target");

		$homeMount = $this->mountProviderCollection->getHomeMountForUser($this->userManager->get("user1"));
		$storage = $homeMount->getStorage();
		$cache = $storage->getCache();
		$storage->mkdir('');
		$storage->mkdir('files');
		$storage->getScanner()->scan('');
		$this->assertFalse($cache->inCache('files/path'));
		$this->assertFalse($cache->inCache('files/target'));

		$handler->applyChange($change);

		$this->assertFalse($cache->inCache('files/path'));
		$this->assertFalse($cache->inCache('files/target'));
	}

	public function testDeleteChangeNoMeta() {
		$handler = new ChangeHandler($this->userManager, $this->mountProviderCollection);
		$change = new Change(IChange::REMOVED, "user1/files/path");

		$homeMount = $this->mountProviderCollection->getHomeMountForUser($this->userManager->get("user1"));
		$storage = $homeMount->getStorage();
		$cache = $storage->getCache();
		$storage->mkdir('');
		$storage->mkdir('files');
		$storage->file_put_contents('files/path', 'asd');
		$storage->getScanner()->scan('');
		$this->assertTrue($cache->inCache('files/path'));

		$handler->applyChange($change);

		$this->assertFalse($cache->inCache('files/path'));
	}

	public function testDeleteChangeDoesntExist() {
		$handler = new ChangeHandler($this->userManager, $this->mountProviderCollection);
		$change = new Change(IChange::REMOVED, "user1/files/path");

		$homeMount = $this->mountProviderCollection->getHomeMountForUser($this->userManager->get("user1"));
		$storage = $homeMount->getStorage();
		$cache = $storage->getCache();
		$storage->mkdir('');
		$storage->mkdir('files');
		$storage->getScanner()->scan('');
		$this->assertFalse($cache->inCache('files/path'));

		$handler->applyChange($change);

		$this->assertFalse($cache->inCache('files/path'));
	}
}
