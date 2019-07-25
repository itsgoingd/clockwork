<?php namespace Clockwork\Storage;

use Clockwork\Request\Request;

class SqlSearch extends Search
{
	public $query;
	public $bindings;

	protected $conditions;

	public function __construct($search = [])
	{
		parent::__construct($search);

		list($this->conditions, $this->bindings) = $this->resolveConditions();

		$this->buildQuery();
	}

	public static function fromBase(Search $search)
	{
		return new static((array) $search);
	}

	public function addCondition($condition, $bindings = [])
	{
		$this->conditions = array_merge([ $condition ], $this->conditions);
		$this->bindings = array_merge($bindings, $this->bindings);
		$this->buildQuery();

		return $this;
	}

	protected function resolveConditions()
	{
		if ($this->isEmpty()) return [ [], [] ];

		$conditions = array_filter([
			$this->resolveStringCondition('uri', $this->uri),
			$this->resolveStringCondition('controller', $this->controller),
			$this->resolveExactCondition('method', $this->method),
			$this->resolveNumberCondition('responseStatus', $this->status),
			$this->resolveNumberCondition('responseDuration', $this->time),
			$this->resolveDateCondition('time', $this->received)
		]);

		$sql = array_map(function ($condition) { return $condition[0]; }, $conditions);
		$bindings = array_reduce($conditions, function ($bindings, $condition) {
			return array_merge($bindings, $condition[1]);
		}, []);

		return [ $sql, $bindings ];
	}

	protected function resolveDateCondition($field, $inputs)
	{
		if (! count($inputs)) return null;

		$bindings = [];
		$conditions = implode(' OR ', array_map(function ($input, $index) use ($field, &$bindings) {
			if (preg_match('/^<(.+)$/', $input, $match)) {
				$bindings["{$field}{$index}"] = $match[1];
				return "{$field} < :{$field}{$index}";
			} elseif (preg_match('/^>(.+)$/', $input, $match)) {
				$bindings["{$field}{$index}"] = $match[1];
				return "{$field} > :{$field}{$index}";
			}
		}, $inputs, array_keys($inputs)));

		return [ "({$conditions})", $bindings ];
	}

	protected function resolveExactCondition($field, $inputs)
	{
		if (! count($inputs)) return null;

		$bindings = [];
		$values = implode(', ', array_map(function ($input, $index) use ($field, &$bindings) {
			$bindings["{$field}{$index}"] = $input;
			return ":{$field}{$index}";
		}, $inputs, array_keys($inputs)));

		return [ "{$field} IN ({$values})", $bindings ];
	}

	protected function resolveNumberCondition($field, $inputs)
	{
		if (! count($inputs)) return null;

		$bindings = [];
		$conditions = implode(' OR ', array_map(function ($input, $index) use ($field, &$bindings) {
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

		return [ "({$conditions})", $bindings ];
	}

	protected function resolveStringCondition($field, $inputs)
	{
		if (! count($inputs)) return null;

		$bindings = [];
		$conditions = implode(' OR ', array_map(function ($input, $index) use ($field, &$bindings) {
			$bindings["{$field}{$index}"] = $input;
			return "{$field} LIKE :{$field}{$index}";
		}, $inputs, array_keys($inputs)));

		return [ "({$conditions})", $bindings ];
	}

	protected function buildQuery()
	{
		$this->query = count($this->conditions) ? 'WHERE ' . implode(' AND ', $this->conditions) : '';
	}
}
