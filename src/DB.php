<?php

namespace Makan\QueryBuilder;

use PDO;
use PDOException;

class DB {

	/**
	 * @var PDO $connection
	 */
	private static $connection;

	// Подключение к БД
	public static function connect($host, $name, $user, $pass, $profiling = false){

		try {
			$options = array(
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
				//\PDO::ATTR_PERSISTENT => true
			);

			self::$connection = new PDO('mysql:host=' . $host . ';dbname=' . $name, $user, $pass, $options);
			self::$connection->query('SET NAMES utf8');

			if($profiling)
				self::$connection->query("SET profiling = 1");

		} catch (PDOException $e) {
			exit('Ошибка при подключении к базе данных: <br>' . $e->getMessage());
		}
	}

	// Метод для передачи существующего подключения
	public static function connection($connection = null){
		if ($connection){
			self::$connection = $connection;
		}

		return self::$connection;
	}

	public static function disconnect(){
		return self::$connection = null;
	}

    public static function table($table){
        return (new Builder(self::$connection))->table($table);
    }

    public static function query($statement){
        return (new Builder(self::$connection))->query($statement);
    }

	// Вернет массив с запросами и временем выполнения
	public static function profiling(){
		$result = self::$connection->prepare('SHOW profiles');
		$result->execute();
		return $result->fetchAll();
	}
}