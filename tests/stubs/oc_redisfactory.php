<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OC;

use OCP\Diagnostics\IEventLogger;

class RedisFactory {
	public const REDIS_MINIMAL_VERSION = '4.0.0';
	public const REDIS_EXTRA_PARAMETERS_MINIMAL_VERSION = '5.3.0';

	public function __construct(
		private SystemConfig $config,
		private IEventLogger $eventLogger,
	) {
	}

	public function getInstance(): \Redis|\RedisCluster
    {
    }

	public function isAvailable(): bool
    {
    }
}
