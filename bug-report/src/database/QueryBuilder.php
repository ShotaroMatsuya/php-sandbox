<?php 

namespace App\Database;

use App\Contracts\DatabaseConnectionInterface;
use App\Contracts\DMLDefinitionInterface;
use App\Exception\InvalidArgumentException;

abstract class QueryBuilder implements DMLDefinitionInterface
{
    use Query;
    protected $connection; // PDO or mysqli
    protected $table;
    protected $statement; // PDOStatement or mysqli_stmt
    protected $fields;
    protected $placeholders;
    protected $bindings; // name = ? ['terry']
    protected $operation = self::DML_TYPE_SELECT; //dml SELECT, UPDATE, INSERT, DELETE

    const OPERATORS = ['=', '>=', '>', '<=', '<', '<>'];
    const PLACEHOLDER = '?';
    const COLUMNS = '*';
    
    public function __construct(DatabaseConnectionInterface $databaseConnection)
    {
        $this->connection = $databaseConnection->getConnection();
    }

    public function table($table)
    {
        $this->table = $table;
        return $this; // method chaining!
    }

    public function where($column, $operator = self::OPERATORS[0], $value = null)
    {
        if(!in_array($operator, self::OPERATORS)){
            if($value === null){
                $value = $operator;
                $operator = self::OPERATORS[0];
            }else{
                throw new InvalidArgumentException('Operator is not valid', ['operator' => $operator]);
            }
        }
        $this->parseWhere([$column => $value], $operator);

        return $this;
    }
    private function parseWhere(array $conditions, string $operator)
    {
        foreach($conditions as $column => $value){
            $this->placeholders[] = sprintf('%s %s %s', $column, $operator, self::PLACEHOLDER);
            $this->bindings[] = $value;
        }
        return $this;
    }
    public function select(string $fields = self::COLUMNS)
    {
        $this->operation = self::DML_TYPE_SELECT;
        $this->fields = $fields;
        return $this;
    }

    public function create(array $data): int
    {
        $this->fields = '`' . implode('`, `', array_keys($data)) . '`';
        foreach ($data as $value){
            $this->placeholders[] = self::PLACEHOLDER;
            $this->bindings[] = $value;
        }
        $query = $this->prepare($this->getQuery(self::DML_TYPE_INSERT));
        $this->statement = $this->execute($query);

        return (int)$this->lastInsertedId();
    }

    public function update(array $data)
    {
        $this->fields = [];
        $this->operation = self::DML_TYPE_UPDATE;
        foreach ($data as $column => $value){
            $this->fields[] = sprintf('%s%s%s', $column, self::OPERATORS[0], "'$value'");
        }
        return $this;
    }

    public function delete()
    {
        $this->operation = self::DML_TYPE_DELETE;
        return $this;
    }

    // rawクエリを処理
    public function raw(string $query)
    {
        $query = $this->prepare($query);
        $this->statement = $this->execute($query);
        return $this;
    }

    public function find($id)
    {
        return $this->where('id', $id)->runQuery()->first();
    }

    public function findOneBy(string $field, $value)
    {
        return $this->where($field, $value)->runQuery()->first();
    }

    public function first()
    {
        return $this->count() ? $this->get()[0] : null;
    }

    public function rollback(): void
    {
        $this->connection->rollback();
    }

    public function runQuery()
    {
        $query = $this->prepare($this->getQuery($this->operation));
        $this->statement = $this->execute($query);
        return $this;
    }

    abstract public function get();
    abstract public function count();
    abstract public function lastInsertedId();
    abstract public function prepare(string $query);
    abstract public function execute($statement);
    abstract public function fetchInto($className);
    abstract public function beginTransaction();
    abstract public function affected();
}

?>