<?php

namespace Framework\Core\database;
class DB {

    public static function table($table){
        return (new Database())->table($table);
    }

    public static function query($statement){
        return (new Database())->query($statement);
    }


    public static function pdo(){
        return (new Database());
    }
}