<?php

namespace FreePBX\modules\Api\Rest;

use DI\Container;
use League\OAuth2\Server\Middleware\ResourceServerMiddleware;
use FreePBX\modules\Api\Oauth\Repositories\AccessTokenRepository;
use League\OAuth2\Server\ResourceServer;

use DirectoryIterator;

use Slim\App;
use Slim\Factory\AppFactory;
use Psr\Container\ContainerInterface;
#[\AllowDynamicProperties]
class Api {
	public function __construct($freepbx, $publicKey) {
		$this->freepbx = $freepbx;
		$this->publicKey = $publicKey;
	}

	public function getValidScopes() {
		$modules = $this->getAPIClasses();
		$scopes = [];
		foreach($modules as $info) {
			if(!isset($scopes[$info['modname']])) {
				$scopes[$info['modname']] = [];
			}
			$scopes[$info['modname']] = array_merge($scopes[$info['modname']],$info['object']->getScopes());
		}
		return $scopes;
	}

	public function execute() {
		$_SERVER['QUERY_STRING'] = str_replace('module=api&command='.$_GET['command'].'&route='.$_GET['route'],'',(string) $_SERVER['QUERY_STRING']);
		$_SERVER['REQUEST_URI'] = '/api/rest'.(!empty($_GET['route']) ? '/'.$_GET['route'] : '');

		$config = [
			'settings' => [
				'displayErrorDetails' => !empty($_REQUEST['debug'])
			]
		];

		$accessTokenRepository = new AccessTokenRepository($this->freepbx->api);
		$publicKeyPath = 'file://' . $this->publicKey;
		$server = new ResourceServer(
			$accessTokenRepository,
				$publicKeyPath
		);

		AppFactory::setSlimHttpDecoratorsAutomaticDetection(false);
		$container = new Container();
		AppFactory::setContainer($container);
		$app = AppFactory::create();
		$app->addBodyParsingMiddleware();
		$app->add(new ResourceServerMiddleware($server));
		$container->set('setupRest', function (Container $container) {
			return function($app) use ($container) {
				$this->setupRest($app);
			};
		});
		$container->set('freepbx', $this->freepbx);
		$self = $this;

		$app->group('/api/rest', function ($app) use ($self) {
			$self->setupRest($app);
		 });
		$app->run();
	}

	public function getAPIClasses() {
		if(!empty($this->classes)) {
			return $this->classes;
		}

		$webrootpath = $this->freepbx->Config->get('AMPWEBROOT');

		$fwcpath = $webrootpath . '/admin/libraries/Api/Rest';

		$classes = [];

		foreach (new DirectoryIterator($fwcpath) as $fileInfo) {
			// skip '.' and '..' entries in the directory
			if($fileInfo->isDot()) { continue; };
			// skip files that begin with '.', such as '.Donotdisturb.php.swp'
			if(str_starts_with($fileInfo->getBasename(), '.')) { continue; };
			$name = pathinfo($fileInfo->getFilename(),PATHINFO_FILENAME);
			$class = "FreePBX\\Api\\Rest\\".$name;
			$classes[] = [
				'modname' => 'framework',
				'class' => $class,
				'name' => $name
			];
		}

		$amodules = $this->freepbx->Modules->getActiveModules();
		foreach($amodules as $module){
			//Module Path
			$mpath = $webrootpath . '/admin/modules/' . $module['rawname'] . '/Api/Rest/';
			if (file_exists($mpath)){
				//Class files
				foreach (new DirectoryIterator($mpath) as $fileInfo) {
					if($fileInfo->isDot()) { continue; };
					$name = pathinfo($fileInfo->getFilename(),PATHINFO_FILENAME);
					$class = "FreePBX\\modules\\".$module['rawname']."\\Api\\Rest\\".$name;
					$classes[] = [
						'modname' => $module['rawname'],
						'class' => $class,
						'name' => $name
					];
				}
			}
		}
		ksort($classes);
		foreach($classes as $class) {
			$cls = $class['class'];
			$class['object'] = new $cls($this->freepbx,$class['modname']);
			$this->classes[] = $class;
		}
		return $this->classes;
	}

	private function setupRest($app) {
		$classes = $this->getAPIClasses();
		$groups = [];
		foreach($classes as $class) {
			$groups[$class['modname']][] = $class;
		}
		foreach($groups as $module => $classes) {
			$app->group('/'.$module, function ($app) use ($classes) {
				foreach($classes as $class) {
					$class['object']->setupRoutes($app);
				}
			});
		}
	}

	public function buildSlimApp($route, $command, $queryString)
	{
		$_SERVER['QUERY_STRING'] = str_replace('module=api&command=' . $command . '&route=' . $route, '', (string) $queryString);
		$_SERVER['REQUEST_URI'] = '/api' . (!empty($route) ? '/' . ltrim((string) $route, '/') : '');

		$config = [
			'settings' => [
				'displayErrorDetails' => !empty($_REQUEST['debug'])
			]
		];

		$accessTokenRepository = new AccessTokenRepository($this->freepbx->api);
		$publicKeyPath = 'file://' . $this->publicKey;
		$server = new ResourceServer(
			$accessTokenRepository,
			$publicKeyPath
		);

		$app = new App($config);
		$app->add(new ResourceServerMiddleware($server));

		$container = $app->getContainer();
		$container['setupRest'] = $container->protect(fn($app) => $this->setupRest($app));

		$container['freepbx'] = $this->freepbx;

		$app->group('/api/rest', function () {
			$this->setupRest($this);
		});

		return $app;
	}
}
