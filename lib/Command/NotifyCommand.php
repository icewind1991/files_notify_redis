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

namespace OCA\FilesNotifyRedis\Command;

use Exception;
use OC\Core\Command\Base;
use OCA\FilesNotifyRedis\Change\RenameChange;
use OCA\FilesNotifyRedis\Notify\ChangeHandler;
use OCA\FilesNotifyRedis\Notify\NotifyHandler;
use OCP\Files\Notify\IChange;
use OCP\Files\Storage\INotifyStorage;
use OCP\IConfig;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class NotifyCommand extends Base {
	private IConfig $config;
	private RedisFactory $redisFactory;
	private ChangeHandler $changeHandler;

	public function __construct(
		IConfig $config,
		RedisFactory $redisFactory,
		ChangeHandler $changeHandler,
	) {
		parent::__construct();
		$this->config = $config;
		$this->redisFactory = $redisFactory;
		$this->changeHandler = $changeHandler;
	}

	protected function configure() {
		$this
			->setName('files_notify_redis:primary')
			->setDescription('Listen for redis updated notifications for the primary local storage')
			->addArgument('list', InputArgument::REQUIRED, 'redis list where the notifications are pushed')
			->addOption('host', null, InputArgument::OPTIONAL, 'redis host, if not provided the system wide redis configuration will be used')
			->addOption('port', null, InputArgument::OPTIONAL, 'redis port')
			->addOption('password', null, InputArgument::OPTIONAL, 'redis password')
			->addOption('prefix', 'p', InputOption::VALUE_REQUIRED, 'The prefix that is stripped from the path retrieved from redis, defaults to the Nextcloud data directory')
			->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'The format of the path retrieved from redis after the prefix is stripped', '/$user/files/$path');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$dataDirectory = $this->config->getSystemValue('datadirectory');
		$prefix = $input->getOption('prefix') ?? $dataDirectory;
		$format = $input->getOption('format');
		try {
			$redis = $this->redisFactory->getRedis($input->getOption('host'), $input->getOption('port'), $input->getOption('password'));
		} catch (Exception $e) {
			$output->writeln('<error>Failed to get redis connection</error>');
			return 1;
		}
		$verbose = $input->getOption('verbose');

		$debugCallback = function ($message) use ($verbose, $output) {
			if ($verbose) {
				$output->writeln("<error>$message</error>");
			}
		};

		$notifyHandler = new NotifyHandler($prefix, $redis, $input->getArgument('list'), $format, $debugCallback);

		$notifyHandler->listen(function (IChange $change) use ($verbose, $output) {
			if ($verbose) {
				$this->logUpdate($change, $output);
			}

			try {
				$this->changeHandler->applyChange($change);
			} catch (Exception $e) {
				$output->writeln('<error>' . $e->getMessage() . '</error>');
			}
		});

		return 0;
	}

	private function logUpdate(IChange $change, OutputInterface $output) {
		switch ($change->getType()) {
			case INotifyStorage::NOTIFY_ADDED:
				$text = 'added';
				break;
			case INotifyStorage::NOTIFY_MODIFIED:
				$text = 'modified';
				break;
			case INotifyStorage::NOTIFY_REMOVED:
				$text = 'removed';
				break;
			case INotifyStorage::NOTIFY_RENAMED:
				$text = 'renamed';
				break;
			default:
				return;
		}

		$text .= ' ' . $change->getPath();
		if ($change instanceof RenameChange) {
			$text .= ' to ' . $change->getTargetPath();
		}

		$output->writeln($text);
	}
}
