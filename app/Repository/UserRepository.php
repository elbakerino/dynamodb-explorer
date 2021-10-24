<?php declare(strict_types=1);

namespace App\Repository;

use App\Services\DynamoService;
use Aws\DynamoDb\Exception\DynamoDbException;

class UserRepository {
    protected DynamoService $dynamo;
    protected string $table;
    protected string $app_salt;

    public function __construct(DynamoService $dynamo, string $table, string $app_salt) {
        $this->dynamo = $dynamo;
        $this->table = $table;
        $this->app_salt = $app_salt;
    }

    protected function timestamp(): string {
        return (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }

    protected function makeHash(string $password): string {
        if(strlen($password) < 8) {
            throw new \RuntimeException('Password min. length: 8');
        }
        return password_hash($password . '#' . $this->app_salt, PASSWORD_BCRYPT);
    }

    protected function verifyHash(string $password, string $hash): bool {
        return password_verify($password . '#' . $this->app_salt, $hash);
    }

    protected function makeUser(string $email, string $password): array {
        $ts = $this->timestamp();
        return [
            'uuid' => 'user#' . $email,
            'data_key' => 'v0#meta',
            'email' => $email,
            'passcheck' => $this->makeHash($password),
            'created_at' => $ts,
            'updated_at' => $ts,
        ];
    }

    /**
     * @throws \JsonException
     */
    protected function hydrate($item): array {
        $assoc_item = $this->dynamo->itemToArray($item);
        return $assoc_item;
    }

    /**
     * @throws \JsonException
     */
    public function create(string $email, string $password): array {
        $user_raw = $this->makeUser($email, $password);
        $item = $this->dynamo->arrayToItem($user_raw);
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
                throw new \RuntimeException('User already exists');
            }
            throw $e;
        }
        if($response->offsetGet('@metadata')['statusCode'] === 200) {
            return $user_raw;
        }

        throw new \RuntimeException('User create error, DB status: ' . $response->offsetGet('@metadata')['statusCode']);
    }

    /**
     * @throws \JsonException
     */
    protected function getDetailsUnsafe(string $email): array {
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

    public function login(string $email, string $password): array {
        $user = $this->getDetailsUnsafe($email);
        if(isset($user['passcheck'])) {
            $hash = $this->verifyHash($password, $user['passcheck']);
            if($hash) {
                unset($user['passcheck']);
                return $user;
            }
            throw new \RuntimeException('Password is incorrect.');
        }
        throw new \RuntimeException('Passcheck missing for user.');
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
                ':uuid__val' => ['S' => 'user#' . $uuid],
            ],
        ];
        $res = $this->dynamo->client()->query($query);
        $items = $res->offsetGet('Items');
        if(empty($items)) {
            throw new \RuntimeException('db entry not found');
        }

        // todo: hydrate `products` with the ProductRepository
        $items = array_map(fn($item) => $this->hydrate($item), $items);
        $user = null;
        foreach($items as $data) {
            if(!isset($data['uuid'])) {
                continue;
            }
            if(strpos($data['uuid'], 'user#') === 0) {
                $data['uuid'] = substr($data['uuid'], strlen('user#'));
                unset($data['passcheck']);
                $user['meta'] = $data;
            }
        }
        return [
            'size' => (int)$res->offsetGet('@metadata')['headers']['content-length'],
            'user' => $user,
        ];
    }

    public function update(string $email, array $data) {
        try {
            $ts = $this->timestamp();
            $ops = [];
            if(isset($data['password'])) {
                $ops[] = [
                    'TableName' => $this->table,
                    'Key' => [
                        'uuid' => ['S' => 'user#' . $email],
                        'data_key' => ['S' => 'v0#meta'],
                    ],
                    'UpdateExpression' => <<<TXT
SET
    #updated_at = :val__updated_at,
    passcheck = :val__passcheck
TXT
                    ,
                    'ExpressionAttributeNames' => [
                        '#updated_at' => 'updated_at',
                    ],
                    'ExpressionAttributeValues' => [
                        ':val__updated_at' => ['S' => $ts],
                        ':val__passcheck' => ['S' => $this->makeHash($data['password'])],
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
                if(isset($updated['passcheck'])) {
                    unset($updated['passcheck']);
                }
                $results[substr($updated['data_key'], 3)] = $updated;
            }
            return $results;
        } catch(DynamoDbException $e) {
            throw $e;
        }
    }

    public function delete(string $uuid) {
        throw new \RuntimeException('not implemented');
    }
}
