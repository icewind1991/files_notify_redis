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

use DateTime;
use OCA\FilesNotifyRedis\Notify\NotifyHandler;
use OCP\Files\Notify\IChange;
use OCP\Files\Notify\IRenameChange;
use Redis;
use Test\TestCase;

class NotifyHandlerTest extends TestCase {
	private $list = [];
	/** @var Redis */
	private $redis;

	protected function setUp(): void {
		parent::setUp();

		$this->redis = $this->createMock(Redis::class);
		$this->redis->method('rPop')
			->willReturnCallback(function () {
				return array_pop($this->list);
			});
	}

	public function testGetChangeNoMeta() {
		$handler = new NotifyHandler('/base', $this->redis, '', '/$user/files/$path', function () {
		});
		$this->list[] = json_encode([
			"event" => "modify",
			"path"  => "/base/foo/files/the/path",
		]);
		$changes = $handler->getChanges();
		$this->assertCount(1, $changes);
		$this->assertEquals(IChange::MODIFIED, $changes[0]->getType());
		$this->assertEquals("foo/files/the/path", $changes[0]->getPath());
		$this->assertNull($changes[0]->getSize());
		$this->assertNull($changes[0]->getTime());

		$this->list[] = json_encode([
			"event" => "delete",
			"path"  => "/base/foo/files/the/path",
		]);
		$changes = $handler->getChanges();
		$this->assertCount(1, $changes);
		$this->assertEquals(IChange::REMOVED, $changes[0]->getType());
		$this->assertEquals("foo/files/the/path", $changes[0]->getPath());
		$this->assertNull($changes[0]->getSize());
		$this->assertNull($changes[0]->getTime());

		$this->list[] = json_encode([
			"event" => "move",
			"from"  => "/base/foo/files/the/path",
			"to"    => "/base/foo/files/the/target",
		]);
		$changes = $handler->getChanges();
		$this->assertCount(1, $changes);
		$this->assertInstanceOf(IRenameChange::class, $changes[0]);
		$this->assertEquals(IChange::RENAMED, $changes[0]->getType());
		$this->assertEquals("foo/files/the/path", $changes[0]->getPath());
		$this->assertEquals("foo/files/the/target", $changes[0]->getTargetPath());
		$this->assertNull($changes[0]->getTime());
	}

	public function testGetChangeMeta() {
		$handler = new NotifyHandler('/base', $this->redis, '', '/$user/files/$path', function () {
		});
		$this->list[] = json_encode([
			"event" => "modify",
			"path"  => "/base/foo/files/the/path",
			"time"  => "2019-05-13T10:58:35-0400",
			"size"  => 1024,
		]);
		$changes = $handler->getChanges();
		$this->assertCount(1, $changes);
		$this->assertEquals(IChange::MODIFIED, $changes[0]->getType());
		$this->assertEquals("foo/files/the/path", $changes[0]->getPath());
		$this->assertEquals(1024, $changes[0]->getSize());
		$this->assertEquals(DateTime::createFromFormat(DateTime::ATOM, "2019-05-13T10:58:35-0400"), $changes[0]->getTime());
	}

	public function testGetChangesMultiple() {
		$handler = new NotifyHandler('/base', $this->redis, '', '/$user/files/$path', function () {
		});
		$this->list[] = json_encode([
			"event" => "modify",
			"path"  => "/base/foo/files/the/path1",
		]);
		$this->list[] = json_encode([
			"event" => "modify",
			"path"  => "/base/foo/files/the/path2",
		]);
		$changes = $handler->getChanges();
		$this->assertCount(2, $changes);
		$this->assertEquals("foo/files/the/path2", $changes[0]->getPath());
		$this->assertEquals("foo/files/the/path1", $changes[1]->getPath());
	}

	public function pathFormatProvider() {
		return [
			['/base', '/$user/$path', '/base/foo/path', 'foo/files/path'],
			['', '/$user/$path', '/foo/path', 'foo/files/path'],
			['', '/prefix/$user/$path', '/prefix/foo/path', 'foo/files/path'],
			['/base', '/$user/$path', '/foo/path', null],
			['/base', '/$user/files/$path', '/base/foo/path', null],
			['', '/$user/$path', '/path', null],
			['', '/prefix/$user/$path', '/foo/path', null],
		];
	}

	/**
	 * @dataProvider pathFormatProvider
	 */
	public function testPathFormat(string $base, string $format, string $input, ?string $expected) {
		$handler = new NotifyHandler($base, $this->redis, '', $format, function () {
		});
		$this->list[] = json_encode([
			"event" => "modify",
			"path"  => $input,
		]);
		$changes = $handler->getChanges();
		if ($expected) {
			$this->assertCount(1, $changes);
			$this->assertEquals($expected, $changes[0]->getPath());
		} else {
			$this->assertCount(0, $changes);
		}
	}

	public function testGetChangeLegacy() {
		$handler = new NotifyHandler('/base', $this->redis, '', '/$user/files/$path', function () {
		});
		$this->list[] = "modify|/base/foo/files/the/path";
		$changes = $handler->getChanges();
		$this->assertCount(1, $changes);
		$this->assertEquals(IChange::MODIFIED, $changes[0]->getType());
		$this->assertEquals("foo/files/the/path", $changes[0]->getPath());
		$this->assertNull($changes[0]->getSize());
		$this->assertNull($changes[0]->getTime());

		$this->list[] = "delete|/base/foo/files/the/path";
		$changes = $handler->getChanges();
		$this->assertCount(1, $changes);
		$this->assertEquals(IChange::REMOVED, $changes[0]->getType());
		$this->assertEquals("foo/files/the/path", $changes[0]->getPath());
		$this->assertNull($changes[0]->getSize());
		$this->assertNull($changes[0]->getTime());

		$this->list[] = "move|/base/foo/files/the/path|/base/foo/files/the/target";
		$changes = $handler->getChanges();
		$this->assertCount(1, $changes);
		$this->assertInstanceOf(IRenameChange::class, $changes[0]);
		$this->assertEquals(IChange::RENAMED, $changes[0]->getType());
		$this->assertEquals("foo/files/the/path", $changes[0]->getPath());
		$this->assertEquals("foo/files/the/target", $changes[0]->getTargetPath());
		$this->assertNull($changes[0]->getTime());
	}
}
