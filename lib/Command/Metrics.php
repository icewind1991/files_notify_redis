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

use Exception;
use OC\Core\Command\Base;
use OCA\FilesNotifyRedis\Notify\NotifyHandler;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Metrics extends Base {
	private RedisFactory $redisFactory;

	public function __construct(RedisFactory $redisFactory) {
		parent::__construct();
		$this->redisFactory = $redisFactory;
	}

	protected function configure() {
		$this
			->setName('files_notify_redis:metrics')
			->setDescription('Listen for redis updated notifications for the primary local storage')
			->addArgument('list', InputArgument::REQUIRED, 'redis list where the notifications are pushed')
			->addOption('host', null, InputArgument::OPTIONAL, 'redis host, if not provided the system wide redis configuration will be used')
			->addOption('port', null, InputArgument::OPTIONAL, 'redis port')
			->addOption('password', null, InputArgument::OPTIONAL, 'redis password');
		parent::configure();
	}


	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$redis = $this->redisFactory->getRedis($input->getOption('host'), $input->getOption('port'), $input->getOption('password'));
		} catch (Exception $e) {
			$output->writeln("<error>Failed to get redis connection</error>");
			return 1;
		}

		$notifyHandler = new NotifyHandler("", $redis, $input->getArgument('list'), "", function () {});
		$count = $notifyHandler->getCount();
		$output->writeln("Events processed: $count");
		return 0;
	}
}
