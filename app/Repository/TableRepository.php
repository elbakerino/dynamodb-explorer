<?php declare(strict_types=1);

namespace App\Repository;

use App\Services\DynamoService;
use Aws\DynamoDb\Exception\DynamoDbException;
use Ramsey\Uuid\Uuid;

class TableRepository {
    protected DynamoService $dynamo;
    protected string $table;

    public function __construct(DynamoService $dynamo, string $table) {
        $this->dynamo = $dynamo;
        $this->table = $table;
    }

    protected function timestamp(): string {
        return (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }

    protected function makeTable(string $name): array {
        $ts = $this->timestamp();
        $uuid = Uuid::uuid4()->toString();
        return [
            'uuid' => 'table#' . $uuid,
            'data_key' => 'v0#meta',
            'name' => $name,
            'table_meta_name' => $name,
            'created_at' => $ts,
            'updated_at' => $ts,
        ];
    }

    protected function makeTableShare(string $full_uuid, string $user, string $shared_level): array {
        $ts = $this->timestamp();
        return [
            'uuid' => $full_uuid,
            'data_key' => 'shared#' . $user,
            'shared_with' => $user,
            'shared_level' => $shared_level,
            'created_at' => $ts,
            'updated_at' => $ts,
        ];
    }

    /**
     * @throws \JsonException
     */
    protected function hydrate($item): array {
        $assoc_item = $this->dynamo->itemToArray($item);
        $assoc_item['uuid'] = substr($assoc_item['uuid'], strlen('table#'));
        return $assoc_item;
    }

    /**
     * @throws \JsonException
     */
    public function create(string $name, string $user): array {
        $table_raw = $this->makeTable($name);
        $item = $this->dynamo->arrayToItem($table_raw);
        $shared_raw = $this->makeTableShare($table_raw['uuid'], $user, 'owner');
        $shared_item = $this->dynamo->arrayToItem($shared_raw);
        try {
            $response = $this->dynamo->client()->putItem([
                'TableName' => $this->table,
                'Item' => $item,
                'ConditionExpression' => '#uuid <> :uuid__val AND #data_key <> :data_key__val',
                'ExpressionAttributeNames' => [
                    '#uuid' => 'uuid',
                    '#data_key' => 'data_key',
                ],
                'ExpressionAttributeValues' => [
                    ':uuid__val' => $item['uuid'],
                    ':data_key__val' => $item['data_key'],
                ],
                'ReturnValues' => 'NONE',// default for put item
                //'ReturnValues' => 'ALL_OLD',
            ]);
        } catch(DynamoDbException $e) {
            if($e->getAwsErrorShape() && $e->getAwsErrorShape()->offsetGet('name') === 'ConditionalCheckFailedException') {
                throw new \RuntimeException('Table already exists');
            }
            throw $e;
        }
        if($response->offsetGet('@metadata')['statusCode'] === 200) {
            $response_share = $this->dynamo->client()->putItem([
                'TableName' => $this->table,
                'Item' => $shared_item,
            ]);
            $table_raw['uuid'] = substr($table_raw['uuid'], strlen('table#'));
            return ['meta' => $table_raw];
        }

        throw new \RuntimeException('Table create error, DB status: ' . $response->offsetGet('@metadata')['statusCode']);
    }

    /**
     * @throws \JsonException
     */
    public function listForUser(string $email, string $direction, int $per_page): array {
        $query = [
            'TableName' => $this->table,
            'IndexName' => 'table_shares',
            'KeyConditionExpression' => '#uuid__key = :uuid__val',
            'ScanIndexForward' => $direction !== 'desc',// `true` = `asc`, `false` = `desc`
            'ExpressionAttributeNames' => [
                '#uuid__key' => 'shared_with',
            ],
            'ExpressionAttributeValues' => [
                ':uuid__val' => ['S' => 'user#' . $email],
            ],
            'Limit' => $per_page,
        ];

        $res = $this->dynamo->client()->query($query);
        $items = $res->offsetGet('Items');
        $share_objects = array_map(fn($item) => $this->hydrate($item), $items);

//        $share_response = [
//            'size' => (int)$res->offsetGet('@metadata')['headers']['content-length'],
//            'per_page' => $per_page,
//            'last_evaluated_key' => $res->offsetGet('LastEvaluatedKey') ? $this->dynamo->itemToArray($res->offsetGet('LastEvaluatedKey')) : null,
//            //'items' => array_map(fn($item) => $this->hydrate($item), $items),
//        ];

        $keys = [];
        foreach($share_objects as $share_object) {
            $keys[] = [
                'uuid' => ['S' => 'table#' . $share_object['uuid']],
                'data_key' => ['S' => 'v0#meta'],
            ];
        }
        $batch_res = $this->dynamo->client()->batchGetItem([
            'RequestItems' => [
                $this->table => [
                    /*'AttributesToGet' => [''],
                    'ExpressionAttributeNames' => [
                        '#uuid' => 'uuid',
                    ],*/
                    'Keys' => $keys,
                ],
            ],
        ])->toArray();
        if(!isset($batch_res['Responses'][$this->table])) {
            throw new \RuntimeException('Request to list the tables failed');
        }

        return array_map(fn($item) => $this->hydrate($item), $batch_res['Responses'][$this->table]);
    }

    /**
     * @throws \JsonException
     */
    public function getDetails(string $email): array {
        $query = [
            'TableName' => $this->table,
            'KeyConditionExpression' => '#uuid__key = :uuid__val',
            'ExpressionAttributeNames' => [
                '#uuid__key' => 'uuid',
            ],
            'ExpressionAttributeValues' => [
                ':uuid__val' => ['S' => 'user#' . $email],
            ],
        ];
        $res = $this->dynamo->client()->query($query);
        $items = $res->offsetGet('Items');
        if(!isset($items[0])) {
            throw new \RuntimeException('db entry not found');
        }

        return $this->hydrate($items[0]);
    }

    /**
     * @param string $uuid
     * @return array
     * @throws \JsonException
     */
    public function getFullDetails(string $uuid): array {
        $query = [
            'TableName' => $this->table,
            'KeyConditionExpression' => '#uuid__key = :uuid__val',
            'ExpressionAttributeNames' => [
                '#uuid__key' => 'uuid',
            ],
            'ExpressionAttributeValues' => [
                ':uuid__val' => ['S' => 'table#' . $uuid],
            ],
        ];
        $res = $this->dynamo->client()->query($query);
        $items = $res->offsetGet('Items');
        if(empty($items)) {
            throw new \RuntimeException('db entry not found');
        }

        // todo: hydrate with injectable factories
        $items = array_map(fn($item) => $this->hydrate($item), $items);
        $table = null;
        $shares = [];
        foreach($items as $data) {
            if(!isset($data['data_key'])) {
                continue;
            }
            if(strpos($data['data_key'], 'v0#meta') === 0) {
                $table[substr($data['data_key'], 3)] = $data;
            } else if(strpos($data['data_key'], 'v0#schema') === 0) {
                if(isset($data['schema'])) {
                    $data['schema'] = json_decode($data['schema'], false, 512, JSON_THROW_ON_ERROR);
                }
                $table[substr($data['data_key'], 3)] = $data;
            } else if(strpos($data['data_key'], 'v0#preset#') === 0) {
                if(isset($data['display_keys'])) {
                    $data['display_keys'] = json_decode($data['display_keys'], false, 512, JSON_THROW_ON_ERROR);
                }
                if(!isset($table['presets'])) {
                    $table['presets'] = [];
                }
                $table['presets'][] = $data;
            } else if(strpos($data['data_key'], 'v0#color#') === 0) {
                if(isset($data['color_sk'])) {
                    $data['color_sk'] = json_decode($data['color_sk'], false, 512, JSON_THROW_ON_ERROR);
                }
                if(isset($data['color_pk'])) {
                    $data['color_pk'] = json_decode($data['color_pk'], false, 512, JSON_THROW_ON_ERROR);
                }
                if(!isset($table['colors'])) {
                    $table['colors'] = [];
                }
                $table['colors'][] = $data;
            } else if(strpos($data['data_key'], 'v0#exampleData') === 0) {
                if(isset($data['example_items'])) {
                    $data['example_items'] = json_decode($data['example_items'], false, 512, JSON_THROW_ON_ERROR);
                }
                $table[substr($data['data_key'], 3)] = $data;
            } else if(strpos($data['data_key'], 'v0#entity#') === 0) {
                //$table['entities'][] = $data;
                // todo: support many entities views in the future
                $table['entities'] = $data;
            } else if(strpos($data['data_key'], 'shared#') === 0) {
                $shares[] = $data;
            }
        }
        return [
            'size' => (int)$res->offsetGet('@metadata')['headers']['content-length'],
            'table' => $table,
            'shares' => $shares,
        ];
    }

    public function update(string $uuid, array $data) {
        try {
            $ts = $this->timestamp();
            $ops = [];
            if(isset($data['schema'])) {
                $ops[] = [
                    'TableName' => $this->table,
                    'Key' => [
                        'uuid' => ['S' => 'table#' . $uuid],
                        'data_key' => ['S' => 'v0#schema'],
                    ],
                    'UpdateExpression' => <<<TXT
SET
    #created_at = if_not_exists(created_at, :val__updated_at),
    #updated_at = :val__updated_at,
    #schema = :val__schema
TXT
                    ,
                    'ExpressionAttributeNames' => [
                        '#created_at' => 'created_at',
                        '#updated_at' => 'updated_at',
                        '#schema' => 'schema',
                    ],
                    'ExpressionAttributeValues' => [
                        ':val__updated_at' => ['S' => $ts],
                        ':val__schema' => ['S' => json_encode($data['schema'], JSON_THROW_ON_ERROR)],
                    ],
                    // UPDATED_NEW
                    'ReturnValues' => 'ALL_NEW',
                ];
            }

            if(isset($data['exampleData'])) {
                $ops[] = [
                    'TableName' => $this->table,
                    'Key' => [
                        'uuid' => ['S' => 'table#' . $uuid],
                        'data_key' => ['S' => 'v0#exampleData'],
                    ],
                    'UpdateExpression' => <<<TXT
SET
    #created_at = if_not_exists(created_at, :val__updated_at),
    #updated_at = :val__updated_at,
    example_items = :val__example_items
TXT
                    ,
                    'ExpressionAttributeNames' => [
                        '#created_at' => 'created_at',
                        '#updated_at' => 'updated_at',
                    ],
                    'ExpressionAttributeValues' => [
                        ':val__updated_at' => ['S' => $ts],
                        ':val__example_items' => ['S' => json_encode($data['exampleData'], JSON_THROW_ON_ERROR)],
                    ],
                    // UPDATED_NEW
                    'ReturnValues' => 'ALL_NEW',
                ];
            }

            if(isset($data['entities'])) {
                $ops[] = [
                    'TableName' => $this->table,
                    'Key' => [
                        'uuid' => ['S' => 'table#' . $uuid],
                        'data_key' => ['S' => 'v0#entity#definition#default'],
                    ],
                    'UpdateExpression' => <<<TXT
SET
    #created_at = if_not_exists(created_at, :val__updated_at),
    #updated_at = :val__updated_at,
    entity_definitions = :val__entity_definitions,
    flow_cards = :val__flow_cards,
    flow_view = :val__flow_view,
    flow_connections = :val__flow_connections,
    flow_layers = :val__flow_layers
TXT
                    ,
                    'ExpressionAttributeNames' => [
                        '#created_at' => 'created_at',
                        '#updated_at' => 'updated_at',
                    ],
                    'ExpressionAttributeValues' => [
                        ':val__updated_at' => ['S' => $ts],
                        ':val__entity_definitions' => $this->dynamo->parseArrayElement($data['entities']->entity_definitions),
                        ':val__flow_cards' => $this->dynamo->parseArrayElement($data['entities']->flow_cards),
                        ':val__flow_view' => $this->dynamo->parseArrayElement($data['entities']->flow_view),
                        ':val__flow_connections' =>
                            ($data['entities']->flow_connections ?? null) ? $this->dynamo->parseArrayElement($data['entities']->flow_connections) : ['L' => []],
                        ':val__flow_layers' =>
                            ($data['entities']->flow_layers ?? null) ? $this->dynamo->parseArrayElement($data['entities']->flow_layers) : ['M' => []],
                    ],
                    // UPDATED_NEW
                    'ReturnValues' => 'ALL_NEW',
                ];
            }

            if(isset($data['name'])) {
                $ops[] = [
                    'TableName' => $this->table,
                    'Key' => [
                        'uuid' => ['S' => 'table#' . $uuid],
                        'data_key' => ['S' => 'v0#meta'],
                    ],
                    'UpdateExpression' => <<<TXT
SET
    #created_at = if_not_exists(created_at, :val__updated_at),
    #updated_at = :val__updated_at,
    name = :val__name
TXT
                    ,
                    'ExpressionAttributeNames' => [
                        '#created_at' => 'created_at',
                        '#updated_at' => 'updated_at',
                    ],
                    'ExpressionAttributeValues' => [
                        ':val__updated_at' => ['S' => $ts],
                        ':val__name' => ['S' => json_encode($data['name'], JSON_THROW_ON_ERROR)],
                    ],
                    // UPDATED_NEW
                    'ReturnValues' => 'ALL_NEW',
                ];
            }

            $results = [];
            foreach($ops as $op) {
                $res = $this->dynamo->client()->updateItem($op)->toArray();
                if(!isset($res['Attributes'])) {
                    continue;
                }
                $updated = $this->dynamo->itemToArray($res['Attributes']);
                if(isset($updated['schema'])) {
                    $updated['schema'] = json_decode($updated['schema'], false, 512, JSON_THROW_ON_ERROR);
                }
                if(isset($updated['example_items'])) {
                    $updated['example_items'] = json_decode($updated['example_items'], false, 512, JSON_THROW_ON_ERROR);
                }

                if(strpos($updated['data_key'], 'v0#entity#') === 0) {
                    // todo: support many entities views in the future
                    $results['entities'] = $updated;
                } else {
                    $results[substr($updated['data_key'], 3)] = $updated;
                }
            }
            return $results;
        } catch(DynamoDbException $e) {
            throw $e;
        }
    }

    public function saveTablePreset(string $table_uuid, string $preset_name, array $data) {
        try {
            $ts = $this->timestamp();
            $op = [
                'TableName' => $this->table,
                'Key' => [
                    'uuid' => ['S' => 'table#' . $table_uuid],
                    'data_key' => ['S' => 'v0#preset#' . rawurlencode($preset_name)],
                ],
                'UpdateExpression' => <<<TXT
SET
    #created_at = if_not_exists(created_at, :val__updated_at),
    #updated_at = :val__updated_at,
    preset_name = :val__preset_name,
    display_keys = :val__display_keys
TXT
                ,
                'ExpressionAttributeNames' => [
                    '#created_at' => 'created_at',
                    '#updated_at' => 'updated_at',
                ],
                'ExpressionAttributeValues' => [
                    ':val__updated_at' => ['S' => $ts],
                    ':val__display_keys' => ['S' => json_encode($data['display_keys'] ?? [], JSON_THROW_ON_ERROR)],
                    ':val__preset_name' => ['S' => $preset_name],
                ],
                // UPDATED_NEW
                'ReturnValues' => 'ALL_NEW',
            ];

            $res = $this->dynamo->client()->updateItem($op)->toArray();
            $updated = $this->dynamo->itemToArray($res['Attributes']);
            if(isset($updated['display_keys'])) {
                $updated['display_keys'] = json_decode($updated['display_keys'], false, 512, JSON_THROW_ON_ERROR);
            }
            $updated['uuid'] = substr($updated['uuid'], strlen('table#'));
            $results = [
                'presets' => [
                    $updated,
                ]
            ];
            return $results;
        } catch(DynamoDbException $e) {
            throw $e;
        }
    }

    public function saveTableColor(string $table_uuid, string $color_name, array $data) {
        try {
            $ts = $this->timestamp();
            $op = [
                'TableName' => $this->table,
                'Key' => [
                    'uuid' => ['S' => 'table#' . $table_uuid],
                    'data_key' => ['S' => 'v0#color#' . rawurlencode($color_name)],
                ],
                'UpdateExpression' => <<<TXT
SET
    #created_at = if_not_exists(created_at, :val__updated_at),
    #updated_at = :val__updated_at,
    color_pk = :val__color_pk,
    color_sk = :val__color_sk,
    color_name = :val__color_name
TXT
                ,
                'ExpressionAttributeNames' => [
                    '#created_at' => 'created_at',
                    '#updated_at' => 'updated_at',
                ],
                'ExpressionAttributeValues' => [
                    ':val__updated_at' => ['S' => $ts],
                    ':val__color_pk' => ['S' => json_encode($data['color_pk'] ?? [], JSON_THROW_ON_ERROR)],
                    ':val__color_sk' => ['S' => json_encode($data['color_sk'] ?? [], JSON_THROW_ON_ERROR)],
                    ':val__color_name' => ['S' => $color_name],
                ],
                // UPDATED_NEW
                'ReturnValues' => 'ALL_NEW',
            ];

            $res = $this->dynamo->client()->updateItem($op)->toArray();
            $updated = $this->dynamo->itemToArray($res['Attributes']);
            if(isset($updated['color_pk'])) {
                $updated['color_pk'] = json_decode($updated['color_pk'], false, 512, JSON_THROW_ON_ERROR);
            }
            if(isset($updated['color_sk'])) {
                $updated['color_sk'] = json_decode($updated['color_sk'], false, 512, JSON_THROW_ON_ERROR);
            }
            $updated['uuid'] = substr($updated['uuid'], strlen('table#'));
            $results = [
                'colors' => [
                    $updated,
                ]
            ];
            return $results;
        } catch(DynamoDbException $e) {
            throw $e;
        }
    }

    public function delete(string $uuid) {
        throw new \RuntimeException('not implemented');
    }
}
