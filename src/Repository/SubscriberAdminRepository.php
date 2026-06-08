<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\AsciiStringType;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\DateImmutableType;
use Doctrine\DBAL\Types\DateIntervalType;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use Doctrine\DBAL\Types\DateTimeTzType;
use Doctrine\DBAL\Types\DateType;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\GuidType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\SimpleArrayType;
use Doctrine\DBAL\Types\SmallIntType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\TimeImmutableType;
use Doctrine\DBAL\Types\TimeType;

final class SubscriberAdminRepository
{
    public function __construct(private readonly Connection $auditConnection)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSubscribers(int $limit = 200): array
    {
        $metadata = $this->getTableMetadata();
        $pk = $metadata['primary_key'];
        $orderBy = $pk !== null ? $this->auditConnection->quoteIdentifier($pk) . ' DESC' : '1';

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->auditConnection->fetchAllAssociative(
            sprintf('SELECT * FROM public.t00002 ORDER BY %s LIMIT %d', $orderBy, max(1, $limit))
        );

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSubscriber(string $primaryKeyValue): ?array
    {
        $metadata = $this->getTableMetadata();
        $pk = $metadata['primary_key'];
        if ($pk === null) {
            return null;
        }

        $row = $this->auditConnection->fetchAssociative(
            sprintf('SELECT * FROM public.t00002 WHERE %s = :id LIMIT 1', $this->auditConnection->quoteIdentifier($pk)),
            ['id' => $primaryKeyValue]
        );

        return $row === false ? null : $row;
    }

    /**
     * @return array{primary_key:?string, columns:list<array{name:string,type:string,required:bool,editable:bool,max_length:?int,default:mixed}>}
     */
    public function getTableMetadata(): array
    {
        $schemaManager = $this->auditConnection->createSchemaManager();
        $columns = $schemaManager->listTableColumns('t00002');
        $primaryKey = $this->findPrimaryKey();
        $result = [];

        foreach ($columns as $column) {
            $result[] = $this->normalizeColumn($column, $column->getName() !== $primaryKey);
        }

        usort(
            $result,
            static fn (array $left, array $right): int => strcmp($left['name'], $right['name'])
        );

        return [
            'primary_key' => $primaryKey,
            'columns' => $result,
        ];
    }

    /**
     * @param array<string, string> $payload
     */
    public function save(array $payload, ?string $primaryKeyValue = null): void
    {
        $metadata = $this->getTableMetadata();
        $primaryKey = $metadata['primary_key'];
        $columnsByName = [];
        foreach ($metadata['columns'] as $column) {
            $columnsByName[$column['name']] = $column;
        }

        $data = [];
        foreach ($payload as $name => $value) {
            if (!isset($columnsByName[$name])) {
                continue;
            }

            if ($columnsByName[$name]['editable'] !== true) {
                continue;
            }

            $data[$name] = $this->normalizeValue($value, $columnsByName[$name]['type'], $name);
        }

        if ($primaryKeyValue === null || $primaryKey === null) {
            $this->auditConnection->insert('t00002', $data);

            return;
        }

        $this->auditConnection->update('t00002', $data, [$primaryKey => $primaryKeyValue]);
    }

    public function toggleActive(string $primaryKeyValue, bool $active): void
    {
        if (!$this->hasColumn('log_ativo')) {
            return;
        }

        $primaryKey = $this->findPrimaryKey();
        if ($primaryKey === null) {
            return;
        }

        $this->auditConnection->update('t00002', ['log_ativo' => $active ? '1' : '0'], [$primaryKey => $primaryKeyValue]);
    }

    public function generateToken(): string
    {
        return 'tok_' . bin2hex(random_bytes(24));
    }

    public function hasColumn(string $name): bool
    {
        foreach ($this->getTableMetadata()['columns'] as $column) {
            if ($column['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    private function findPrimaryKey(): ?string
    {
        $pk = $this->auditConnection->fetchOne(
            <<<'SQL'
            SELECT kcu.column_name
            FROM information_schema.table_constraints tc
            INNER JOIN information_schema.key_column_usage kcu
                ON kcu.constraint_name = tc.constraint_name
               AND kcu.table_schema = tc.table_schema
            WHERE tc.table_schema = 'public'
              AND tc.table_name = 't00002'
              AND tc.constraint_type = 'PRIMARY KEY'
            ORDER BY kcu.ordinal_position
            LIMIT 1
            SQL
        );

        return is_string($pk) && $pk !== '' ? $pk : null;
    }

    /**
     * @return array{name:string,type:string,required:bool,editable:bool,max_length:?int,default:mixed}
     */
    private function normalizeColumn(Column $column, bool $editable): array
    {
        return [
            'name' => $column->getName(),
            'type' => $this->resolveTypeName($column),
            'required' => !$column->getNotnull() ? false : $column->getDefault() === null,
            'editable' => $editable && !$column->getAutoincrement(),
            'max_length' => $column->getLength(),
            'default' => $column->getDefault(),
        ];
    }

    private function resolveTypeName(Column $column): string
    {
        $type = $column->getType();

        return match (true) {
            $type instanceof BooleanType => 'boolean',
            $type instanceof SmallIntType => 'smallint',
            $type instanceof IntegerType => 'integer',
            $type instanceof BigIntType => 'bigint',
            $type instanceof FloatType => 'float',
            $type instanceof DecimalType => 'decimal',
            $type instanceof TextType => 'text',
            $type instanceof BlobType => 'blob',
            $type instanceof BinaryType => 'binary',
            $type instanceof JsonType => 'json',
            $type instanceof SimpleArrayType => 'simple_array',
            $type instanceof GuidType => 'guid',
            $type instanceof DateTimeImmutableType => 'datetime_immutable',
            $type instanceof DateTimeTzImmutableType => 'datetimetz_immutable',
            $type instanceof DateTimeTzType => 'datetimetz',
            $type instanceof DateTimeType => 'datetime',
            $type instanceof DateImmutableType => 'date_immutable',
            $type instanceof DateType => 'date',
            $type instanceof TimeImmutableType => 'time_immutable',
            $type instanceof TimeType => 'time',
            $type instanceof DateIntervalType => 'dateinterval',
            $type instanceof AsciiStringType, $type instanceof StringType => 'string',
            default => strtolower(preg_replace('/Type$/', '', (new \ReflectionClass($type))->getShortName()) ?? 'string'),
        };
    }

    private function normalizeValue(string $value, string $type, string $name): mixed
    {
        $trimmed = trim($value);

        if ($type === 'boolean' || str_starts_with($name, 'log_')) {
            return in_array(strtolower($trimmed), ['1', 'true', 't', 'on', 'sim', 'yes'], true) ? '1' : '0';
        }

        if ($trimmed === '') {
            return null;
        }

        return match ($type) {
            'smallint', 'integer', 'bigint' => (int) $trimmed,
            'float', 'decimal' => (float) str_replace(',', '.', $trimmed),
            default => $trimmed,
        };
    }
}
