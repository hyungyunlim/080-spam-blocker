<?php
namespace FreePBX\modules\Fax\Api\Rest;
use FreePBX\modules\Api\Rest\Base;
class Fax extends Base {
	protected $module = 'fax';
	public static function getScopes() {
		return [
			'read:settings' => [
				'description' => _('Read fax settings'),
			],
			'write:settings' => [
				'description' => _('Write fax settings'),
			]
		];
	}
	public function setupRoutes($app) {

		/**
		 * @verb GET
		 * @returns - list of fax settings
		 * @uri /fax
		 */
		$app->get('/', function($request, $response, $args)
		{
			$response->getBody()->write(json_encode(\FreePBX::Fax()->getSettings()));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkReadScopeMiddleware('settings'));

		/**
		* @verb GET
		 * @returns - list of fax related modules that may be installed
		 * @uri /fax/detect
		 */
		$app->get('/detect', function($request, $response, $args)
		{
			$response->getBody()->write(json_encode(\FreePBX::Fax()->faxDetect()));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkReadScopeMiddleware('settings'));

		/**
		 * Updates the fax settings
		 * @verb POST
		 * @returns - the freshly created settings
		 * @uri /fax
		 */
		$app->post('/', function($request, $response, $args)
		{
			$params = $request->getParsedBody();
			$response->getBody()->write(json_encode(\FreePBX::Fax()->setSettings($params)));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkWriteScopeMiddleware('settings'));
	}
}
