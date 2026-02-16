<?php

namespace BrianHenryIE\WP_Private_Uploads;

use BrianHenryIE\ColorLogger\ColorLogger;
use Psr\Log\LoggerInterface;
use WP_Mock;

class Unit_Testcase extends \Codeception\Test\Unit {

	protected LoggerInterface $logger;

	protected function setup(): void {
		WP_Mock::setUp();

		$this->logger = new ColorLogger();
	}

	protected function tearDown(): void {
		parent::_tearDown();
		WP_Mock::tearDown();
		\Patchwork\restoreAll();
	}
}
