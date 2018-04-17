<?php
/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
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

namespace OCA\FilesNotifyRedis\Storage;

use OC\Files\Notify\Change;
use OC\Files\Notify\RenameChange;
use OCP\Files\Notify\IChange;
use OCP\Files\Notify\INotifyHandler;

class NotifyHandler implements INotifyHandler {
	/** @var string */
	private $basePath;

	/** @var \Redis */
	private $redis;

	/**
	 * @param string $basePath
	 * @param \Redis $redis
	 */
	public function __construct($basePath, \Redis $redis) {
		$this->basePath = rtrim($basePath, '/');
		$this->redis = $redis;
	}

	public function getChanges() {
		$changes = [];
		while ($change = $this->getChange()) {
			$changes[] = $change;
		}
		return $changes;
	}

	private function getChange() {
		$event = $this->redis->rPop('notify');
		return $event ? $this->decodeEvent($event) : false;
	}

	/**
	 * @param $path
	 * @return string
	 */
	private function getRelativePath($path) {
		if (substr($path, 0, strlen($this->basePath) + 1) === $this->basePath . '/') {
			return substr($path, strlen($this->basePath) + 1);
		} else {
			return null;
		}
	}

	private function decodeEvent($string) {
		list($type, $path1, $path2) = explode('|', $string);
		switch ($type) {
			case 'write':
				return new Change(IChange::MODIFIED, $this->getRelativePath($path1));
			case 'remove':
				return new Change(IChange::REMOVED, $this->getRelativePath($path1));
			case 'rename':
				return new RenameChange(IChange::RENAMED, $this->getRelativePath($path1), $this->getRelativePath($path2));
		}
		throw new \Error('Invalid event type ' . $type);
	}

	public function listen(callable $callback) {
		$active = true;

		$stop = function() use (&$active) {
			$active = false;
		};

		if (function_exists('pcntl_signal')) {
			pcntl_signal(SIGTERM, $stop);
			pcntl_signal(SIGINT, $stop);
		}

		while ($active) {
			if (function_exists('pcntl_signal_dispatch')) {
				pcntl_signal_dispatch();
			}
			$change = $this->getChange();
			if (!$change) {
				sleep(1);
			} else {
				if ($callback($change) === false) {
					$active = false;
				}
			}
		}
	}

	public function stop() {
		// noop
	}
}
