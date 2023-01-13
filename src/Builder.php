<?php

namespace Makan\QueryBuilder;

use PDO;
use PDOException;
use Closure;

class Builder
{
	/**
	 * @var PDO $connection
	 */
	private $connection;

    private $prefix = '';
    private $table;
    private $columns = ['*'];
    private $wheres;
    private $joins;
    private $order;
    private $limit = false;
    private int $offset = 0;
    private $orders;

    private $nested; // указывает на объединение условия WHERE
    private $nestedGroup = 0;
    private $basicGroup = 0;
    private $lastInsertIds = [];

    private $query;
    private $appQueries;
    private $sql;

    private string $asObject;

	public function __construct($connection){
		$this->connection = $connection;
	}


    public function table($table)
    {
        $this->table = htmlspecialchars($table);
        return $this;
    }


    public function columns($columns = ['*'])
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }


    public function orderBy($column = 'id', $direction = 'asc')
    {
        $direction = strtolower($direction);

        if (!in_array($direction, ['asc', 'desc'], true)) {
            exit('Order direction must be "asc" or "desc".');
        }

        $this->orders[] = [
            'column' => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * Add a descending "order by" clause to the query.
     *
     * @param string $column
     * @return $this
     */
    public function orderByDesc($column = 'id')
    {
        return $this->orderBy($column, 'desc');
    }


    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {

        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $boolean = mb_strtolower($boolean);

        if ($this->nested) $group_save = 'Nested_' . $this->nestedGroup;
        else $group_save = 'Basic_' . $this->basicGroup++;

        $type = 'Basic';

        $this->wheres[$group_save][] = compact(
            'type', 'column', 'operator', 'value', 'boolean'
        );

        return $this;
    }

    private function whereNested(Closure $callback, $boolean)
    {
        $this->nested = 1;
        $this->nestedGroup++;
        $this->wheres[] = empty($this->wheres) ? ['str' => ' ( '] : ['str' => $boolean . ' ( '];
        $callback($this);
        $this->wheres[] = ['str' => ' ) '];
        $this->nested = 0;
        return $this;
    }


    public function andWhere($column, $operator = null, $value = null)
    {

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }


        return $this->where($column, $operator, $value, 'and');
    }

    public function orWhere($column, $operator = null, $value = null)
    {

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->where($column, $operator, $value, 'or');
        return $this;
    }

    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        $type = $not ? 'NOT IN' : 'IN';

        $this->wheres[][] = compact(
            'type', 'column', 'values', 'boolean'
        );
        return $this;
    }

    public function andWhereIn($column, $values)
    {
        $this->whereIn($column, $values, 'and', false);
        return $this;
    }

    public function orWhereIn($column, $values)
    {
        $this->whereIn($column, $values, 'or', false);
        return $this;
    }

    public function whereNotIn($column, $values, $boolean = 'and')
    {

        $this->whereIn($column, $values, $boolean, true);
        return $this;
    }

    public function andWhereNotIn($column, $values)
    {

        $this->whereIn($column, $values, 'and', true);
        return $this;
    }

    public function orWhereNotIn($column, $values)
    {

        $this->whereIn($column, $values, 'or', true);
        return $this;
    }


    public function limit($limit, $offset = 0)
    {

        $this->limit = (int)$limit;
        $this->offset = (int)$offset;

        return $this;
    }


    private function compileSelect()
    {
        try {
            $query = "SELECT " . $this->compileColumns($this->columns) . " FROM " . $this->prefix . $this->table . " as " . $this->table . " " .
				$this->compileJoins($this->joins) .
				$this->compileWheres($this->wheres) .
				$this->compileOrders($this->orders) .
				$this->compileLimit($this->limit, $this->offset);


            $result = $this->connection->prepare($query);
            $values = $this->wheresBindParam();
            $this->saveQueries($query, $values);
            $result->execute($values);

            return $result;

        } catch (PDOException $e) {
			throw new PDOException('Ошибка: ' . $e->getMessage() . '; <br>Line: ' . $e->getLine() . '; <br>SQL: ' . $query . '; <br>Params: <br>' . print_r($this->wheres, true));
        }

    }

//** Выводит множество записей из таблицы */
    public function get($class = null): bool|array
    {
        if($class){
            return $this->compileSelect()->fetchAll(PDO::FETCH_CLASS, $class);
        }

        return $this->compileSelect()->fetchAll();
    }


//** Выводит одну запись из таблицы */

    public function first($class = null) : mixed
    {
        $res = $this
			->limit(1)
			->compileSelect();

		return $class ? $res->fetchObject($class) : $res->fetch();
    }

    public function insert($values, $id_name = 'id')
    {
        if (empty($values)) {
            return true;
        }

		// Если передали параметры для 1й записи.
        if (!is_array(reset($values))) {
            $values = [$values];
        }

		// Here, we will sort the insert keys for every record so that each insert is
		// in the same order for the record. We need to make sure this is the case
		// so there are not any errors or problems when inserting these records.
        else {
            foreach ($values as $key => $value) {
                ksort($value);
                $values[$key] = $value;
            }
        }


        try {

            $columns = array_keys(reset($values));
            $placeholders = rtrim(str_repeat('?,', count($columns)), ',');
            $columns = "`" . implode('`, `', $columns) . "`";

            $query = "INSERT INTO " . $this->prefix . $this->table . " (" . $columns . ") VALUES ( " . $placeholders . " )  ";
            $result = $this->connection->prepare($query);


            $this->connection->beginTransaction();
            foreach ($values as $item) {

                $item = array_values($item); // сбрасываем ключи массива в числовые
                $this->saveQueries($query, $item);
                $result->execute($item);
                $this->lastInsertIds[] = $this->connection::lastInsertId($id_name);

            }

            $this->connection->commit();

            return $this;

        } catch (PDOException $e) {

            $this->rollBack();
//Logs::Instance()->write('database', 'Ошибка: ' . $e->getMessage() . '; Line: ' . $e->getLine() . '; SQL: ' . $sql . '; Params: ' . Tools::my_print_r($params) . '');
//echo ('Ошибка: ' . $e->getMessage() . '; Line: ' . $e->getLine() . '; SQL: ' . $sql . '; Params: ');
            throw $e;
        }

    }

    public function insertGetId($values, $var = 'id')
    {
        $this->insert($values, $var);

        if (count($this->lastInsertIds) == 1)
            return $this->lastInsertIds[0];

        return $this->lastInsertIds;
    }


    public function compileColumns($columns)
    {

        if (is_array($columns)) {
            $str = '';

// Здесь поймаем название таблиц в joins
// Если равен * то мы тогда понимаем, что нужно определить все таблицы со звездочко
// А если там не звездочка, то значит ручками указанные поля из таблиц,
//  соответственно нам не нужно получать названия таблиц
            if (count($this->columns) == 1 and reset($this->columns) == '*' and is_array($this->joins)) {

                $column = reset($this->columns);

                $str .= $this->table . '.' . $column . ', ';


                foreach ($this->joins as $join) {
                    $str .= $join['table'] . '.' . $column . ', ';
                }
            } else {
                foreach ($this->columns as $column) {
                    $str .= $column . ', ';
                }
            }

            return rtrim($str, ', ');
        }

        return '*';
    }


    private function compileWheres($where_condition) :string
    {
		if (!is_array($where_condition))
			return '';


		$sql = '';
		$i = 0;
		foreach ($where_condition as $wheres) {

			if (isset($wheres['str'])) {
				$sql .= $wheres['str'];
				if ($i == 0) $i++;
				if ($i > 1) $i = 0;
				continue;
			}


			foreach ($wheres as $key => $where) {
				if ($i == 1) {
					$where['boolean'] = '';
					$i++;
				}

				if ($where['type'] == 'NOT IN' or $where['type'] == 'IN') {
					$in = str_repeat('?,', count($where['values']) - 1) . '?';
					$sql .= $where['boolean'] . ' ' . $this->addQuotesForColumn($where['column']) . ' ' . $where['type'] . "( " . $in . " )";
				} else {
					$sql .= $where['boolean'] . ' ' . $this->addQuotesForColumn($where['column']) . ' ' . $where['operator'] . ' ? ';
				}

			}


		}

		return ' WHERE ' . ltrim($sql, 'and');
    }


    /**
     * @param $column // table.column OR column // blog.id OR id
     * @return string
     */

    private function addQuotesForColumn($column)
    {
        if (!$column)
            return true;

        $data = explode('.', $column);

		$column = '`' . $data[0] . '`';

        if (isset($data[1]))
			$column .= '.' . $data[1];

        return $column;
    }


    private function compileLimit(int $limit, int $offset = 0) :string
    {
        if (!$limit)
			return '';

		return  sprintf(' LIMIT %d, %d', $offset, $limit);
    }

    public function compileOrders($orders)
    {

        $sql = '';

        if (!is_array($orders))
            return $sql;

        $sql .= ' ORDER BY  ';
        foreach ($orders as $order) {
            if ($order['column'] and $order['direction']) {
                $sql .= $order['column'] . ' ' . $order['direction'] . ' , ';
            }
        }

        return rtrim($sql, ' ,');
    }


    private function wheresBindParam() :array
    {
		$values = [];

        if (is_array($this->wheres)) {
            foreach ($this->wheres as $wheres) {

				// Если это скобочки ( ) для группы условий , то пропускаем цикл
                if (isset($wheres['str']))
                    continue;

                foreach ($wheres as $where) {

					// Перебираем условие IN
                    if ($where['type'] == 'NOT IN' or $where['type'] == 'IN') {

                        foreach ($where['values'] as $in) {
                            $values[] = $in;
                        }

					// Перебираем обычные условия WHERE
                    } else {
                        $values[] = $where['value'];
                    }
                }
            }
        }
		return $values;
    }

//**  УДАЛЕНИЕ  */
//

    public function delete($id = null)
    {
		// If an ID is passed to the method, we will set the where clause to check the
		// ID to let developers to simply and quickly remove a single row from this
		// database without manually specifying the "where" clauses on the query.
        if (!is_null($id)) {
            $this->where('id', '=', $id);
        }

        $wheres = $this->compileWheres($this->wheres);

        try {
            $query = "DELETE FROM " . $this->prefix . $this->table . "  " . $wheres;
            $result = $this->connection->prepare($query);

            $values = $this->wheresBindParam();
            $this->saveQueries($query, $values);
            $result->execute($values);

        } catch (PDOException $e) {
            exit('Ошибка: ' . $e->getMessage() . '; Line: ' . $e->getLine() . '; SQL: ' . $sql . '; Params: ' . Tools::my_print_r($params) . '');
        }
    }


    /** ОБНОВЛЕНИЕ  */

    public function update($values)
    {
        if (empty($values)) {
            return false;
        }

        try {
            foreach ($values as $key => $item) {
                $params .= ' `' . $key . '` = ? ,';
            }
            $wheres = $this->compileWheres($this->wheres);

            $query = "UPDATE " . $this->prefix . $this->table . " SET " . rtrim($params, ',') . $wheres;
            $result = $this->connection->prepare($query);

            $values = array_values(
                array_merge($values, $this->wheresBindParam())
            ); // сбрасываем ключи массива в числовые

            $result->execute($values);

            return $this;

        } catch (PDOException $e) {
			//Logs::Instance()->write('database', 'Ошибка: ' . $e->getMessage() . '; Line: ' . $e->getLine() . '; SQL: ' . $sql . '; Params: ' . Tools::my_print_r($params) . '');
			//echo ('Ошибка: ' . $e->getMessage() . '; Line: ' . $e->getLine() . '; SQL: ' . $sql . '; Params: ');
            throw $e;
        }

    }


    /** ----======================   JOINS ============= -------------------- */
    public function join($table, $first, $operator = null, $second = null, $type = 'inner' /*, $where = false */)
    {
        $method = isset($where) ? 'where' : 'on';

		// Этот метод я пока оставлю, сейчас он не важен, но потом,
		// как обычно через пару лет я возможно его доделаю
        if ($first instanceof Closure) {
			$this->joins[] = [
				'table' => $table,
				'type' => $type
			];

			call_user_func($first, $this);
		} else {
			$this->joins[] = [
				'table' => $table,
				'first' => $first,
				'operator' => $operator,
				'second' => $second,
				'type' => $type,
				'method' => $method
			];
		}

        return $this;
    }

    public function leftJoin($table, $first, $operator = null, $second = null): Builder
	{
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function crossJoin($table, $first, $operator = null, $second = null): Builder
	{
        return $this->join($table, $first, $operator, $second, 'cross');
    }

    public function rightJoin($table, $first, $operator = null, $second = null): Builder
	{
        return $this->join($table, $first, $operator, $second, 'right');
    }


    public function andOn($first, $operator = null, $second = null): Builder
	{
        return $this->on($first, $operator, $second, 'and');
    }

    public function orOn($first, $operator = null, $second = null): Builder
	{
        return $this->on($first, $operator, $second, 'or');
    }


    public function compileJoins()
    {
        if (!is_array($this->joins))
            return false;

        $str = ' ';

        foreach ($this->joins as $join) {
            if ($join['table'] and $join['type']) {
                $str .= $join['type'] . ' JOIN ' . $this->prefix . $join['table'] . ' AS ' . $join['table'] . ' ON ';
                $on = true;
            }

            if (!$on and $join['boolean'])
                $str .= ' ' . $join['boolean'] . ' ';

            if (isset($join['boolean']))
                $on = false;

            $str .= $join['first'] . ' ' . $join['operator'] . ' ' . $join['second'] . ' ';
        }
        return $str;
    }

    public function on($first, $operator = null, $second = null, $boolean = 'and')
    {
        $this->joins[] = [
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'boolean' => $boolean
        ];
        return $this;
    }


    public function count()
    {
        try {
            $query = "SELECT count(*) as `count` FROM " . $this->prefix . $this->table . " as " . $this->table . " " .
				$this->compileJoins($this->joins) .
				$this->compileWheres($this->wheres);

            $result = $this->connection->prepare($query);
            $values = $this->wheresBindParam();
            $this->saveQueries($query, $values);
            $result->execute($values);
            $row = $result->fetch();

            return $row->count;

        } catch (PDOException $e) {
            exit('<pre>Ошибка: ' . $e->getMessage() . '; <br>Line: ' . $e->getLine() . '; <br>SQL: ' . $query . '; <br>Params: <br>' . print_r($this->wheres, true) . '</pre>');
            return null;
        }
    }

    public function paginate($currentPage, $perPage = false)
    {
        $paginator = new Pagination($this->count(), $currentPage, $perPage);
        $this->limit($paginator->limit(), $paginator->offset());
        return (object)['pagination' => $paginator, 'rows' => $this->get($this->asObject ?? null)];
    }

    //TODO Продумать, как реализовать лучше (Пока сделал наскоряк)
    public function paginateAsObject($className, $currentPage, $perPage = false): object
    {
        $this->asObject = $className;
        return $this->paginate($currentPage, $perPage);
    }

    private function saveQueries($query, $params = array())
    {
        $this->query = [
            'query' => $query,
            'params' => $params
        ];
    }

    public function toSql()
    {
        $this->compileSelect();
        return $this->query;
    }


    //public function withLang($column = false)
    //{
    //    if (!$column)
    //        $column = $this->table . '.lang';
    //    return $this->where($column, locale());
    //}
}