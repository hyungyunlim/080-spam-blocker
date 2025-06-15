<?php

namespace FreePBX\modules\Pinsets\Api\Rest;

use FreePBX\modules\Api\Rest\Base;

class Pinsets extends Base {
    protected $module = 'pinsets';

    public function __construct($freepbx, $module) {
        parent::__construct($freepbx, $module);
        $this->freepbx->Modules->loadFunctionsInc($module);
    }

    public function setupRoutes($app) {
        /**
         * @verb    GET
         * @returns - the pinset list
         * @uri     /pinsets
         */
        $freepbx = $this->freepbx;
        $app->get('/', function($request, $response, $args) use($freepbx) {
            $list = [];
            $pinsets = $freepbx->Pinsets->listPinsets();

            foreach ($pinsets as $pinset) {
                $entry = new \stdClass();
                $entry->id = $pinset['pinsets_id'];
                $entry->description = $pinset['description'];
                $list[$pinset['pinsets_id']] = $entry;
            }

            $list = !empty($list) ? $list : false;
            $response->getBody()->write(json_encode($list));
            return $response->withHeader('Content-Type', 'application/json');
        })->add($this->checkAllReadScopeMiddleware());

        /**
         * @verb    GET
         * @returns - a list of pinsets password
         * @uri     /pinsets/:id
         */
        $app->get('/{id}', function($request, $response, $args) {
            $pinset = pinsets_get($args['id']);
            if ($pinset) {
                $entry = new \stdClass();
                $entry->id = $pinset['pinsets_id'];
                $entry->passwords = $pinset['passwords'];
                $entry->addtocdr = $pinset['addtocdr'];
                $entry->deptname = $pinset['deptname'];
            }

            $entry = !empty($entry) ? $entry : false;
            $response->getBody()->write(json_encode($entry));
            return $response->withHeader('Content-Type', 'application/json');
        })->add($this->checkAllReadScopeMiddleware());

        /**
         * @verb    PUT
         * @returns - the result of updating the pinset
         * @uri     /pinsets/:id
         */
        $app->put('/{id}', function($request, $response, $args) {
            $pinset = pinsets_get($args['id']);
            if (empty($pinset)) {
                $response->getBody()->write(json_encode(false));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $params = $request->getParsedBody();
            if (isset($params['description'])) {
                $pinset['description'] = $params['description'];
            }
            if (isset($params['passwords'])) {
                $pinset['passwords'] = $params['passwords'];
            }
            if (isset($params['addtocdr'])) {
                $pinset['addtocdr'] = $params['addtocdr'];
            }
            if (isset($params['deptname'])) {
                $pinset['deptname'] = $params['deptname'];
            }

            pinsets_edit($args['id'], $pinset);
            needreload();
            $response->getBody()->write(json_encode(true));
            return $response->withHeader('Content-Type', 'application/json');
        })->add($this->checkAllReadScopeMiddleware());
    }
}
