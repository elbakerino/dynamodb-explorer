<?php declare(strict_types=1);

return static function(string $table) {
    return [
        'TableName' => $table,
        'BillingMode' => 'PAY_PER_REQUEST',
        //'BillingMode' => 'PROVISIONED',
        'KeySchema' => [
            ['AttributeName' => 'uuid', 'KeyType' => 'HASH',],
            ['AttributeName' => 'data_key', 'KeyType' => 'RANGE',],
        ],
        'AttributeDefinitions' => [
            ['AttributeName' => 'uuid', 'AttributeType' => 'S',],
            ['AttributeName' => 'data_key', 'AttributeType' => 'S',],
            ['AttributeName' => 'shared_with', 'AttributeType' => 'S',],
        ],
        /*'ProvisionedThroughput' => [
            'ReadCapacityUnits' => 2,
            'WriteCapacityUnits' => 2,
        ],*/
        'GlobalSecondaryIndexes' => [
            [
                'IndexName' => 'table_shares',
                'KeySchema' => [
                    ['AttributeName' => 'shared_with', 'KeyType' => 'HASH',],
                    ['AttributeName' => 'uuid', 'KeyType' => 'RANGE',],
                ],
                'Projection' => [
                    'ProjectionType' => 'ALL',
                ],
            ],
        ],
    ];
};
