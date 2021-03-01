<?php

use OCA\FilesNotifyRedis\AppInfo\Application;

/** @var Application $application */
$application = \OC::$server->query(Application::class);
$application->register();
