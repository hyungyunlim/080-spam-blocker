<?php
namespace FreePBX\modules\Ucp\Api\Rest;
use FreePBX\modules\Api\Rest\Base;
use League\OAuth2\Server\AuthorizationServer;
use \Ramsey\Uuid\Uuid;
use \Ramsey\Uuid\Exception\UnsatisfiedDependencyException;
class Dashboard extends Base {
	protected $module = 'ucp';
	public static function getScopes() {
		return [
			'read:dashboard' => [
				'description' => _('Read UCP Dashboard Information'),
			],
			'write:dashboard' => [
				'description' => _('Write UCP Dashboard Information'),
			]
		];
	}
	public function setupRoutes($app) {
		//get all dashboards
		$freepbx = $this->freepbx;
		$app->get('/dashboard/tab', function ($request, $response, $args) use($freepbx) {
			$user = $request->getAttribute('oauth_user_id');
			$dashboards = $freepbx->Ucp->getSettingByID($user,'Global','dashboards');
			$dashboards = is_array($dashboards) ? $dashboards : [];
			$response->getBody()->write(json_encode($dashboards));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkReadScopeMiddleware('dashboard'));

		//update dashboard tab layout
		$app->post('/dashboard/tab/layout', function ($request, $response, $args) use($freepbx) {
			$order = $request->getParsedBody();
			$user = $request->getAttribute('oauth_user_id');
			$dashboards = $freepbx->Ucp->getSettingByID($user,'Global','dashboards');
			$dashboards = is_array($dashboards) ? $dashboards : [];
			@usort($dashboards, function($a,$b) use ($order) {
				$keya = array_search($a['id'],$order);
				$keyb = array_search($b['id'],$order);
				return ($keya < $keyb) ? -1 : 1;
			});
			$freepbx->Ucp->setSettingByID($user,'Global','dashboards',$dashboards);
			$data = ["status" => true];
			$response->getBody()->write(json_encode($data));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkWriteScopeMiddleware('dashboard'));

		//add new dashboard
		$app->put('/dashboard/tab', function ($request, $response, $args) use($freepbx) {
			$params = $request->getParsedBody();
			$user = $request->getAttribute('oauth_user_id');
			$dashboards = $freepbx->Ucp->getSettingByID($user,'Global','dashboards');
			$dashboards = is_array($dashboards) ? $dashboards : [];
			$id = (string)Uuid::uuid4();
			$dashboards[] = ["id" => $id, "name" => $params['name']];
			$freepbx->Ucp->setSettingByID($user,'Global','dashboards',$dashboards);
			$data = ["status" => true, "id" => $id];
			$response->getBody()->write(json_encode($data));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkWriteScopeMiddleware('dashboard'));

		//update dashboard
		$app->post('/dashboard/tab/{dashboard_id}', function ($request, $response, $args) use($freepbx) {
			$params = $request->getParsedBody();
			$user = $request->getAttribute('oauth_user_id');
			$dashboards = $freepbx->Ucp->getSettingByID($user,'Global','dashboards');
			$dashboards = is_array($dashboards) ? $dashboards : [];
			$res = ["status" => false, "message" => "Invalid Dashboard ID"];
			foreach($dashboards as $k => $d) {
				if($d['id'] == $args['dashboard_id']) {
					$dashboards[$k]['name'] = $params['name'];
					$freepbx->Ucp->setSettingByID($user,'Global','dashboards',$dashboards);
					$res = ["status" => true, "id" => $d['id']];
					break;
				}
			}
			$response->getBody()->write(json_encode($res));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkWriteScopeMiddleware('dashboard'));

		//delete dashboard
		$app->delete('/dashboard/tab/{dashboard_id}', function ($request, $response, $args) use($freepbx) {
			$user = $request->getAttribute('oauth_user_id');
			$dashboards = $freepbx->Ucp->getSettingByID($user,'Global','dashboards');
			$dashboards = is_array($dashboards) ? $dashboards : [];
			$res = ["status" => false, "message" => "Invalid Dashboard ID"];
			foreach($dashboards as $k => $d) {
				if($d['id'] == $args['dashboard_id']) {
					unset($dashboards[$k]);
					$freepbx->Ucp->setSettingByID($user,'Global','dashboards',$dashboards);
					$freepbx->Ucp->setSettingByID($user,'Global','dashboard-layout-'.$args['dashboard_id'],null);
					$res = ["status" => true];
					break;
				}
			}
			$response->getBody()->write(json_encode($res));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkWriteScopeMiddleware('dashboard'));

		//get dashboard widgets
		$app->get('/dashboard/{dashboard_id}/widget', function ($request, $response, $args) use($freepbx) {
			$user = $request->getAttribute('oauth_user_id');
			$widgets = $freepbx->Ucp->getSettingByID($user,'Global','dashboard-layout-'.$args['dashboard_id']);
			$widgets = json_decode($widgets,true, 512, JSON_THROW_ON_ERROR);
			$response->getBody()->write(json_encode($widgets));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkReadScopeMiddleware('dashboard'));

		//update dashboard widget layout
		$app->post('/dashboard/{dashboard_id}/widget/layout', function ($request, $response, $args) use($freepbx) {
			$user = $request->getAttribute('oauth_user_id');
			$freepbx->Ucp->setSettingByID($user,'Global','dashboard-layout-'.$args['dashboard_id'],json_encode($request->getParsedBody(), JSON_THROW_ON_ERROR));
			$response->getBody()->write(json_encode(["status" => true]));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkWriteScopeMiddleware('dashboard'));

		//get widget content
		$app->get('/dashboard/{dashboard_id}/widget/{widget_id}/content', function ($request, $response, $args) use($freepbx) {
			$user = $request->getAttribute('oauth_user_id');
			$ucp = $freepbx->Ucp->getUCPObject($user);

			$widgets = $freepbx->Ucp->getSettingByID($user,'Global','dashboard-layout-'.$args['dashboard_id']);

			$widgets = json_decode($widgets,true, 512, JSON_THROW_ON_ERROR);

			foreach($widgets as $widget) {
				if($widget['id'] === $args['widget_id']) {
					if($ucp->Modules->moduleHasMethod($widget['rawname'], 'getWidgetDisplay')) {
						$module = ucfirst(strtolower((string) $widget['rawname']));
						$json_data = $ucp->Modules->$module->getWidgetDisplay($widget['widget_type_id'], $widget['id']);
						$response->getBody()->write(json_encode($json_data));
						return $response->withHeader('Content-Type', 'application/json');
					}
				}
			}
			$response->getBody()->write(json_encode([]));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkReadScopeMiddleware('dashboard'));

		//get widget setting content
		$app->get('/dashboard/{dashboard_id}/widget/{widget_id}/setting/content', function ($request, $response, $args) use($freepbx) {
			$user = $request->getAttribute('oauth_user_id');
			$ucp = $freepbx->Ucp->getUCPObject($user);

			$widgets = $freepbx->Ucp->getSettingByID($user,'Global','dashboard-layout-'.$args['dashboard_id']);

			$widgets = json_decode($widgets,true, 512, JSON_THROW_ON_ERROR);

			foreach($widgets as $widget) {
				if($widget['id'] === $args['widget_id']) {
					if($ucp->Modules->moduleHasMethod($widget['rawname'], 'getWidgetDisplay')) {
						$module = ucfirst(strtolower((string) $widget['rawname']));
						$json_data = $ucp->Modules->$module->getWidgetSettingsDisplay($widget['widget_type_id'], $widget['id']);
						$response->getBody()->write(json_encode($json_data));
						return $response->withHeader('Content-Type', 'application/json');
					}
				}
			}			
			$response->getBody()->write(json_encode([]));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkReadScopeMiddleware('dashboard'));

		//get side widgets
		$app->get('/dashboard/side', function ($request, $response, $args) use($freepbx) {
			$user = $request->getAttribute('oauth_user_id');
			$dashboards = $freepbx->Ucp->getSettingByID($user,'Global','dashboard-simple-layout');
			$dashboards = json_decode($dashboards,true, 512, JSON_THROW_ON_ERROR);
			$dashboards = is_array($dashboards) ? $dashboards : [];
			$response->getBody()->write(json_encode($dashboards));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkReadScopeMiddleware('dashboard'));

		//update side layout
		$app->post('/dashboard/side/layout', function ($request, $response, $args) use($freepbx) {
			$user = $request->getAttribute('oauth_user_id');
			$freepbx->Ucp->setSettingByID($user,'Global','dashboard-simple-layout',json_encode($request->getParsedBody(), JSON_THROW_ON_ERROR));
			$response->getBody()->write(json_encode(['status' => true]));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkWriteScopeMiddleware('dashboard'));

		//get side widget content
		$app->get('/dashboard/side/{widget_id}/content', function ($request, $response, $args) use($freepbx) {
			$user = $request->getAttribute('oauth_user_id');
			$widgets = $freepbx->Ucp->getSettingByID($user,'Global','dashboard-simple-layout');
			$widgets = json_decode($widgets,true, 512, JSON_THROW_ON_ERROR);

			$ucp = $freepbx->Ucp->getUCPObject($user);

			foreach($widgets as $widget) {
				if($widget['id'] === $args['widget_id']) {
					if($ucp->Modules->moduleHasMethod($widget['rawname'], 'getSimpleWidgetDisplay')) {
						$module = ucfirst(strtolower((string) $widget['rawname']));
						$response->getBody()->write(json_encode($ucp->Modules->$module->getSimpleWidgetDisplay($widget['widget_type_id'], $widget['id'])));
						return $response->withHeader('Content-Type', 'application/json');
					}
					if($ucp->Modules->moduleHasMethod($widget['rawname'], 'getWidgetDisplay')) {
						$module = ucfirst(strtolower((string) $widget['rawname']));
						$response->getBody()->write(json_encode($ucp->Modules->$module->getWidgetDisplay($widget['widget_type_id'], $widget['id'])));
						return $response->withHeader('Content-Type', 'application/json');
					}
				}
			}

			$response->getBody()->write(json_encode([]));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkReadScopeMiddleware('dashboard'));

		//get side widget setting content
		$app->get('/dashboard/side/{widget_id}/setting/content', function ($request, $response, $args) use($freepbx) {
			$user = $request->getAttribute('oauth_user_id');
			$ucp = $freepbx->Ucp->getUCPObject($user);

			$widgets = $freepbx->Ucp->getSettingByID($user,'Global','dashboard-simple-layout');

			$widgets = json_decode($widgets,true, 512, JSON_THROW_ON_ERROR);

			foreach($widgets as $widget) {
				if($widget['id'] === $args['widget_id']) {
					if($ucp->Modules->moduleHasMethod($widget['rawname'], 'getSimpleWidgetSettingsDisplay')) {
						$module = ucfirst(strtolower((string) $widget['rawname']));
						$response->getBody()->write(json_encode($ucp->Modules->$module->getSimpleWidgetSettingsDisplay($widget['widget_type_id'], $widget['id'])));
						return $response->withHeader('Content-Type', 'application/json');
					}
				}
			}
			$response->getBody()->write(json_encode([]));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkReadScopeMiddleware('dashboard'));
	}
}
