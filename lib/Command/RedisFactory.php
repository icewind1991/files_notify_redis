<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Robin Appelman <robin@icewind.nl>
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

namespace OCA\FilesNotifyRedis\Command;

use Redis;

class RedisFactory {
	private \OC\RedisFactory $factory;

	public function __construct(\OC\RedisFactory $factory) {
		$this->factory = $factory;
	}

	/**
	 * @param ?string $host
	 * @param ?string $port
	 * @param ?string $password
	 * @return Redis
	 */
	public function getRedis(?string $host, ?string $port, ?string $password): Redis {
		if ($host) {
			if (!$port) {
				$port = 6379;
			}
			$instance = new Redis();

			$instance->connect($host, $port, 0.0);
			if ($password) {
				$instance->auth($password);
			}
			return $instance;
		} else {
			return $this->factory->getInstance();
		}
	}
}
