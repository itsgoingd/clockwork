<?php namespace Clockwork\Support\Laravel\Eloquent;

use Clockwork\DataSource\EloquentDataSource;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ScopeInterface;

class ResolveModelOldScope implements ScopeInterface
{
	protected $dataSource;

	public function __construct(EloquentDataSource $dataSource)
	{
		$this->dataSource = $dataSource;
	}

	public function apply(Builder $builder)
	{
		$this->dataSource->nextQueryModel = get_class($builder->getModel());
	}

	public function remove(Builder $builder)
	{
		// nothing to do here
	}
}
