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

use OCA\FilesNotifyRedis\Change\RenameChange;
use OCA\FilesNotifyRedis\Change\Change;
use OCP\Files\Notify\IChange;
use OCP\Files\Notify\INotifyHandler;

class NotifyHandler implements INotifyHandler {
	/** @var string */
	private $basePath;

	/** @var \Redis */
	private $redis;

	/** @var string */
	private $list;

	/** @var string */
	private $format;

	/** @var string|false */
	private $formatRegex;

	/** @var callable */
	private $debugCallback;

	/**
	 * @param string $basePath
	 * @param \Redis $redis
	 * @param string $list
	 * @param string $format
	 * @param callable $debugCallback
	 */
	public function __construct($basePath, \Redis $redis, $list = 'notify', $format = '/$user/files/$path', callable $debugCallback) {
		$this->basePath = rtrim($basePath, '/');
		$this->redis = $redis;
		$this->list = $list;
		$this->format = $format;
		$this->formatRegex = '|' . str_replace(
				['\$user', '\$path'],
				['(?P<user>[^/]+)', '(?P<path>.*)'],
				preg_quote(ltrim($format, '/'), '|')
			) . '|';
		$this->debugCallback = $debugCallback;
	}

	/**
	 * @return Change[]
	 */
	public function getChanges() {
		$changes = [];
		while ($change = $this->getChange()) {
			$changes[] = $change;
		}
		return $changes;
	}

	private function getChange() {
		$event = $this->redis->rPop($this->list);
		if ($event) {
			$decodedEvent = $this->decodeEvent($event);
			if ($decodedEvent->getPath() === null || ($decodedEvent instanceof RenameChange && $decodedEvent->getTargetPath() === null)) {
				return false;
			} else {
				return $decodedEvent;
			}
		} else {
			return false;
		}
	}

	/**
	 * @param $path
	 * @return string
	 */
	private function getRelativePath($path) {
		if (substr($path, 0, strlen($this->basePath) + 1) === $this->basePath . '/') {
			$relativePath = substr($path, strlen($this->basePath) + 1);
			preg_match($this->formatRegex, $relativePath, $matches);
			if ($matches) {
				return "${matches['user']}/files/${matches['path']}";
			} else {
				$format = $this->format;
				($this->debugCallback)("path ($path) doesn't match format ($format)");
				return null;
			}
		} else {
			$basePath = $this->basePath;
			($this->debugCallback)("path ($path) outside base path ($basePath)");
			return null;
		}
	}

	private function decodeEvent($string) {
		$json = json_decode($string, true);
		if (is_array($json)) {
			$type = $json['event'];
			$path = isset($json['from']) ? $json['from'] : $json['path'];
			$target = isset($json['to']) ? $json['to'] : '';
			$time = isset($json['time']) ? \DateTime::createFromFormat(DATE_ATOM, $json['time']) : null;
			$size = isset($json['size']) ? (int)$json['size'] : null;
		} else {
			$parts = explode('|', $string);
			$type = $parts[0];
			$path = $parts[1];
			$target = isset($parts[2]) ? $parts[2] : '';
			$time = null;
			$size = null;
		}

		switch ($type) {
			case 'write':
			case 'modify':
				return new Change(IChange::MODIFIED, $this->getRelativePath($path), $time, $size);
			case 'remove':
			case 'delete':
				return new Change(IChange::REMOVED, $this->getRelativePath($path), $time, $size);
			case 'rename':
			case 'move':
				return new RenameChange(IChange::RENAMED, $this->getRelativePath($path), $this->getRelativePath($target), $time);
		}
		throw new \Exception('Invalid event type ' . $type);
	}

	public function listen(callable $callback) {
		$active = true;

		$stop = function () use (&$active) {
			$active = false;
		};

		if (function_exists('pcntl_signal')) {
			pcntl_signal(SIGTERM, $stop);
			pcntl_signal(SIGINT, $stop);
		}

		while ($active) {
			$change = $this->getChange();
			if (!$change) {
				// sleep while listening for stop signal
				for ($i = 0; ($i < 10 && $active); $i++) {
					if (function_exists('pcntl_signal_dispatch')) {
						pcntl_signal_dispatch();
					}
					usleep(100 * 1000);
				}
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
