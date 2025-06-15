<?php

namespace FreepPBX\sms\utests;

use FreePBX\modules\Api\utests\ApiBaseTestCase;

class SmsGqlApiTest extends ApiBaseTestCase
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
		self::$sms = self::$freepbx->Sms;
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
	 * test_addSmsWebhook_whenAllIsGood_shouldReturnTrue
	 *
	 * @return void
	 */
	public function test_addSmsWebhook_whenAllIsGood_shouldReturnTrue()
	{

		$mockHelper = $this->getMockBuilder(\Freepbx\modules\sms\Sms::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->setMethods(['addWebhook'])
			->getMock();

		$mockHelper->method('addWebhook')
			->willReturn(['status' => true]);

		self::$freepbx->sms = $mockHelper;

		$response = $this->request("mutation{
										addSmsWebhook(input : {
											webHookBaseurl: \"https://web.hook.sh/31fb9b81-3a9e-4e93-a2bd-147761ea82bb\"
											enablewebHook: true
											dataToBeSentOn: send
										}){
											status message
										}
									}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"data":{"addSmsWebhook":{"status":true,"message":"Webhook added successfully..!!"}}}', $json);

		$this->assertEquals(200, $response->getStatusCode());
	}

	/**
	 * test_addSmsWebhook_whenHookIsAlreadyExists_shouldRetuFalseue
	 *
	 * @return void
	 */
	public function test_addSmsWebhook_whenHookIsAlreadyExists_shouldReturnFalse()
	{

		$mockHelper = $this->getMockBuilder(\Freepbx\modules\sms\Sms::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->setMethods(['addWebhook'])
			->getMock();

		$mockHelper->method('addWebhook')
			->willReturn(['status' => false, 'message' => _("web hook already exists for data to be sent on send sms events")]);

		self::$freepbx->sms = $mockHelper;

		$response = $this->request("mutation{
										addSmsWebhook(input : {
											webHookBaseurl: \"https://web.hook.sh/31fb9b81-3a9e-4e93-a2bd-147761ea82bb\"
											enablewebHook: true
											dataToBeSentOn: send
										}){
											status message
										}
									}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"errors":[{"message":"web hook already exists for data to be sent on send sms events","status":false}]}', $json);

		$this->assertEquals(400, $response->getStatusCode());
	}

	/**
	 * test_addSmsWebhook_whenRequiredFieldIsNotGiven_shouldRetuFalseue
	 *
	 * @return void
	 */
	public function test_addSmsWebhook_whenRequiredFieldIsNotGiven_shouldReturnFalse()
	{
		$response = $this->request("mutation{
										addSmsWebhook(input : {
											enablewebHook: true
										}){
											status message
										}
									}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"errors":[{"message":"Field addSmsWebhookInput.webHookBaseurl of required type String! was not provided.","status":false}]}', $json);

		$this->assertEquals(400, $response->getStatusCode());
	}

	/**
	 * test_updateSmsWebhook_whenAllIsGood_shouldReturnTrue
	 *
	 * @return void
	 */
	public function test_updateSmsWebhook_whenAllIsGood_shouldReturnTrue()
	{

		$mockHelper = $this->getMockBuilder(\Freepbx\modules\sms\Sms::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->setMethods(['updateWebhook'])
			->getMock();

		$mockHelper->method('updateWebhook')
			->willReturn(['status' => true]);

		self::$freepbx->sms = $mockHelper;

		$response = $this->request("mutation{
										updateSmsWebhook(input : {
											id: \"12\"
											webHookBaseurl: \"https://web.hook.sh/31fb9b81-3a9e-4e93-a2bd-147761ea82bb\"
											enablewebHook: true
											dataToBeSentOn: send
										}){
											status message
										}
									}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"data":{"updateSmsWebhook":{"status":true,"message":"Webhook updated successfully..!!"}}}', $json);

		$this->assertEquals(200, $response->getStatusCode());
	}

	/**
	 * test_updateSmsWebhook_whenHookIsAlreadyExists_shouldRetuFalseue
	 *
	 * @return void
	 */
	public function test_updateSmsWebhook_whenHookIsAlreadyExists_shouldReturnFalse()
	{

		$mockHelper = $this->getMockBuilder(\Freepbx\modules\sms\Sms::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->setMethods(['updateWebhook'])
			->getMock();

		$mockHelper->method('updateWebhook')
			->willReturn(['status' => false, 'message' => _("web hook already exists for data to be sent on send sms events")]);

		self::$freepbx->sms = $mockHelper;

		$response = $this->request("mutation{
										updateSmsWebhook(input : {
											id: \"12\"
											webHookBaseurl: \"https://web.hook.sh/31fb9b81-3a9e-4e93-a2bd-147761ea82bb\"
											enablewebHook: true
											dataToBeSentOn: send
										}){
											status message
										}
									}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"errors":[{"message":"web hook already exists for data to be sent on send sms events","status":false}]}', $json);

		$this->assertEquals(400, $response->getStatusCode());
	}

	/**
	 * test_updateSmsWebhook_whenRequiredFieldIsNotGiven_shouldRetuFalseue
	 *
	 * @return void
	 */
	public function test_updateSmsWebhook_whenRequiredFieldIsNotGiven_shouldReturnFalse()
	{
		$response = $this->request("mutation{
										updateSmsWebhook(input : {
											enablewebHook: true
										}){
											status message
										}
									}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"errors":[{"message":"Field updateSmsWebhookInput.id of required type String! was not provided.","status":false}]}', $json);

		$this->assertEquals(400, $response->getStatusCode());
	}

	/**
	 * test_fetchAllSmsWebhook_whenAllIsGood_shouldReturnTrue
	 *
	 * @return void
	 */
	public function test_fetchAllSmsWebhook_whenAllIsGood_shouldReturnTrue()
	{

		$mockHelper = $this->getMockBuilder(\Freepbx\modules\sms\Sms::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->setMethods(['getAllWebhooks'])
			->getMock();

		$mockHelper->method('getAllWebhooks')
			->willReturn([["id" => 25, "webhookUrl" => "https://web.hook.sh/31fb9b81-3a9e-4e93-a2bd-147761ea82bb", "enablewebHook" => 1, "dataToBeSentOn" => "receive"]]);

		self::$freepbx->sms = $mockHelper;

		$response = $this->request("query{
										fetchAllSmsWebhook{
											status message webhookDetails{
												id
												webhookUrl
												enablewebHook
												dataToBeSentOn
												}
											}
										}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"data":{"fetchAllSmsWebhook":{"status":true,"message":"List of sms webhooks","webhookDetails":[{"id":"25","webhookUrl":"https:\/\/web.hook.sh\/31fb9b81-3a9e-4e93-a2bd-147761ea82bb","enablewebHook":true,"dataToBeSentOn":"receive"}]}}}', $json);

		$this->assertEquals(200, $response->getStatusCode());
	}

	/**
	 * test_fetchAllSmsWebhook_whenInvalidFieldIsQueried_shouldReturnFalse
	 *
	 * @return void
	 */
	public function test_fetchAllSmsWebhook_whenInvalidFieldIsQueried_shouldReturnFalse()
	{
		$response = $this->request("query{
										fetchAllSmsWebhook{
											status message webhookDetails{
												id
												lorem
												enablewebHook
												dataToBeSentOn
												}
											}
										}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"errors":[{"message":"Cannot query field \"lorem\" on type \"sms\".","status":false}]}', $json);

		$this->assertEquals(400, $response->getStatusCode());
	}

	/**
	 * test_fetchSmsWebhook_whenEverythingIsGood_shouldReturntrue
	 *
	 * @return void
	 */
	public function test_fetchSmsWebhook_whenEverythingIsGood_shouldReturntrue()
	{

		$mockHelper = $this->getMockBuilder(\Freepbx\modules\sms\Sms::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->setMethods(['getWebhookById'])
			->getMock();

		$mockHelper->method('getWebhookById')
			->willReturn(["id" => 25, "webhookUrl" => "https://web.hook.sh/31fb9b81-3a9e-4e93-a2bd-147761ea82bb", "enablewebHook" => 1, "dataToBeSentOn" => "receive"]);

		self::$freepbx->sms = $mockHelper;

		$response = $this->request("query{
										fetchSmsWebhook(id: \"29\") {
											status 
											message
											id
											webhookUrl
											enablewebHook
											dataToBeSentOn
										}
									}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"data":{"fetchSmsWebhook":{"status":true,"message":"Sms webhooks data found successfully..!!","id":"25","webhookUrl":"https:\/\/web.hook.sh\/31fb9b81-3a9e-4e93-a2bd-147761ea82bb","enablewebHook":true,"dataToBeSentOn":"receive"}}}', $json);

		$this->assertEquals(200, $response->getStatusCode());
	}


	/**
	 * test_fetchSmsWebhook_whenWhenInvalidFieldIsQueried_ShouldReturnFalse
	 *
	 * @return void
	 */
	public function test_fetchSmsWebhook_whenWhenInvalidFieldIsQueried_ShouldReturnFalse()
	{
		$response = $this->request("query{
										fetchSmsWebhook(id: \"29\") {
											lorem 
											message
											id
											webhookUrl
											enablewebHook
											dataToBeSentOn
										}
									}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"errors":[{"message":"Cannot query field \"lorem\" on type \"SmsConnection\".","status":false}]}', $json);

		$this->assertEquals(400, $response->getStatusCode());
	}

	/**
	 * test_deleteSmsWebhook_whenEverythingIsGood_shouldReturntrue
	 *
	 * @return void
	 */
	public function test_deleteSmsWebhook_whenEverythingIsGood_shouldReturntrue()
	{

		$mockHelper = $this->getMockBuilder(\Freepbx\modules\sms\Sms::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->setMethods(['getWebhookById', 'deleteWebHook'])
			->getMock();

		$mockHelper->method('getWebhookById')
			->willReturn(["id" => 25, "webhookUrl" => "https://web.hook.sh/31fb9b81-3a9e-4e93-a2bd-147761ea82bb", "enablewebHook" => 1, "dataToBeSentOn" => "receive"]);

		$mockHelper->method('deleteWebHook')
			->willReturn(true);

		self::$freepbx->sms = $mockHelper;

		$response = $this->request("mutation {
										deleteSmsWebhook(input: {
											id: \"37\"
										}) {
											status
											message
										}
									}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"data":{"deleteSmsWebhook":{"status":true,"message":"Sms webhook deleted successfully"}}}', $json);

		$this->assertEquals(200, $response->getStatusCode());
	}


	/**
	 * test_deleteSmsWebhook_whenIdIsInvalid_shouldReturnfalse
	 *
	 * @return void
	 */
	public function test_deleteSmsWebhook_whenIdIsInvalid_shouldReturnfalse()
	{

		$mockHelper = $this->getMockBuilder(\Freepbx\modules\sms\Sms::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->setMethods(['getWebhookById', 'deleteWebHook'])
			->getMock();

		$mockHelper->method('getWebhookById')
			->willReturn(null);

		$mockHelper->method('deleteWebHook')
			->willReturn(false);

		self::$freepbx->sms = $mockHelper;

		$response = $this->request("mutation {
										deleteSmsWebhook(input: {
											id: \"37\"
										}) {
											status
											message
										}
									}");

		$json = (string)$response->getBody();

		$this->assertEquals('{"errors":[{"message":"Sms webhook does not exists","status":false}]}', $json);

		$this->assertEquals(400, $response->getStatusCode());
	}
}
