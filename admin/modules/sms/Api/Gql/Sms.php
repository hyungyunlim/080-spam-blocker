<?php

namespace FreePBX\modules\Sms\Api\Gql;

use GraphQLRelay\Relay;
use GraphQL\Type\Definition\Type;
use FreePBX\modules\Api\Gql\Base;
use GraphQL\Type\Definition\EnumType;

class Sms extends Base
{
    protected $module = 'sms';

    public function mutationCallback()
    {
        if ($this->checkAllWriteScope()) {
            return fn() => [
                'addSmsWebhook' => Relay::mutationWithClientMutationId([
                    'name' => 'addSmsWebhook',
                    'description' => _('add new sms web hook'),
                    'inputFields' => $this->getAddSmsInputFields(),
                    'outputFields' => $this->getSmsOutputFields(),
                    'mutateAndGetPayload' => function ($input) {
                        $res = $this->freepbx->sms->addWebhook($input);
                        if ($res['status']) {
                            return ['status' => true, 'message' => _("Webhook added successfully..!!")];
                        } else {
                            return ['status' => false, 'message' => $res['message']];
                        }
                    }
                ]),
                'updateSmsWebhook' => Relay::mutationWithClientMutationId([
                    'name' => 'updateSmsWebhook',
                    'description' => _('Update sms web hook'),
                    'inputFields' => $this->getUpdateSmsInputFields(),
                    'outputFields' => $this->getSmsOutputFields(),
                    'mutateAndGetPayload' => function ($input) {
                        $res = $this->freepbx->sms->updateWebhook($input);
                        if ($res['status']) {
                            return ['status' => true, 'message' => _("Webhook updated successfully..!!")];
                        } else {
                            return ['status' => false, 'message' => $res['message']];
                        }
                    }
                ]),
                'deleteSmsWebhook' => Relay::mutationWithClientMutationId([
                    'name' => 'deleteSmsWebhook',
                    'description' => _('Delete an SMS web hook'),
                    'inputFields' => ['id' => ['type' => Type::nonNull(Type::id()), 'description' => _('SMS webhook id to be deleted')]],
                    'outputFields' => $this->getSmsOutputFields(),
                    'mutateAndGetPayload' => function ($input) {
                        $existingWebhook = $this->freepbx->sms->getWebhookById($input['id']);
                        if (!empty($existingWebhook)) {
                            $res = $this->freepbx->sms->deleteWebHook($input['id']);
                            if ($res) {
                                return ['status' => true, 'message' => _('Sms webhook deleted successfully')];
                            } else {
                                return ['status' => false, 'message' => _('Sms webhook does not exists')];
                            }
                        } else {
                            return ['status' => false, 'message' => _('Sms webhook does not exists')];
                        }
                    }
                ]),
            ];
        }
    }

    /**
     * queryCallback
     *
     * @return void
     */
    public function queryCallback()
    {
        if ($this->checkAllReadScope()) {
            return fn() => [
                'fetchAllSmsWebhook' => [
                    'type' => $this->typeContainer->get('sms')->getConnectionType(),
                    'resolve' => function ($root, $args) {
                        $res = $this->freepbx->sms->getAllWebhooks();
                        if (!empty($res)) {
                            return ['message' => _("List of sms webhooks"), 'status' => true, 'response' => $res];
                        } else {
                            return ['message' => _('Sorry unable to find the sms webhooks data'), 'status' => false];
                        }
                    }
                ],
                'fetchSmsWebhook' => [
                    'type' => $this->typeContainer->get('sms')->getConnectionType(),
                    'args' => [
                        'id' => [
                            'type' => Type::nonNull(Type::id()),
                            'description' => _('The Sms Webhook Id to be fetched'),
                        ]
                    ],
                    'resolve' => function ($root, $args) {
                        $res = $this->freepbx->sms->getWebhookById($args['id']);
                        if (!empty($res)) {
                            return ['message' => _("Sms webhooks data found successfully..!!"), 'status' => true, 'response' => $res];
                        } else {
                            return ['message' => _('Sorry unable to find the sms webhooks data'), 'status' => false];
                        }
                    }
                ]
            ];
        }
    }

    public function initializeTypes()
    {
        $user = $this->typeContainer->create('sms');
        $user->setDescription('%description%');

        $user->addInterfaceCallback(fn() => [$this->getNodeDefinition()['nodeInterface']]);

        $user->addFieldCallback(fn() => [
            'status' => [
                'type' => Type::boolean(),
                'description' => _('Status of the request')
            ],
            'message' => [
                'type' => Type::string(),
                'description' => _('Message for the request'),
            ],
            'id' => [
                'type' => Type::nonNull(Type::Id()),
                'description' => _('Returns sms webhook id'),
                'resolve' => function ($row) {
                    if (isset($row['id'])) {
                        return $row['id'];
                    } elseif (isset($row['response'])) {
                        return  $row['response']['id'];
                    }
                    return null;
                }
            ],
            'webhookUrl' => [
                'type' => Type::string(),
                'description' => _('Returns sms webhook url'),
                'resolve' => function ($row) {
                    if (isset($row['webhookUrl'])) {
                        return $row['webhookUrl'];
                    } elseif (isset($row['response'])) {
                        return  $row['response']['webhookUrl'];
                    }
                    return null;
                }
            ],
            'enablewebHook' => [
                'type' => Type::boolean(),
                'description' => _('Status of sms webhook'),
                'resolve' => function ($row) {
                    if (isset($row['enablewebHook'])) {
                        return $row['enablewebHook'];
                    } elseif (isset($row['response'])) {
                        return  $row['response']['enablewebHook'];
                    }
                    return null;
                }
            ],
            'dataToBeSentOn' => [
                'type' => $this->getOptions('output'),
                'description' => _('On which SMS events fire the webhook'),
                'resolve' => function ($row) {
                    if (isset($row['dataToBeSentOn'])) {
                        return $row['dataToBeSentOn'];
                    } elseif (isset($row['response'])) {
                        return  $row['response']['dataToBeSentOn'];
                    }
                    return null;
                }
            ]
        ]);

        $user->setConnectionResolveNode(fn($edge) => $edge['node']);

        $user->setConnectionFields(fn() => [
            'message' => [
                'type' => Type::string(),
                'description' => _('Message for the request')
            ],
            'status' => [
                'type' => Type::boolean(),
                'description' => _('Status for the request')
            ],
            'id' => [
                'type' => Type::nonNull(Type::Id()),
                'description' => _('Returns sms webhook id'),
                'resolve' => function ($row) {
                    if (isset($row['id'])) {
                        return $row['id'];
                    } elseif (isset($row['response'])) {
                        return  $row['response']['id'];
                    }
                    return null;
                }
            ],
            'webhookDetails' => [
                'type' => Type::listOf($this->typeContainer->get('sms')->getObject()),
                'description' => _('List of sms webhooks'),
                'resolve' => function ($root, $args) {
                    $data = array_map(fn($row) => $row, $root['response'] ?? []);
                    return $data;
                }
            ],
            'webhookUrl' => [
                'type' => Type::nonNull(Type::string()),
                'description' => _('Returns sms webhook url'),
                'resolve' => function ($row) {
                    if (isset($row['webhookUrl'])) {
                        return $row['webhookUrl'];
                    } elseif (isset($row['response'])) {
                        return  $row['response']['webhookUrl'];
                    }
                    return null;
                }
            ],
            'enablewebHook' => [
                'type' => Type::boolean(),
                'description' => _('Status of sms webhook'),
                'resolve' => function ($row) {
                    if (isset($row['enablewebHook'])) {
                        return $row['enablewebHook'];
                    } elseif (isset($row['response'])) {
                        return  $row['response']['enablewebHook'];
                    }
                    return null;
                }
            ],
            'dataToBeSentOn' => [
                'type' => $this->getOptions('outputSetConnectionFields'),
                'description' => _('On which SMS events fire the webhook'),
                'resolve' => function ($row) {
                    if (isset($row['dataToBeSentOn'])) {
                        return $row['dataToBeSentOn'];
                    } elseif (isset($row['response'])) {
                        return  $row['response']['dataToBeSentOn'];
                    }
                    return null;
                }
            ]
        ]);
    }

    /**
     * getAddSmsInputFields
     *
     * @return void
     */
    private function getAddSmsInputFields()
    {
        return [
            'webHookBaseurl' => [
                'type' => Type::nonNull(Type::string()),
                'description' => _('Returns sms webhook url')
            ],
            'enablewebHook' => [
                'type' => Type::nonNull(Type::boolean()),
                'description' => _('Status of sms webhook')
            ],
            'dataToBeSentOn' => [
                'type' => $this->getOptions('add'),
                'description' => _('On which SMS events fire the webhook')
            ]
        ];
    }

    /**
     * getUpdateSmsInputFields
     *
     * @return void
     */
    private function getUpdateSmsInputFields()
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::string()),
                'description' => _('A id used to identify sms webhook')
            ],
            'webHookBaseurl' => [
                'type' => Type::nonNull(Type::string()),
                'description' => _('Returns sms webhook url')
            ],
            'enablewebHook' => [
                'type' => Type::nonNull(Type::boolean()),
                'description' => _('Status of sms webhook')
            ],
            'dataToBeSentOn' => [
                'type' => $this->getOptions('update'),
                'description' => _('On which SMS events fire the webhook')
            ]
        ];
    }

    /**
     * getSmsOutputFields
     *
     * @return void
     */
    private function getSmsOutputFields()
    {
        return [
            'status' => [
                'type' => Type::boolean(),
                'resolve' => fn($payload) => $payload['status']
            ],
            'message' => [
                'type' => Type::string(),
                'resolve' => fn($payload) => $payload['message']
            ]
        ];
    }

    private function getOptions($type)
    {
        return new EnumType([
            'name' => "dataToBeSentOnValuesFor$type",
            'description' => _('On which SMS events fire the webhook'),
            'values' => [
                'send' => [
                    'value' => 'send',
                    'description' => _('When SMS is sent')
                ],
                'receive' => [
                    'value' => 'receive',
                    'description' => _("When SMS is received")
                ],
                'both' => [
                    'value' => 'both',
                    'description' => _("On both event")
                ]
            ]
        ]);
    }
}
