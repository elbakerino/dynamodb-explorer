<?php declare(strict_types=1);

namespace App\Services;

use Bemit\DynamoDB\DynamoService;
use ReallySimpleJWT\Decode;
use ReallySimpleJWT\Jwt;
use ReallySimpleJWT\Parse;
use ReallySimpleJWT\Token;
use ReallySimpleJWT\Encoders\EncodeHS256;
use ReallySimpleJWT\Helper\Validator;
use ReallySimpleJWT\Validate;

class AuthService {
    public static $ACCESS_LEVELS = [
        'public' => 0,
        'read' => 1,
        'create' => 2,
        'update' => 3,
        'delete' => 4,
        'manage' => 5,
        'owner' => 15,
    ];
    protected string $secret;
    protected string $issuer;
    protected int $expire;
    protected DynamoService $dynamo;
    protected string $table;

    public function __construct(string $secret, string $issuer, int $expire, string $table, DynamoService $dynamo) {
        $this->secret = $secret;
        $this->issuer = $issuer;
        $this->expire = $expire;
        $this->dynamo = $dynamo;
        $this->table = $table;
    }

    public function createToken($user_id): string {
        $payload = [
            'iat' => time(),
            'uid' => $user_id,
            'exp' => time() + $this->expire,
            'iss' => $this->issuer,
        ];

        return Token::customPayload($payload, $this->secret);
    }

    /**
     * @throws \ReallySimpleJWT\Exception\ValidateException
     */
    public function parse($token): \ReallySimpleJWT\Parsed {
        $jwt = new Jwt($token, $this->secret);

        $parse = new Parse($jwt, new Decode());

        $parsed = $parse->parse();
        $validate = new Validate(
            $parse,
            new EncodeHS256(),
            new Validator()
        );

        $validate->structure()
            ->signature()
            ->expiration()
            ->algorithmNotNone();

        return $parsed;
    }

    public function getAccessLevel(string $user, string $object_id) {
        $item_res = $this->dynamo->client()->getItem([
            'TableName' => $this->table,
            'Key' => [
                'uuid' => ['S' => $object_id],
                'data_key' => ['S' => 'shared#' . $user],
            ],
        ])->offsetGet('Item');
        if(!$item_res) {
            throw new \RuntimeException('AuthService requested share not found');
        }
        return $this->dynamo->fromItem($item_res)['shared_level'];
    }

    public function requireAccessLevel(string $user, string $object_id, string $access_level_required) {
        if(!isset(self::$ACCESS_LEVELS[$access_level_required])) {
            throw new \RuntimeException('Access level required not defined: ' . $access_level_required);
        }

        $access_level = $this->getAccessLevel($user, $object_id);

        $this->validateAccessLevel($access_level_required, $access_level, true);
    }

    public function validateAccessLevel($access_level_required, $access_level, bool $throw = false): bool {
        if(!isset(self::$ACCESS_LEVELS[$access_level])) {
            throw new \RuntimeException('Access level not defined: ' . $access_level);
        }

        $access_level_n = self::$ACCESS_LEVELS[$access_level];
        $access_level_required_n = self::$ACCESS_LEVELS[$access_level_required];

        $valid = $access_level_n >= $access_level_required_n;
        if($throw && !$valid) {
            throw new \RuntimeException('Access level `' . $access_level . '` (' . $access_level_n . ') not reaching required level `' . $access_level_required . '` (' . $access_level_required_n . ')');
        }
        return $valid;
    }
}
