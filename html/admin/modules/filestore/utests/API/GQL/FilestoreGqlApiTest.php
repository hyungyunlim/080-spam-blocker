<?php 

namespace FreepPBX\filestore\utests;

require_once('../api/utests/ApiBaseTestCase.php');

use FreePBX\modules\filestore;
use Exception;
use FreePBX\modules\Api\utests\ApiBaseTestCase;

/**
 * Pm2GqlApiTest
 */
class FilestoreGqlApiTest extends ApiBaseTestCase {
    protected static $filestore;
        
    /**
     * setUpBeforeClass
     *
     * @return void
     */
    public static function setUpBeforeClass() {
      parent::setUpBeforeClass();
      self::$filestore = self::$freepbx->filestore;
    }
        
    /**
     * tearDownAfterClass
     *
     * @return void
     */
    public static function tearDownAfterClass() {
      parent::tearDownAfterClass();
    }
      
  /**
   * test_fetchAWSRegions_all_Good_Should_return_true
   *
   * @return void
   */
  public function test_fetchAWSRegions_all_Good_Should_return_true(){
    $response = $this->request("query{
      fetchAWSRegion{
        status
        message
        regions
      }}");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"data":{"fetchAWSRegion":{"status":true,"message":"List of AWS Storage","regions":"[\"us-east-2\",\"us-east-1\",\"us-gov-east-1\",\"us-west-1\",\"us-west-2\",\"us-gov-west-1\",\"ca-central-1\",\"ap-south-1\",\"ap-northeast-3\",\"ap-northeast-2\",\"ap-southeast-1\",\"ap-southeast-2\",\"ap-northeast-1\",\"cn-north-1\",\"cn-northwest-1\",\"eu-central-1\",\"eu-west-1\",\"eu-west-2\",\"eu-west-3\",\"eu-north-1\",\"sa-east-1\"]"}}}',$json);
      
    $this->assertEquals(200, $response->getStatusCode());
  }
  
  /**
   * test_fetchFilestoreTypes_all_Good_Should_return_true
   *
   * @return void
   */
  public function test_fetchFilestoreTypes_all_Good_Should_return_true(){

    $mockfilestore = $this->getMockBuilder(\FreePBX\modules\filestore\Filestore::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('listLocations'))
      ->getMock();
      
	 $mockfilestore->method('listLocations')
		->willReturn(array('filestoreTypes' => array('Dropbox','Email','FTP')));
    
    self::$freepbx->filestore = $mockfilestore; 

    $response = $this->request("query{
      fetchFilestoreTypes{
        status message types
      }
    }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"data":{"fetchFilestoreTypes":{"status":true,"message":"List of filestore types","types":["Dropbox","Email","FTP"]}}}',$json);
      
    $this->assertEquals(200, $response->getStatusCode());
  }
  
  /**
   * test_fetchFilestoreTypes_when_empty_Should_return_false
   *
   * @return void
   */
  public function test_fetchFilestoreTypes_when_empty_Should_return_false(){
   $mockfilestore = $this->getMockBuilder(\FreePBX\modules\filestore\Filestore::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('listLocations'))
      ->getMock();
      
	 $mockfilestore->method('listLocations')
		->willReturn('');
    
    self::$freepbx->filestore = $mockfilestore; 

    $response = $this->request("query{
      fetchFilestoreTypes{
        status message types
      }
    }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"errors":[{"message":"Sorry unable to find the filestore types","status":false}]}',$json);
      
    $this->assertEquals(400, $response->getStatusCode());
  }
  
  /**
   * test_addFTPInstance_all_good_Should_return_true
   *
   * @return void
   */
  public function test_addFTPInstance_all_good_Should_return_true(){
   $mockfilestore = $this->getMockBuilder(\FreePBX\modules\filestore\Filestore::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('addItem'))
      ->getMock();
      
	 $mockfilestore->method('addItem')
		->willReturn('123456789');
    
    self::$freepbx->filestore = $mockfilestore; 

    $response = $this->request("mutation{
      addFTPInstance(input : {
          serverName: \"testGql\"
          hostName: \"100.100.100.100\"
          userName: \"testGql\"
          password: \"testGql\"    
      }){
        status message id
      }
    }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"data":{"addFTPInstance":{"status":true,"message":"FTP Instance is created successfully","id":"123456789"}}}',$json);
      
    $this->assertEquals(200, $response->getStatusCode());
  }
  
  /**
   * test_addFTPInstance_When_required_param_not_sent_Should_return_false
   *
   * @return void
   */
  public function test_addFTPInstance_When_required_param_not_sent_Should_return_false(){
   $mockfilestore = $this->getMockBuilder(\FreePBX\modules\filestore\Filestore::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('addItem'))
      ->getMock();
      
	 $mockfilestore->method('addItem')
		->willReturn('123456789');
    
   self::$freepbx->filestore = $mockfilestore; 

    $response = $this->request("mutation{
      addFTPInstance(input : {
          serverName: \"testGql\"
          userName: \"testGql\"
          password: \"testGql\"    
      }){
        status message id
      }
    }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"errors":[{"message":"Field addFTPInstanceInput.hostName of required type String! was not provided.","status":false}]}',$json);
      
    $this->assertEquals(400, $response->getStatusCode());
  }
  
  /**
   * test_addFTPInstance_when_return_is_as_null_Should_return_false
   *
   * @return void
   */
  public function test_addFTPInstance_when_return_is_as_null_Should_return_false(){
   $mockfilestore = $this->getMockBuilder(\FreePBX\modules\filestore\Filestore::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('addItem'))
      ->getMock();
      
	 $mockfilestore->method('addItem')
		->willReturn(null);
    
   self::$freepbx->filestore = $mockfilestore; 

    $response = $this->request("mutation{
      addFTPInstance(input : {
          serverName: \"testGql\"
          hostName: \"100.100.100.100\"
          userName: \"testGql\"
          password: \"testGql\"    
      }){
        status message id
      }
    }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"errors":[{"message":"Sorry unable to create FTP Instance","status":false}]}',$json);
      
    $this->assertEquals(400, $response->getStatusCode());
  }

  public function test_addS3Bucket_all_good_Should_return_true(){
   $mockfilestore = $this->getMockBuilder(\FreePBX\modules\filestore\Filestore::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('addItem'))
      ->getMock();
      
	 $mockfilestore->method('addItem')
		->willReturn('123456789');
    
   self::$freepbx->filestore = $mockfilestore; 

    $response = $this->request("mutation{
        addS3Bucket(input : {
            name: \"testGql\"
            bucketName: \"100.100.100.100\"
            AWSRegion: \"us-east-2\"
            AWSAccessKey: \"testGql\"    
            AWSSecret: \"12345\"
        }){
          status message id
        }
      }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"data":{"addS3Bucket":{"status":true,"message":"S3 Instance is created successfully","id":"123456789"}}}',$json);
      
    $this->assertEquals(200, $response->getStatusCode());
  }
    
  /**
   * test_addS3Bucket_When_required_param_not_sent_Should_return_false
   *
   * @return void
   */
  public function test_addS3Bucket_When_required_param_not_sent_Should_return_false(){
   $mockfilestore = $this->getMockBuilder(\FreePBX\modules\filestore\Filestore::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('addItem'))
      ->getMock();
      
	 $mockfilestore->method('addItem')
		->willReturn('123456789');
    
   self::$freepbx->filestore = $mockfilestore; 

    $response = $this->request("mutation{
      addS3Bucket(input : {
          name: \"testGql\"
      }){
        status message id
      }
    }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"errors":[{"message":"Field addS3BucketInput.bucketName of required type String! was not provided.","status":false}]}',$json);
      
    $this->assertEquals(400, $response->getStatusCode());
  }

  public function test_addS3Bucket_when_return_is_null_Should_return_false(){
   $mockfilestore = $this->getMockBuilder(\FreePBX\modules\filestore\Filestore::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('addItem'))
      ->getMock();
      
	 $mockfilestore->method('addItem')
		->willReturn(null);
    
   self::$freepbx->filestore = $mockfilestore; 

    $response = $this->request("mutation{
        addS3Bucket(input : {
            name: \"testGql\"
            bucketName: \"100.100.100.100\"
            AWSRegion: \"us-east-2\"
            AWSAccessKey: \"testGql\"    
            AWSSecret: \"12345\"
        }){
          status message id
        }
      }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"errors":[{"message":"Sorry unable to create S3 Instance","status":false}]}',$json);
      
    $this->assertEquals(400, $response->getStatusCode());
  }
  
  /**
   * test_fetchFilestoreLocations_when_empty_Should_return_false
   *
   * @return void
   */
  public function test_fetchFilestoreLocations_when_empty_Should_return_false(){
   $mockfilestore = $this->getMockBuilder(\FreePBX\modules\filestore\Filestore::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('listLocations'))
      ->getMock();
      
	 $mockfilestore->method('listLocations')
		->willReturn('');
    
   self::$freepbx->filestore = $mockfilestore; 

    $response = $this->request("query{
      fetchFilestoreLocations{
        status message locations
      }
    }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"errors":[{"message":"Sorry unable to find the filestore locations","status":false}]}',$json);
      
    $this->assertEquals(400, $response->getStatusCode());
  }
    
  /**
   * test_fetchFilestoreLocations_when_returns_locations_Should_return_true_list_of_locations
   *
   * @return void
   */
  public function test_fetchFilestoreLocations_when_returns_locations_Should_return_true_list_of_locations(){
   $mockfilestore = $this->getMockBuilder(\FreePBX\modules\filestore\Filestore::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('listLocations'))
      ->getMock();
      
	 $mockfilestore->method('listLocations')
		->willReturn(array('locations' => array('Email' => array(array('id' => '123456789')),'SSH' => array(array('id' => '987654321') , array('id' => '1122334455')))));
    
    self::$freepbx->filestore = $mockfilestore; 

    $response = $this->request("query{
      fetchFilestoreLocations{
        status message locations
      }
    }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"data":{"fetchFilestoreLocations":{"status":true,"message":"List of filestore locations","locations":["Email_123456789","SSH_987654321","SSH_1122334455"]}}}',$json);
      
    $this->assertEquals(200, $response->getStatusCode());
  }
  public function test_fetchFetchAllFilestores_when_returns_locations_Should_return_true_list_of_name_and_id_and_description(){
		$mockfilestore = $this->getMockBuilder(\FreePBX\modules\filestore\Filestore::class)
    ->disableOriginalConstructor()
    ->disableOriginalClone()
    ->setMethods(array('listLocations'))
    ->getMock();
		
		$mockfilestore->method('listLocations')
    ->willReturn(array('locations' => array('FTP' => array(array('id' => '123456789', 'name' => "Testing", "description" => "Testing Lorem")))));
		
		self::$freepbx->filestore = $mockfilestore; 

		$response = $this->request("query{
      fetchAllFilestores{
        status message filestores{
          id
          name
          description
          filestoreType
        }
      }
    }");
		
		$json = (string)$response->getBody();

    $this->assertEquals('{"data":{"fetchAllFilestores":{"status":true,"message":"List of all filestores","filestores":[{"id":"FTP_123456789","name":"Testing","description":"Testing Lorem","filestoreType":"FTP"}]}}}', $json);
		
		$this->assertEquals(200, $response->getStatusCode());
	}

	public function test_fetchFetchAllFilestores_when_wrong_parameter_sent_should_return_error_and_false(){
	
		$response = $this->request("query{
      fetchAllFilestores{
        status message filestores{
          id
          name
          type
          }
        }
      }");
		
		$json = (string)$response->getBody();
	
		$this->assertEquals('{"errors":[{"message":"Cannot query field \"type\" on type \"filestore\".","status":false}]}',$json);
		
		$this->assertEquals(400, $response->getStatusCode());
	}

	public function test_fetchFetchAllFilestores_when_location_not_return_should_return_false(){
		$mockfilestore = $this->getMockBuilder(\FreePBX\modules\filestore\Filestore::class)
    ->disableOriginalConstructor()
    ->disableOriginalClone()
    ->setMethods(array('listLocations'))
    ->getMock();
		
		$mockfilestore->method('listLocations')
					->willReturn(array('locations' => array()));
		
		self::$freepbx->filestore = $mockfilestore; 

		$response = $this->request("query{
      fetchAllFilestores{
        status message filestores{
          id
          name
          description
          filestoreType
          }
        }
      }");
		
		$json = (string)$response->getBody();
	
		$this->assertEquals('{"errors":[{"message":"Sorry, unable to find any filestore","status":false}]}',$json);
		
		$this->assertEquals(400, $response->getStatusCode());
	}
  
  /**
   * test_updateFTPInstance_all_good_Should_return_true
   *
   * @return void
   */
  public function test_updateFTPInstance_all_good_Should_return_true(){
   $mockfilestore = $this->getMockBuilder(\FreePBX\modules\filestore\Filestore::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('editItem','getItemById'))
      ->getMock();
      
	 $mockfilestore->method('editItem')
		->willReturn(true);
   
  $mockfilestore->method('getItemById')
		->willReturn(array('desc' => "description", 'driver' => 'FTP', 'fstype' => 'auto', 'host' => '100.100.100.100', 'id' => '12324', 'name' => 'test' ,'password' => 'password' , 'path' => '\tmp' , 'port' => 100, 'timeout' => 20, 'transfer' => 'active', 'user' =>'user'));
    
    self::$freepbx->filestore = $mockfilestore; 

    $response = $this->request("mutation{
      updateFTPInstance(input : {
          id : \"FTP_12324\"
          serverName: \"testGql\"
          hostName: \"100.100.100.100\"
          userName: \"testGql\"
          password: \"testGql\"    
      }){
        status message
      }
    }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"data":{"updateFTPInstance":{"status":true,"message":"FTP_12324 Instance is updated successfully"}}}',$json);
      
    $this->assertEquals(200, $response->getStatusCode());
  }
  
  /**
   * test_updateFTPInstance_required_field_not_passed_should_return_false
   *
   * @return void
   */
  public function test_updateFTPInstance_required_field_not_passed_should_return_false(){
    $response = $this->request("mutation{
      updateFTPInstance(input : {
          serverName: \"testGql\"
          hostName: \"100.100.100.100\"
          userName: \"testGql\"
          password: \"testGql\"    
      }){
        status message
      }
    }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"errors":[{"message":"Field updateFTPInstanceInput.id of required type String! was not provided.","status":false}]}',$json);
      
    $this->assertEquals(400, $response->getStatusCode());
  }
  
  /**
   * test_updateFTPInstance_when_unable_to_update_should_return_false
   *
   * @return void
   */
  public function test_updateFTPInstance_when_unable_to_update_should_return_false(){
   $mockfilestore = $this->getMockBuilder(\FreePBX\modules\filestore\Filestore::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('editItem','getItemById'))
      ->getMock();
      
	 $mockfilestore->method('editItem')
		->willReturn(false);
   
  $mockfilestore->method('getItemById')
		->willReturn(array('desc' => "description", 'driver' => 'FTP', 'fstype' => 'auto', 'host' => '100.100.100.100', 'id' => '12324', 'name' => 'test' ,'password' => 'password' , 'path' => '\tmp' , 'port' => 100, 'timeout' => 20, 'transfer' => 'active', 'user' =>'user'));
    
    self::$freepbx->filestore = $mockfilestore; 

    $response = $this->request("mutation{
      updateFTPInstance(input : {
          id : \"FTP_12324\"
          serverName: \"testGql\"
          hostName: \"100.100.100.100\"
          userName: \"testGql\"
          password: \"testGql\"    
      }){
        status message
      }
    }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"errors":[{"message":"Sorry unable to update FTP_12324 Instance","status":false}]}',$json);
      
    $this->assertEquals(400, $response->getStatusCode());
  }
  
  /**
   * test_deleteFTPInstance_when_unable_to_delete_should_return_false
   *
   * @return void
   */
  public function test_deleteFTPInstance_when_unable_to_delete_should_return_false(){
   $mockfilestore = $this->getMockBuilder(\FreePBX\modules\filestore\Filestore::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('deleteItem'))
      ->getMock();
      
	 $mockfilestore->method('deleteItem')
		->willReturn(true);
   
    self::$freepbx->filestore = $mockfilestore; 

    $response = $this->request("mutation {
      deleteFTPInstance(input: { id : \"FTP_124234\"}){
        status
        message
      }
    }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"errors":[{"message":"Sorry, unable to process your delete request","status":false}]}',$json);
      
    $this->assertEquals(400, $response->getStatusCode());
  }
  
  /**
   * test_deleteFTPInstance_when_delete_should_return_true
   *
   * @return void
   */
  public function test_deleteFTPInstance_when_delete_should_return_true(){
   $mockfilestore = $this->getMockBuilder(\FreePBX\modules\filestore\Filestore::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('deleteItem'))
      ->getMock();
      
	 $mockfilestore->method('deleteItem')
		->willReturn(false);
   
    self::$freepbx->filestore = $mockfilestore; 

    $response = $this->request("mutation {
      deleteFTPInstance(input: { id : \"FTP_124234\"}){
        status
        message
      }
    }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"data":{"deleteFTPInstance":{"status":true,"message":"Successfully deleted FTP instance"}}}',$json);
      
    $this->assertEquals(200, $response->getStatusCode());
  }
  
  /**
   * test_fetchFileStoreDetails_all_good_Should_return_true
   *
   * @return void
   */
  public function test_fetchFileStoreDetails_all_good_Should_return_true(){
   $mockfilestore = $this->getMockBuilder(\FreePBX\modules\filestore\Filestore::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('getItemById'))
      ->getMock();
   
    $mockfilestore->method('getItemById')
  		->willReturn(array('desc' => "description", 'driver' => 'FTP', 'fstype' => 'auto', 'host' => '100.100.100.100', 'id' => '12324', 'name' => 'test' ,'password' => 'password' , 'path' => 'tmp' , 'port' => 100, 'timeout' => 20, 'transfer' => 'active', 'user' =>'user'));
    
    self::$freepbx->filestore = $mockfilestore; 

    $response = $this->request("{
        fetchFileStoreDetails(id: \"FTP_12324\") {
          status
          message
          serverName
          hostName
          description
          port
          userName
          password
          fileStoreType
          path
          transferMode
          timeout
          }
      }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"data":{"fetchFileStoreDetails":{"status":true,"message":"FTP instance found successfully","serverName":"test","hostName":"100.100.100.100","description":"description","port":"100","userName":"user","password":"password","fileStoreType":"auto","path":"tmp","transferMode":"active","timeout":"20"}}}',$json);
      
    $this->assertEquals(200, $response->getStatusCode());
  }

  public function test_fetchFileStoreDetails_when_instance_does_not_exists_should_retuen_false(){
   $mockfilestore = $this->getMockBuilder(\FreePBX\modules\filestore\Filestore::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('getItemById'))
      ->getMock();
   
    $mockfilestore->method('getItemById')
  		->willReturn(false);
    
    self::$freepbx->filestore = $mockfilestore; 

    $response = $this->request("{
        fetchFileStoreDetails(id: \"FTP_12324\") {
          status
          message
          serverName
          hostName
          description
          port
          userName
          password
          fileStoreType
          path
          transferMode
          timeout
          }
      }");
      
    $json = (string)$response->getBody();
    $this->assertEquals('{"errors":[{"message":"FTP instance does not exists","status":false}]}',$json);
      
    $this->assertEquals(400, $response->getStatusCode());
  }
}