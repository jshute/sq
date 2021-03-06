<?php

/**
 * SQL model implementation
 *
 * Provides an eloquent object oriented interface for managing mySQL records.
 * Handles basic CRUD operations as well as straight SQL queries.
 */

class sql extends model {

	// Static PDO database connection
	protected static $conn = false;

	// Override component constructor to add database connection code
	public function __construct($options) {
		parent::__construct($options);

		// Assume the name of the table is the same as the name of the model
		// unless specified otherwise
		if (!isset($this->options['table'])) {
			$this->options['table'] = $this->options['name'];
		}

		// Set up new PDO connection if it doesn't already exist
		if (!self::$conn) {
			self::$conn = new PDO(
				$this->options['dbtype'].':host='.$this->options['host'].';dbname='.$this->options['dbname'].';port='.$this->options['port'],
				$this->options['username'],
				$this->options['password']);

			self::$conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
			self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}

		if ($this->options['autogenerate-table'] && $this->options['schema']) {
			$this->make();
		}
	}

	// Returns only alphanumeric characters and underscores. A whitelist of
	// values to not sanitize can be passed in as a second argument.
	public function sanitize($value, $whitelist = []) {

		// If an array is passed in sanitize every key
		if (is_array($value)) {
			foreach ($value as $key => $item) {
				$value[$key] = $this->sanitize($item, $whitelist);
			}

			return $value;
		}


		// If value is in whitelist return whitelisted value
		if (in_array($value, $whitelist)) {
			return $value;
		}

		// Remove all characters except alphnumeric and underscores
		return preg_replace('/[^A-Za-z0-9_]/', '', $value);
	}

	/**
	 * Creates a new row in the table
	 *
	 * An array of data may be passed in. These values will overwrite any values
	 * set in the model.
	 */
	public function create($data = null) {

		// Unset a numberic id key if one exists
		if (empty($this->data['id']) || is_numeric($this->data['id'])) {
			unset($this->data['id']);
		}

		$this->set($data);

		// Mark record as belonging to the current user if marked as a user
		// specific model
		if ($this->options['user-specific'] && !isset($this->data['users_id'])) {
			$this->data['users_id'] = sq::auth()->user->id;
		}

		$values = [];
		foreach ($this->sanitize(array_keys($this->data)) as $key) {
			$values[] = ":$key";
		}

		$columns = implode(',', $this->sanitize(array_keys($this->data)));
		$values = implode(',', $values);

		$query = 'INSERT INTO '.$this->sanitize($this->options['table'])."
			($columns) VALUES ($values)";

		if ($this->checkDuplicate($this->data)) {
			$this->query($query, $this->data);
		}

		return $this;
	}

	/**
	 * Reads rows from table and sets them to the model object
	 *
	 * The columns to read from the table row may be specified in the optional
	 * columns argument. By default every column will be read. Values from the
	 * record are assigned to the model. If limit is true the values are
	 * directly applied to the object otherwise a list of model objects is set
	 * to the object.
	 */
	public function read($columns = null) {
		$columns = $this->sanitize($columns);

		if (is_array($columns)) {
			$columns = implode(',', $columns);
		} else {
			$columns = '*';
		}

		$query = "SELECT $columns FROM ".$this->sanitize($this->options['table']);

		$query .= $this->parseWhere();
		$query .= $this->parseOrder();
		$query .= $this->parseLimit();

		return $this->query($query);
	}

	/**
	 * Updates rows in table
	 *
	 * Updates all rows matching the where statement with the data in the model
	 * object. Data and a where statement values may be passed in as optional
	 * arguments.
	 */
	public function update($data = null, $where = null) {
		$this->set($data);

		if ($where) {
			$this->where($where);
		}

		$this->updateDatabase();
		$this->onRelated('update');

		return $this;
	}

	/**
	 * Deletes rows in table
	 *
	 * All rows matching the where statement in the current table are deleted.
	 * A where statment may be passed in directly as a shorthand.
	 */
	public function delete($where = null) {
		if ($where) {
			$this->where($where);
		}

		$query = 'DELETE FROM '.$this->sanitize($this->options['table']);

		$query .= $this->parseWhere();
		$query .= $this->parseLimit();

		$this->limit()->onRelated('delete');
		$this->query($query);
		$this->data = [];

		return $this;
	}

	/**
	 * Add a straight SQL where query
	 *
	 * Chainable method that allows a straight sql where query to be used for
	 * advanced searches that are too much for the where method.
	 *
	 * WARNING: raw where statements are executed 'as is' without any safety
	 * checking.
	 */
	public function whereRaw($query) {
		$this->options['where-raw'] = $query;

		return $this;
	}

	/**
	 * Returns an empty model
	 *
	 * schema() sets all the available values in the model to null. Useful for
	 * making create forms or other actions where having null data is necessary.
	 */
	public function columns() {
		$this->limit();

		return $this->query('SHOW COLUMNS FROM '.$this->sanitize($this->options['table']));
	}

	/**
	 * Checks if table exists
	 *
	 * exists() checks to see of the referenced table exists for the model and
	 * returns a boolean.
	 */
	public function exists() {
		try {
			self::$conn->query('SELECT 1 FROM '.$this->sanitize($this->options['table']).' LIMIT 1');
		} catch (Exception $e) {
			return false;
		}

		return true;
	}

	/**
	 * Basic create table functionality
	 *
	 * Makes a table with the passed in schema or schema from the model options.
	 * Schema is an associative array column names as keys and SQL definition as
	 * values. An auto incrementing id column is added if none is specified.
	 */
	public function make($schema = null) {

		// Skip if the table already exists
		if ($this->exists()) {
			return $this;
		}

		if (!$schema) {
			$schema = $this->schema();
		} elseif (is_string($schema)) {
			$schema = sq::config($schema);
		}

		$query = 'CREATE TABLE '.$this->sanitize($this->options['table']).' (';

		if (!array_key_exists('id', $schema)) {
			$query .= 'id INT(11) NOT NULL AUTO_INCREMENT, ';
		}

		foreach ($schema as $key => $val) {
			$schema[$key] = $key.' '.$val;
		}

		$query .= implode(',', $schema);
		$query .= ', PRIMARY KEY (id))';

		return $this->query($query);
	}

	// Returns count of records matched by the where query
	public function count() {
		if ($this->isRead && !$this->options['pages']) {
			return parent::count();
		}

		$query = 'SELECT COUNT(*) FROM '.$this->sanitize($this->options['table']);
		$query .= $this->parseWhere();

		return self::$conn->query($query)->fetchColumn();
	}

	// Execute a straight mySQL query. Used behind the scenes by all the CRUD
	// interactions.
	public function query($query, $data = []) {
		$handle = self::$conn->prepare($query);

		// Guard against bad query
		if (!$handle) {
			return $this;
		}

		// Bind the data values to the query
		foreach ($data as $key => $val) {
			if (!is_array($val) && !is_object($val)) {
				if ($val === null) {
					$handle->bindValue(":$key", null, PDO::PARAM_NULL);
				} else {
					$handle->bindValue(":$key", $val);
				}
			}
		}

		$handle->setFetchMode(PDO::FETCH_ASSOC);
		$handle->execute();

		// Call the appropriate method to handle the query result
		if (strpos($query, 'SELECT') !== false) {
			$this->selectQuery($handle);
		} elseif (strpos($query, 'INSERT') !== false) {
			$this->insertQuery();
		} elseif (strpos($query, 'SHOW COLUMNS') !== false) {
			$this->showColumnsQuery($handle);
		}

		// Mark the model in post read state
		$this->isRead = true;

		return $this;
	}

	// Update some basic data to the model after inserting into SQL
	private function insertQuery() {

		// When inserting always stick the last inserted id into the model
		$this->data['id'] = self::$conn->lastInsertId();
	}

	// Insert data into the model from the query result. For single queries add
	// the data to the current model otherwise create child models and add them
	// as a list.
	private function selectQuery($handle) {
		if ($this->isSingle()) {
			$data = $handle->fetch();

			if ($data) {
				$this->data = $this->cleanData($data);
			}
		} else {
			while ($row = $handle->fetch()) {

				// Create child model
				$model = sq::model($this->options['name'], [
					'use-layout' => false,
					'load-relations' => $this->options['load-relations']
				])->where($row['id']);

				// Mark child model in post read state
				$model->isRead = true;

				$model->data = $this->cleanData($row);
				$this->data[] = $model;
			}
		}

		// Call relation setup if enabled
		if ($this->options['load-relations']) {
			$this->relateModel();
		}
	}

	// Sets the model to the equivelent of an empty record with the columns as
	// keys but no values
	private function showColumnsQuery($handle) {
		$columns = [];
		while ($row = $handle->fetch()) {
			$columns[$row['Field']] = null;
		}

		$this->set($columns);
	}

	// Removes slashes from data
	private function cleanData($data) {
		return array_map(function($item) {
			if (is_string($item)) {
				return stripcslashes($item);
			}

			return $item;
		}, $data);
	}

	// Utility function to update data in the database from what is in the model
	private function updateDatabase() {
		$data = array_diff_key($this->data, array_flip(['id', 'created', 'edited']));
		$query = 'UPDATE '.$this->sanitize($this->options['table']);

		$set = [];
		foreach ($data as $key => $val) {

			// Avoid setting keys for related models
			if (!is_array($val) && !is_object($val)) {
				$key = $this->sanitize($key);

				if ($val === null) {
					unset($data[$key]);
					$set[] = "$key = NULL";
				} else {
					$set[] = "$key = :$key";
				}
			}
		}

		$query .= ' SET '.implode(',', $set);
		$query .= $this->parseWhere();

		if (!empty($set)) {
			$this->query($query, $data);
		}
	}

	// Generates SQL order statement
	private function parseOrder() {
		if ($this->options['order'] && !$this->isSingle()) {
			return ' ORDER BY '.$this->sanitize($this->options['order']).'
				'.$this->sanitize($this->options['order-direction']).', id ASC';
		}
	}

	// Generates SQL where statement from array
	private function parseWhere() {

		// If model represents a record and no where statement is applied assume
		// where is for the current model
		if (empty($this->options['where']) && isset($this->data['id'])) {
			$this->where($this->data['id']);
		}

		$query = null;

		if ($this->options['user-specific']) {
			$this->options['where'] += ['users_id' => sq::auth()->user->id];
		}

		if ($this->options['where']) {

			$i = 0;
			foreach ($this->options['where'] as $key => $val) {
				if ($i++) {
					$query .= ' '.$this->sanitize($this->options['where-operation']).' ';
				} else {
					$query .= ' WHERE ';
				}

				if (is_array($val)) {
					$query .= '(';
					$j = 0;

					foreach ($val as $param) {
						if ($j++) {
							$query .= ' OR ';
						}

						// Support explicit null in sql querries
						if ($param === null) {
							$query .= $this->sanitize($key).' IS NULL';
						} else {
							$query .= $this->sanitize($key).' = '.self::$conn->quote($param);
						}
					}

					$query .= ')';

				// Support explicit null in sql querries
				} elseif ($val === null) {
					$query .= $this->sanitize($key).' IS NULL';
				} else {
					$query .= $this->sanitize($key).' = '.self::$conn->quote($val);
				}
			}
		}

		$query .= $this->parseWhereRaw();

		return $query;
	}

	// Adds straight SQL where statement to the model
	private function parseWhereRaw() {
		$query = null;

		if ($this->options['where-raw']) {
			if ($this->options['where']) {
				$query .= ' '.$this->sanitize($this->options['where-operation']).' ';
			} else {
				$query .= ' WHERE ';
			}

			$query .= $this->options['where-raw'];
		}

		return $query;
	}

	// Generates SQL limit statement
	private function parseLimit() {
		if ($this->options['limit']) {
			$limit = $this->sanitize($this->options['limit']);

			if (is_array($limit)) {
				$limit = implode(',', $limit);
			}

			return ' LIMIT '.$limit;
		}
	}
}
