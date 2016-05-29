<?php namespace Clockwork\Support\Laravel\Eloquent;

use Clockwork\DataSource\EloquentDataSource;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ScopeInterface;

class ResolveModelLegacyScope implements ScopeInterface
{
	protected $dataSource;

	public function __construct(EloquentDataSource $dataSource)
	{
		$this->dataSource = $dataSource;
	}

	public function apply(Builder $builder, Model $model)
	{
		$this->dataSource->nextQueryModel = get_class($model);
	}

	public function remove(Builder $builder, Model $model)
	{
		// nothing to do here
	}
}
