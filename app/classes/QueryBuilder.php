<?php


namespace App\classes;
use Aura\SqlQuery\QueryFactory;
use PDO;

class QueryBuilder
{
    private $pdo, $queryFactory;

    public function __construct(PDO $pdo, QueryFactory $queryFactory) {
        $this->pdo = $pdo;
        $this->queryFactory = $queryFactory;
    }

    public function getAll($table) {


        $select = $this->queryFactory->newSelect();

        $select->cols(['*'])
            ->from($table);

        $sth = $this->pdo->prepare($select->getStatement());


        $sth->execute($select->getBindValues());


        $result = $sth->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function select($what, $table, $where = null) {


        $select = $this->queryFactory->newSelect();

        $select->cols([$what])
            ->from($table);
        if(!empty($where)) {
            $select->where($where);
        }


        $sth = $this->pdo->prepare($select->getStatement());



        $sth->execute($select->getBindValues());


        $result = $sth->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function insert($table, $data = []) {

        $insert = $this->queryFactory->newInsert();

        $insert->into($table)
        ->cols($data);

        $sth = $this->pdo->prepare($insert->getStatement());


        $sth->execute($insert->getBindValues());


        $name = $insert->getLastInsertIdName('id');
        $id = $this->pdo->lastInsertId($name);
        return $id;

    }

    public function update($table, $where = null, $what = []) {


        $update = $this->queryFactory->newUpdate();

        $update->table($table)
            ->cols($what);
        if(!empty($where)) {
            $update->where($where);
        }


        $sth = $this->pdo->prepare($update->getStatement());

        $sth->execute($update->getBindValues());


    }

    public function delete($table, $where = null) {


        $delete = $this->queryFactory->newDelete();

        $delete->from($table)
            ->where($where);

        $sth = $this->pdo->prepare($delete->getStatement());


        $sth->execute($delete->getBindValues());


    }
}