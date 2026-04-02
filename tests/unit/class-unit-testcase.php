<?php

namespace BrianHenryIE\WP_Private_Uploads;

use BrianHenryIE\ColorLogger\ColorLogger;
use Psr\Log\Test\TestLogger;
use WP_Mock;

class Unit_Testcase extends \Codeception\Test\Unit {

	protected TestLogger $logger;

	protected function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		$this->logger = new ColorLogger();
	}

	protected function tearDown(): void {
		parent::tearDown();
		WP_Mock::tearDown();
		\Patchwork\restoreAll();
	}
}
