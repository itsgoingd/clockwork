<?php namespace Clockwork\Storage;

use Clockwork\Request\Request;

// Rules for searching requests using SQL storage, builds the SQL query conditions
class SqlSearch extends Search
{
	// Generated SQL query and bindings
	public $query;
	public $bindings;

	// Internal representation of the SQL where conditions
	protected $conditions;

	// Create a new instance, takes search parameters
	public function __construct($search = [])
	{
		parent::__construct($search);

		list($this->conditions, $this->bindings) = $this->resolveConditions();

		$this->buildQuery();
	}

	// Creates a new isntance from a base Search class instance
	public static function fromBase(Search $search = null)
	{
		return new static((array) $search);
	}

	// Add an additional where condition, takes the SQL condition and array of bindings
	public function addCondition($condition, $bindings = [])
	{
		$this->conditions = array_merge([ $condition ], $this->conditions);
		$this->bindings = array_merge($bindings, $this->bindings);
		$this->buildQuery();

		return $this;
	}

	// Resolve SQL conditions and bindings based on the search parameters
	protected function resolveConditions()
	{
		if ($this->isEmpty()) return [ [], [] ];

		$conditions = array_filter([
			$this->resolveStringCondition([ 'type' ], $this->type),
			$this->resolveStringCondition([ 'uri', 'commandName', 'jobName', 'testName' ], array_merge($this->uri, $this->name)),
			$this->resolveStringCondition([ 'controller' ], $this->controller),
			$this->resolveExactCondition([ 'method' ], $this->method),
			$this->resolveNumberCondition([ 'responseStatus', 'commandExitCode', 'jobStatus', 'testStatus' ], $this->status),
			$this->resolveNumberCondition([ 'responseDuration' ], $this->time),
			$this->resolveDateCondition([ 'time' ], $this->received)
		]);

		$sql = array_map(function ($condition) { return $condition[0]; }, $conditions);
		$bindings = array_reduce($conditions, function ($bindings, $condition) {
			return array_merge($bindings, $condition[1]);
		}, []);

		return [ $sql, $bindings ];
	}

	// Resolve a date type condition and bindings
	protected function resolveDateCondition($fields, $inputs)
	{
		if (! count($inputs)) return null;

		$bindings = [];
		$conditions = implode(' OR ', array_map(function ($field) use ($inputs, &$bindings) {
			return implode(' OR ', array_map(function ($input, $index) use ($field, &$bindings) {
				if (preg_match('/^<(.+)$/', $input, $match)) {
					$bindings["{$field}{$index}"] = $match[1];
					return "{$field} < :{$field}{$index}";
				} elseif (preg_match('/^>(.+)$/', $input, $match)) {
					$bindings["{$field}{$index}"] = $match[1];
					return "{$field} > :{$field}{$index}";
				}
			}, $inputs, array_keys($inputs)));
		}, $fields));

		return [ "({$conditions})", $bindings ];
	}

	// Resolve an exact type condition and bindings
	protected function resolveExactCondition($fields, $inputs)
	{
		if (! count($inputs)) return null;

		$bindings = [];
		$values = implode(' OR ', array_map(function ($field) use ($inputs, &$bindings) {
			return implode(', ', array_map(function ($input, $index) use ($field, &$bindings) {
				$bindings["{$field}{$index}"] = $input;
				return ":{$field}{$index}";
			}, $inputs, array_keys($inputs)));
		}, $fields));

		return [ "{$field} IN ({$values})", $bindings ];
	}

	// Resolve a number type condition and bindings
	protected function resolveNumberCondition($fields, $inputs)
	{
		if (! count($inputs)) return null;

		$bindings = [];
		$conditions = implode(' OR ', array_map(function ($field) use ($inputs, &$bindings) {
			return implode(' OR ', array_map(function ($input, $index) use ($field, &$bindings) {
				if (preg_match('/^<(\d+(?:\.\d+)?)$/', $input, $match)) {
					$bindings["{$field}{$index}"] = $match[1];
					return "{$field} < :{$field}{$index}";
				} elseif (preg_match('/^>(\d+(?:\.\d+)?)$/', $input, $match)) {
					$bindings["{$field}{$index}"] = $match[1];
					return "{$field} > :{$field}{$index}";
				} elseif (preg_match('/^(\d+(?:\.\d+)?)-(\d+(?:\.\d+)?)$/', $input, $match)) {
					$bindings["{$field}{$index}from"] = $match[1];
					$bindings["{$field}{$index}to"] = $match[2];
					return "({$field} > :{$field}{$index}from AND {$field} < :{$field}{$index}to)";
				} else {
					$bindings["{$field}{$index}"] = $input;
					return "{$field} = :{$field}{$index}";
				}
			}, $inputs, array_keys($inputs)));
		}, $fields));

		return [ "({$conditions})", $bindings ];
	}

	// Resolve a string type condition and bindings
	protected function resolveStringCondition($fields, $inputs)
	{
		if (! count($inputs)) return null;

		$bindings = [];
		$conditions = implode(' OR ', array_map(function ($field) use ($inputs, &$bindings) {
			return implode(' OR ', array_map(function ($input, $index) use ($field, &$bindings) {
				$bindings["{$field}{$index}"] = $input;
				return "{$field} LIKE :{$field}{$index}";
			}, $inputs, array_keys($inputs)));
		}, $fields));

		return [ "({$conditions})", $bindings ];
	}

	// Build the where part of the SQL query
	protected function buildQuery()
	{
		$this->query = count($this->conditions) ? 'WHERE ' . implode(' AND ', $this->conditions) : '';
	}
}
