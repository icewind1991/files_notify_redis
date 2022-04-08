<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Robin Appelman <robin@icewind.nl>
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

namespace OCA\FilesNotifyRedis\Change;

use DateTime;

/**
 * Extend core change class with extra metadata fields
 */
class Change extends \OC\Files\Notify\Change {
	private ?DateTime $time;
	private ?int $size;

	public function __construct(int $type, string $path, DateTime $time = null, int $size = null) {
		parent::__construct($type, $path);

		$this->time = $time;
		$this->size = $size;
	}

	public function getSize(): ?int {
		return $this->size;
	}

	public function getTime(): ?DateTime {
		return $this->time;
	}
}
