<?php

namespace Makan\QueryBuilder;

class DB {

    public static function table($table){
        return (new Builder())->table($table);
    }

    public static function query($statement){
        return (new Builder())->query($statement);
    }

    public static function pdo(){
        return (new Builder());
    }
}