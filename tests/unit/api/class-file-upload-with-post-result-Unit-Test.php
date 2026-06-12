<?php

namespace BrianHenryIE\WP_Private_Uploads\API;

use BrianHenryIE\WP_Private_Uploads\API\Media_Request;
use BrianHenryIE\WP_Private_Uploads\API_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\Unit_Testcase;
use Codeception\Stub\Expected;
use DateTimeImmutable;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\API\File_Upload_With_Post_Result
 */
class File_Upload_With_Post_Result_Unit_Test extends Unit_Testcase {
	/**
	 * @covers ::__construct
	 */
	public function test_instantiate(): void {
		$result = new File_Upload_With_Post_Result(
			'/path/to/file.ext',
			'http://example.com/path/to/file.ext',
			'text/html',
			321,
		);

		$this->assertEquals( 321, $result->post_id );
	}
}
