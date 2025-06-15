<?php

namespace FreepPBX\sms\utests;

require_once('../api/utests/RestApiBaseTestCase.php');

use FreePBX\modules\Api\utests\RestApiBaseTestCase;

class SmsRestApiTest extends RestApiBaseTestCase
{
	protected static $sms;

	/**
	 * setUpBeforeClass
	 *
	 * @return void
	 */
	public static function setUpBeforeClass()
	{
		parent::setUpBeforeClass();
		self::$sms = self::$freepbx->sms;
	}

	/**
	 * tearDownAfterClass
	 *
	 * @return void
	 */
	public static function tearDownAfterClass()
	{
		parent::tearDownAfterClass();
	}

	/**
	 * test_fetchMedia_fromRestAPI_whenALlIsGood_ShouldReturnTrue
	 *
	 * @return void
	 */
	public function test_fetchMedia_fromRestAPI_whenALlIsGood_ShouldReturnTrue()
	{
		$fileData = [];
  $fileData['raw'] = "xterm-256colorxterm-256colorxterm-256colorxterm-256colorxterm-256colorxterm-256colorxterm-256colorxterm-256colorxterm-256colorxterm-256colorxterm-256colorxterm-256colorxterm-256colorxterm";

		$mockHelper = $this->getMockBuilder(\Freepbx\modules\Sms::class)
			->disableOriginalConstructor()
			->setMethods(['getMediaByMediaID'])
			->getMock();

		$mockHelper->method('getMediaByMediaID')
			->willReturn($fileData);

		self::$freepbx->Sms = $mockHelper;

		$response = $this->request("GET", "/api/rest/sms/media/1");

		$this->assertEquals($fileData['raw'], $response->getBody());
	}


	/**
	 * test_fetchMedia_fromRestAPI_whenMediaIsNotFound_ShouldReturnFalse
	 *
	 * @return void
	 */
	public function test_fetchMedia_fromRestAPI_whenMediaIsNotFound_ShouldReturnFalse()
	{
		$fileData = "";

		$mockHelper = $this->getMockBuilder(\Freepbx\modules\Sms::class)
			->disableOriginalConstructor()
			->setMethods(['getMediaByMediaID'])
			->getMock();

		$mockHelper->method('getMediaByMediaID')
			->willReturn($fileData);

		self::$freepbx->Sms = $mockHelper;

		$response = $this->request("GET", "/api/rest/sms/media/1");

		$this->assertEquals(404, $response->getStatusCode());
	}

	/**
	 * test_fetchMedia_fromRestAPI_invalidUrl_ShouldReturnFalse
	 *
	 * @return void
	 */
	public function test_fetchMedia_fromRestAPI_invalidUrl_ShouldReturnFalse()
	{
		$fileData = "";

		$mockHelper = $this->getMockBuilder(\Freepbx\modules\Sms::class)
			->disableOriginalConstructor()
			->setMethods(['getMediaByMediaID'])
			->getMock();

		$mockHelper->method('getMediaByMediaID')
			->willReturn($fileData);

		self::$freepbx->Sms = $mockHelper;

		$response = $this->request("GET", "/api/rest/sms/media/abc");

		$this->assertEquals(404, $response->getStatusCode());
	}
}
