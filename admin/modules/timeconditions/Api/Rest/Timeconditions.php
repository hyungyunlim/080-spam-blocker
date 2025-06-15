<?php
namespace FreePBX\modules\Timeconditions\Api\Rest;
use FreePBX\modules\Api\Rest\Base;
class Timeconditions extends Base {
	protected $module = 'timeconditions';
	public function setupRoutes($app) {

		/**
		 * @verb GET
		 * @returns - a list of timeconditions settings
		 * @uri /timeconditions
		 */
		$app->get('/', function ($request, $response, $args) {
			\FreePBX::Modules()->loadFunctionsInc('timeconditions');
			$timeconditions = timeconditions_list();
			$timeconditions = $timeconditions ?: false;
			$response->getBody()->write(json_encode($timeconditions));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkAllReadScopeMiddleware());

		/**
		 * @verb GET
		 * @returns - timeconditions state
		 * @uri /timeconditions/:id
		 */
		$app->get('/{id}', function ($request, $response, $args) {
			$timeconditions = [];
			\FreePBX::Modules()->loadFunctionsInc('timeconditions');
			$tcstate = timeconditions_get_state($args['id']);
			if ($tcstate !== false) {
				$timeconditions = [];
				$timeconditions['state'] = $tcstate;
			}
			$timeconditions = $timeconditions ?: false;
			$response->getBody()->write(json_encode($timeconditions));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkAllReadScopeMiddleware());

		/**
		 * @verb PUT
		 * @uri /timeconditions/:id
		 */
		$app->put('/{id}', function ($request, $response, $args) {
			\FreePBX::Modules()->loadFunctionsInc('timeconditions');
			$params = $request->getParsedBody();
			$response->getBody()->write(json_encode(timeconditions_set_state($args['id'] ?? '', $params['state'] ?? '')));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkAllWriteScopeMiddleware());
	}
}
