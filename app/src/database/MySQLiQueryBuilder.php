<?php

declare(strict_types = 1);

namespace App\Database;


use App\Exception\InvalidArgumentException;

use mysqli_stmt;

class MySQLiQueryBuilder extends QueryBuilder
{
    private $resultSet;
    private $results; // actual result

    const PARAM_TYPE_INT = 'i';
    const PARAM_TYPE_STRING = 's';
    const PARAM_TYPE_DOUBLE = 'd';

    public function get(): array
    {
        $results = [];
        if(!$this->resultSet){
            $this->resultSet = $this->statement->get_result();
            if($this->resultSet){
                while($object = $this->resultSet->fetch_object()){
                    $results[] = $object;
                }
                $this->results = $results;
            }
        }
        return $this->results;
    }

    public function count()
    {
        if(!$this->resultSet) {
            $this->get();
        }
        return $this->resultSet ? $this->resultSet->num_rows : false;
    }

    public function lastInsertedId()
    {
        return $this->connection->insert_id;
    }

    public function prepare(string $query): mysqli_stmt | false
    {
        return $this->connection->prepare($query);
    }

    /** @param mysqli_stmt $statement */
    public function execute($statement): mysqli_stmt
    {
        if(!$statement) {
            throw new InvalidArgumentException('MySQLi statement is false');
        }

        if($this->bindings){
            $bindings = $this->parseBindings($this->bindings);
            $reflectionObj = new \ReflectionClass('mysqli_stmt');
            $method = $reflectionObj->getMethod('bind_param');
            $method->invokeArgs($statement, $bindings);
        }
        $statement->execute();
        // once execute is called , cleanup the assigned elements
        $this->bindings = [];
        $this->placeholders = [];

        return $statement;
    }

    private function parseBindings(array $params): array
    {
        $bindings = [];
        $count = count($params);
        if($count === 0){
            return $this->bindings;
        }

        $bindingTypes = $this->parseBindingTypes(); //"sids"
        $bindings[] = & $bindingTypes;
        for($index = 0; $index < $count; $index++){
            $bindings[] = & $params[$index];
        }
        return $bindings;
    }

    // 入力値の型の指定
    public function parseBindingTypes(): string 
    {
        $bindingTypes = [];
        foreach ($this->bindings as $binding){
            if(is_int($binding)){
                $bindingTypes[] = self::PARAM_TYPE_INT;
            }
            if(is_string($binding)){
                $bindingTypes[] = self::PARAM_TYPE_STRING;
            }

            if(is_float($binding)){
                $bindingTypes[] = self::PARAM_TYPE_DOUBLE;
            }
        }
        return implode('', $bindingTypes);
    }

    public function fetchInto($className)
    {
        $results = [];
        $this->resultSet = $this->statement->get_result();
        while($object = $this->resultSet->fetch_object($className)){
            $results[] = $object;
        }
        return $this->results = $results;
    }

    public function beginTransaction()
    {
        $this->connection->begin_transaction();
    }

    public function affected()
    {
        $this->statement->store_result();
        return $this->statement->affected_rows;
    }
}