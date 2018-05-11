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
use OCA\FilesNotifyRedis\Storage\NotifyHandler;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Notify\IChange;
use OCP\Files\Notify\IRenameChange;
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
			->addOption('prefix', 'p', InputOption::VALUE_REQUIRED, 'The prefix that is stripped from the path retrieved from redis, defaults to the Nextcloud data directory')
			->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'The format of the path retrieved from redis after the prefix is stripped', '/$user/files/$path');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$dataDirectory = $this->config->getSystemValue('datadirectory');
		$prefix = $input->getOption('prefix') ?? $dataDirectory;
		$format = $input->getOption('format');
		$redisFactory = \OC::$server->getGetRedisFactory();
		$notifyHandler = new NotifyHandler($prefix, $redisFactory->getInstance(), $input->getArgument('list'), $format);
		$verbose = $input->getOption('verbose');

		$notifyHandler->listen(function (IChange $change) use ($verbose, $output) {
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

	private function handleUpdate(IChange $change, IUser $user, $path) {
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
				/** @var IRenameChange $change */
				list($_, $targetPath) = explode('/', $change->getTargetPath(), 2);
				$updater->renameFromStorage($mount->getStorage(), $path, $targetPath);
				break;
			default:
				return;
		}
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
		if ($change instanceof IRenameChange) {
			$text .= ' to ' . $change->getTargetPath();
		}

		$output->writeln($text);
	}
}
