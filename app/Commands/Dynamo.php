<?php declare(strict_types=1);

namespace App\Commands;

use App\Services\DynamoService;
use Aws\DynamoDb\Exception\DynamoDbException;
use DI\Annotation\Inject;
use Psr\Log\LoggerInterface;
use Satellite\KernelConsole\Annotations\Command;
use Satellite\KernelConsole\Annotations\CommandOperand;

class Dynamo {
    /**
     * @Inject
     */
    protected DynamoService $dynamo;
    /**
     * @Inject
     */
    protected LoggerInterface $logger;
    protected string $table_dir;

    public function __construct(string $table_dir) {
        $this->table_dir = $table_dir;
    }

    /**
     * @Command(
     *     name="dynamo:create",
     *     operands={
     *          @CommandOperand("table", mode=\GetOpt\Operand::REQUIRED, description="name of table schema to create"),
     *          @CommandOperand("table_name", mode=\GetOpt\Operand::OPTIONAL, description="name of table to create")
     *     }
     * )
     */
    public function handleTableCreate(\GetOpt\Command $command) {
        $table_id = $command->getOperand('table')->getValue();
        $table_name = $command->getOperand('table_name') ? ($command->getOperand('table_name')->getValue() ?? $table_id) : $table_id;
        /**
         * @var callable $table_factory
         */
        $table_factory = require($this->table_dir . '/' . $table_id . '.php');
        try {
            $table = $this->dynamo->client()->createTable($table_factory($table_name));
        } catch(DynamoDbException $e) {
            $this->logger->error($e->getMessage(), $e->toArray());
            return;
        }
        if($table->offsetExists('TableDescription')) {
            $table_desc = $table->offsetGet('TableDescription');
            $this->logger->info('created table `' . $table_id . '`, status `' . $table_desc['TableStatus'] . '`');
        } else {
            $this->logger->info('created table `' . $table_id . '`, but no description');
        }
    }

    /**
     * @Command(
     *     name="dynamo:delete",
     *     operands={
     *          @CommandOperand("table", mode=\GetOpt\Operand::REQUIRED, description="name of table to delete")
     *     }
     * )
     */
    public function handleTableDelete(\GetOpt\Command $command) {

        $table_id = $command->getOperand('table')->getValue();

        try {
            $table = $this->dynamo->client()->deleteTable([
                'TableName' => $table_id,
            ]);
        } catch(DynamoDbException $e) {
            $this->logger->error($e->getMessage(), $e->toArray());
            return;
        }
        if($table->offsetExists('TableDescription')) {
            $table_desc = $table->offsetGet('TableDescription');
            $this->logger->info('deleted table `' . $table_id . '`, status `' . $table_desc['TableStatus'] . '`');
        } else {
            $this->logger->info('deleted table `' . $table_id . '`, but no description', $table->toArray());
        }
    }

    /**
     * @Command(
     *     name="dynamo:inspect",
     *     operands={
     *          @CommandOperand("table", mode=\GetOpt\Operand::REQUIRED, description="name of table to delete")
     *     }
     * )
     */
    public function handleInspectSchema(\GetOpt\Command $command) {
        $table_id = $command->getOperand('table')->getValue();
        $this->inspectTable($table_id);
    }

    /**
     * @Command(
     *     name="dynamo:dump",
     *     operands={
     *          @CommandOperand("table", mode=\GetOpt\Operand::REQUIRED, description="name of table to delete")
     *     }
     * )
     */
    public function handleDumpTable(\GetOpt\Command $command) {
        $table_id = $command->getOperand('table')->getValue();
        $this->dumpTable($table_id);
    }

    /**
     * @Command(
     *     name="dynamo:inspect-dump",
     *     operands={
     *          @CommandOperand("table", mode=\GetOpt\Operand::REQUIRED, description="name of table to delete")
     *     }
     * )
     */
    public function handleDumpAndInspect(\GetOpt\Command $command) {
        $table_id = $command->getOperand('table')->getValue();
        $this->inspectTable($table_id);
        $this->dumpTable($table_id);
    }

    protected function inspectTable(string $table_id) {
        try {
            $table = $this->dynamo->client()->describeTable([
                'TableName' => $table_id,
            ]);
        } catch(DynamoDbException $e) {
            $this->logger->error($e->getMessage(), $e->toArray());
            return;
        }
        $json = json_encode($table->toArray(), JSON_THROW_ON_ERROR);
        file_put_contents(__DIR__ . '/../../dynamo-dump--' . $table_id . '--schema.json', $json);
        $this->logger->info('saved inspect result `dynamo-dump--' . $table_id . '--schema.json`');
    }

    protected function dumpTable(string $table_id) {
        try {
            $result = $this->dynamo->client()->scan([
                'TableName' => $table_id,
            ]);
        } catch(DynamoDbException $e) {
            $this->logger->error($e->getMessage(), $e->toArray());
            return;
        }
        $json = json_encode($result->toArray(), JSON_THROW_ON_ERROR);
        file_put_contents(__DIR__ . '/../../dynamo-dump--' . $table_id . '--data.json', $json);
        $this->logger->info('saved scan result `dynamo-dump--' . $table_id . '.json`, got total: ' . count($result->offsetGet('Items')));
    }

    /**
     * @Command(
     *     name="dynamo:seed",
     *     operands={
     *          @CommandOperand("table", mode=\GetOpt\Operand::REQUIRED, description="name of table to delete")
     *     }
     * )
     */
    public function handleSeedTable(\GetOpt\Command $command) {
        $table_id = $command->getOperand('table')->getValue();
        /**
         * @var callable $seed_factory
         */
        $seed_factory = require($this->table_dir . '/' . $table_id . '__seed.php');
        $rows_raw = $seed_factory();
        $rows_dynamo = array_map(function($row_raw) use ($table_id) {
            return [
                'PutRequest' => [
                    'TableName' => $table_id,
                    'Item' => $this->dynamo->arrayToItem($row_raw),
                ],
            ];
        }, $rows_raw['items']);
        try {
            $this->dynamo->client()->batchWriteItem([
                'RequestItems' => [
                    $rows_raw['table'] => $rows_dynamo,
                ],
            ]);
        } catch(DynamoDbException $e) {
            $this->logger->error($e->getMessage(), $e->toArray());
            return;
        }
        $this->logger->info('seeded table with `' . count($rows_dynamo) . '` items');
    }
}
