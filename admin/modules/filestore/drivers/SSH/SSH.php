<?php
namespace FreePBX\modules\Filestore\drivers\SSH;
use League\Flysystem\Filesystem;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\ConnectionProvider;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use FreePBX\modules\Filestore\drivers\FlysystemBase;
use FreePBX\modules\Filestore\drivers\CustomCachedAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter as SymfonyCacheAdapter;
// use League\Flysystem\Cached\CachedAdapter;
use League\Flysystem\Cached\Storage\Memory as MemoryStore;
class SSH extends FlysystemBase {
	protected static $path = __DIR__;
	protected static $validKeys = [
		'name' => '',
		'desc' => 'SSH Server',
		'host' => '',
		'port' => '22',
		'user' => '',
		'key' => '',
		'path' => '',
		'type' => 'ssh',
		'readonly' => array(),
		'immortal' => '',
		'enabled' => 'yes',
	];

	/**
	 * Weather an implintation is supported in this driver
	 * @param  string $method the method "all,backup,readonly,writeonly"
	 * @return bool method is/not supported
	 */
	public function methodSupported($method){
		$permissions = array(
			'all',
			'read',
			'write',
			'backup',
			'general'
		);
		return in_array($method, $permissions);
	}

	//SSH STUFF
	public function getHandler(){
		if(isset($this->handler)){
			return $this->handler;
		}
      	$home = posix_getpwuid(posix_getuid());
		$path = $this->translatePath($this->config['path']);
		$connectionProvider = new SftpConnectionProvider(
			$this->config['host'],
			$this->config['user'],
			$this->config['user'],
			str_replace("~", $home["dir"], $this->config['key']), 
			null, 
			$this->config['port'], 
			false, 
			30, 
			10, 
			null, 
			null,
		);

		$visibilityConverter = PortableVisibilityConverter::fromArray([
			'file' => [
				'public' => 0644,
				'private' => 0600,
			],
			'dir' => [
				'public' => 0755,
				'private' => 0700,
			],
		]);
		// Create the original adapter
		$originalAdapter = new SftpAdapter($connectionProvider, $path, $visibilityConverter);
    	// // Create the cache adapter (using Symfony Cache in this example)
    	 $cachePool = new SymfonyCacheAdapter('flysystem_cache');
    	// // // Decorate the adapter with the custom cache adapter
    	$adapter = new CustomCachedAdapter($originalAdapter, $cachePool);
		$this->handler = new Filesystem($adapter);
		return $this->handler;
	}
}