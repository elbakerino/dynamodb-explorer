<?php

namespace App\Services;

use Aws\Credentials\Credentials;
use Aws\DynamoDb\DynamoDbClient;

class DynamoService {
    protected $dynamo;
    protected static $instance;

    public function __construct(string $region, string $dynamo_key, string $dynamo_secret, ?string $endpoint = null) {
        $credentials = new Credentials($dynamo_key, $dynamo_secret);

        $params = [
            'region' => $region,
            'credentials' => $credentials,
            //'debug' => true,
            'version' => 'latest',
            // 'endpoint' => $endpoint,
            //'endpoint' => 'http://host.docker.internal:4226'
        ];
        if($endpoint) {
            $params['endpoint'] = $endpoint;
        }

        $this->dynamo = new DynamoDbClient($params);
    }

    public function client(): DynamoDbClient {
        return $this->dynamo;
    }

    public function itemToArray(array $item): array {
        $data = [];
        foreach($item as $key => $value_def) {
            if(isset($value_def['S'])) {
                $data[$key] = $value_def['S'];
            } else if(isset($value_def['N'])) {
                // todo: support float, int and big
                $data[$key] = (int)$value_def['N'];
            } else if(isset($value_def['M'])) {
                $data[$key] = $this->itemToArray($value_def['M']);
            } else if(isset($value_def['L'])) {
                throw new \RuntimeException('dynamo itemToArray does not implement L (list) mode');
            } else if(isset($value_def['B'])) {
                throw new \RuntimeException('dynamo itemToArray does not implement B (binary) mode');
            } else {
                throw new \RuntimeException('dynamo itemToArray no known mode found: ' . json_encode(array_keys($value_def), JSON_THROW_ON_ERROR));
            }
        }
        return $data;
    }

    public function arrayToItem(array $item): array {
        $data = [];
        foreach($item as $key => $value) {
            if(is_null($value)) {
                continue;
            }
            if(is_string($value)) {
                $data[$key]['S'] = $value;
            } else if(is_numeric($value)) {
                $data[$key]['N'] = (string)$value;
            } else if(is_array($value)) {
                $data[$key]['M'] = $this->arrayToItem($value);
            } else {
                throw new \RuntimeException('dynamo arrayToItem no known mode found, key: ' . $key . ', value: ' . json_encode($value, JSON_THROW_ON_ERROR));
            }
        }
        return $data;
    }
}
