<?php

namespace FreePBX\modules\Filestore\drivers\S3;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use \FreePBX\modules\Filestore\drivers\FlysystemBase;

class S3 extends FlysystemBase
{
	protected static $path = __DIR__;
	protected static $validKeys = [
		"awsaccesskey" => '',
		"awssecret" => '',
		"desc" => '',
		"name" => '',
		"bucket" => '',
		"region" => '',
		'immortal' => '',
		'path' => '',
		'storageclass' => '',
		'customendpoint' => '',
		'customregion' => '',
		'enabled' => 'yes',
	];

	/**
	 * The display view for non setting items.
	 * @return string html
	 */
	public static function getDisplay($freepbx, $config)
	{
		$regions =
			[
				'US East (Ohio)' => 'us-east-2',
				'US East (N. Virginia)' => 'us-east-1',
				'AWS GovCloud (US-East)' => 'us-gov-east-1',
				'US West (N. California)' => 'us-west-1',
				'US West (Oregon)' => 'us-west-2',
				'AWS GovCloud West (US)' => 'us-gov-west-1',
				'AWS GovCloud East (US)' => 'us-gov-east-1',
				'Canada (Central)' => 'ca-central-1',
				'Africa (Cape Town)' => 'af-south-1',
				'Asia Pacific (Hong Kong)' => 'ap-east-1',
				'Asia Pacific (Jakarta)' => 'ap-southeast-3',
				'Asia Pacific (Mumbai)' => 'ap-south-1',
				'Asia Pacific (Osaka-Local)' => 'ap-northeast-3',
				'Asia Pacific (Seoul)' => 'ap-northeast-2',
				'Asia Pacific (Singapore)' => 'ap-southeast-1',
				'Asia Pacific (Sydney)' => 'ap-southeast-2',
				'Asia Pacific (Tokyo)' => 'ap-northeast-1',
				'China (Beijing)' => 'cn-north-1',
				'China (Ningxia)' => 'cn-northwest-1',
				'EU (Frankfurt)' => 'eu-central-1',
				'EU (Ireland)' => 'eu-west-1',
				'EU (London)' => 'eu-west-2',
				'EU (Milan)' => 'eu-south-1',
				'EU (Paris)' => 'eu-west-3',
				'EU (Stockholm)' => 'eu-north-1',
				'South America (São Paulo)' => 'sa-east-1',
				'Middle East (Bahrain)' => 'me-south-1',
			];
		$storageclasses = [
				'Standard' => 'STANDARD',
				'Intelligent-Tiering' => 'INTELLIGENT_TIERING',
				'Express One Zone' => 'EXPRESS_ONEZONE',
				'Standard-Infrequent Access' => 'STANDARD_IA',
				'One Zone-Infrequent Acess' => 'ONEZONE_IA',
				'Glacier Instant Retrieval' => 'GLACIER_IR',
				'Glacier Flexible Retrieval' => 'GLACIER',
				'Glacier Deep Archive' => 'DEEP_ARCHIVE',
				'Outpost' => 'OUTPOSTS',
				'Not Applicable (Uncommon)' => 'NOT_APPLICABLE',
			];
		if (empty($_GET['view'])) {
			return load_view(__DIR__ . '/views/grid.php');
		} else {
			$config['regions'] = $regions;
			$config['storageclasses'] = $storageclasses;
			return load_view(__DIR__ . '/views/form.php', $config);
		}
	}

	/**
	 * Weather an implintation is supported in this driver
	 * @param  string $method the method "all,backup,readonly,writeonly"
	 * @return bool method is/not supported
	 */
	public function methodSupported($method)
	{
		$permissions = array(
			'all',
			'read',
			'write',
			'backup',
			'general'
		);
		return in_array($method, $permissions);
	}


	//S3 STUFF
	public function getHandler()
	{
		if (isset($this->handler)) {
			return $this->handler;
		}
		$region = !empty($this->config['customregion']) ? $this->config['customregion'] : $this->config['region'];

		$config = [
			'region' => $region,
			'version' => 'latest'
		];

		/** Use default credential provider chain unless user has provided credentials */
		$accessKey = $this->config['awsaccesskey'];
		if (!empty($accessKey)) {
			$config['credentials'] = [
				'key'	 => $accessKey,
				'secret' => trim($this->config['awssecret'])
			];
		}

		/** Set an endpoint if the user has specified one. */
		if (!empty($this->config['customendpoint'])) {
			$config['endpoint'] = $this->config['customendpoint'];
		}
		$adapterOptions = [];
		if(isset($this->config['storageclass']) && $this->config['storageclass'] !== 'NOT_APPLICABLE') {
			$adapterOptions['StorageClass'] = $this->config['storageclass'];
		}

		$client = new S3Client($config);
		// Decorate the adapter
		$this->config['path'] = $this->config['path'] === '/' ? '' : $this->config['path'];
		$adapter = new AwsS3V3Adapter($client, $this->config['bucket'], $this->config['path'], null, null, $adapterOptions);
		$this->handler = new Filesystem($adapter);
		return $this->handler;
	}
}
