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

use OC\Core\Command\Base;
use OCA\FilesNotifyRedis\Change\Change;
use OCA\FilesNotifyRedis\Change\RenameChange;
use OCA\FilesNotifyRedis\Storage\NotifyHandler;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Storage\INotifyStorage;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class NotifyCommand extends Base {
	/** @var IConfig */
	private $config;
	/** @var IMountProviderCollection */
	private $mountProviderCollection;
	/** @var IUserManager */
	private $userManager;

	public function __construct() {
		parent::__construct();
		$this->config = \OC::$server->getConfig();
		$this->mountProviderCollection = \OC::$server->getMountProviderCollection();
		$this->userManager = \OC::$server->getUserManager();
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

	/**
	 * @param $host
	 * @param $port
	 * @param $password
	 * @return \Redis
	 * @throws \Exception
	 */
	private function getRedis($host, $port, $password) {
		if ($host) {
			if (!$port) {
				$port = 6379;
			}
			$instance = new \Redis();

			$instance->connect($host, $port, 0.0);
			if ($password) {
				$instance->auth($password);
			}
			return $instance;
		} else {
			$redisFactory = \OC::$server->getGetRedisFactory();
			return $redisFactory->getInstance();
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$dataDirectory = $this->config->getSystemValue('datadirectory');
		$prefix = $input->getOption('prefix') ?? $dataDirectory;
		$format = $input->getOption('format');
		$redis = $this->getRedis($input->getOption('host'), $input->getOption('port'), $input->getOption('password'));
		$verbose = $input->getOption('verbose');

		$debugCallback = function ($message) use ($verbose, $output) {
			if ($verbose) {
				$output->writeln("<error>$message</error>");
			}
		};

		$notifyHandler = new NotifyHandler($prefix, $redis, $input->getArgument('list'), $format, $debugCallback);

		$notifyHandler->listen(function (Change $change) use ($verbose, $output) {
			if ($verbose) {
				$this->logUpdate($change, $output);
			}

			list ($userId, , $subPath) = explode('/', $change->getPath());
			$user = $this->userManager->get($userId);
			if (!$user) {
				$output->writeln("<error>Unknown user $userId</error>");
			} else {
				$this->handleUpdate($change, $user, 'files/' . $subPath);
			}
		});
	}

	private function handleUpdate(Change $change, IUser $user, $path) {
		$mount = $this->mountProviderCollection->getHomeMountForUser($user);
		$updater = $mount->getStorage()->getUpdater();

		switch ($change->getType()) {
			case INotifyStorage::NOTIFY_ADDED:
			case INotifyStorage::NOTIFY_MODIFIED:
				$updater->update($path);
				break;
			case INotifyStorage::NOTIFY_REMOVED:
				$updater->remove($path);
				break;
			case INotifyStorage::NOTIFY_RENAMED:
				/** @var RenameChange $change */
				list($_, $targetPath) = explode('/', $change->getTargetPath(), 2);
				$updater->renameFromStorage($mount->getStorage(), $path, $targetPath);
				break;
			default:
				return;
		}
	}

	private function logUpdate(Change $change, OutputInterface $output) {
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
