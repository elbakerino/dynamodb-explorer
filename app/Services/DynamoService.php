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
            $data[$key] = $this->parseItemProp($value_def);
        }
        return $data;
    }

    protected function parseItemProp($value_def) {
        if(isset($value_def['S'])) {
            return $value_def['S'];
        }
        if(isset($value_def['N'])) {
            // todo: support float, int and big
            return (float)$value_def['N'];
        }
        if(isset($value_def['BOOL'])) {
            // todo: support float, int and big
            return (bool)$value_def['BOOL'];
        }
        if(isset($value_def['M'])) {
            return $this->itemToArray($value_def['M']);
        }
        if(isset($value_def['L'])) {
            $data = [];
            foreach($value_def['L'] as $l_item) {
                $data[] = $this->parseItemProp($l_item);
            }
            return $data;
        }
        if(isset($value_def['B'])) {
            throw new \RuntimeException('dynamo parseItemProp does not implement B (binary) mode');
        }
        throw new \RuntimeException('dynamo parseItemProp no known mode found: ' . json_encode(array_keys($value_def), JSON_THROW_ON_ERROR));
    }

    /**
     * @throws \JsonException
     */
    public function arrayToItem($item): array {
        $data = [];
        if($item instanceof \stdClass) {
            $item = get_object_vars($item);
        }

        foreach($item as $key => $value) {
            if(is_null($value)) {
                continue;
            }
            $data[$key] = $this->parseArrayElement($value);
        }
        return $data;
    }

    /**
     * @throws \JsonException
     */
    public function parseArrayElement($value): array {
        $data = [];
        if(is_string($value)) {
            $data['S'] = $value;
        } else if(is_numeric($value)) {
            $data['N'] = (string)$value;
        } else if(is_bool($value)) {
            $data['BOOL'] = (bool)$value;
        } else if($value instanceof \stdClass) {
            $data['M'] = $this->arrayToItem($value);
        } else if(is_array($value)) {
            if(empty($value) || is_string(array_keys($value)[0])) {
                $data['M'] = $this->arrayToItem($value);
            } else {
                $data['L'] = $this->arrayToItem($value);
            }
        } else {
            throw new \RuntimeException('dynamo parseArrayElement no known mode found, value: ' . json_encode($value, JSON_THROW_ON_ERROR));
        }

        return $data;
    }
}
