<?php declare(strict_types=1);

namespace App\Services;

use ReallySimpleJWT\Decode;
use ReallySimpleJWT\Jwt;
use ReallySimpleJWT\Parse;
use ReallySimpleJWT\Token;
use ReallySimpleJWT\Encoders\EncodeHS256;
use ReallySimpleJWT\Helper\Validator;
use ReallySimpleJWT\Validate;

class AuthService {
    protected string $secret;
    protected string $issuer;

    public function __construct(string $secret, string $issuer) {
        $this->secret = $secret;
        $this->issuer = $issuer;
    }

    public function createToken($user_id): string {
        $payload = [
            'iat' => time(),
            'uid' => $user_id,
            'exp' => time() + 3600,
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
}
