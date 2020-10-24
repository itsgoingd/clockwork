<?php namespace Clockwork\DataSource\Concerns;

use Clockwork\Helpers\StackFilter;
use Clockwork\Helpers\StackTrace;
use Clockwork\Request\Log;
use Clockwork\Request\Request;

// Duplicate (N+1) queries detection for EloquentDataSource, inspired by the beyondcode/laravel-query-detector package
// by Marcel Pociot (https://github.com/beyondcode/laravel-query-detector)
trait EloquentDetectDuplicateQueries
{
	protected $duplicateQueries = [];

	protected function appendDuplicateQueriesWarnings(Request $request)
	{
		$log = new Log;

		foreach ($this->duplicateQueries as $query) {
			if ($query['count'] <= 1) continue;

			$log->warning(
				"N+1 queries: {$query['model']}::{$query['relation']} loaded {$query['count']} times.",
				[ 'performance' => true, 'trace' => $query['trace'] ]
			);
		}

		$request->log()->merge($log);
	}

	protected function detectDuplicateQuery(StackTrace $trace)
	{
		$relationFrame = $trace->first(function ($frame) {
			return $frame->function == 'getRelationValue'
				|| $frame->class == \Illuminate\Database\Eloquent\Relations\Relation::class;
		});

		if (! $relationFrame || ! $relationFrame->object) return;

		if ($relationFrame->class == \Illuminate\Database\Eloquent\Relations\Relation::class) {
			$model = get_class($relationFrame->object->getParent());
			$relation = get_class($relationFrame->object->getRelated());
		} else {
			$model = get_class($relationFrame->object);
			$relation = $relationFrame->args[0];
		}

		$shortTrace = $trace->skip(StackFilter::make()
			->isNotVendor([ 'itsgoingd', 'laravel', 'illuminate' ])
			->isNotNamespace([ 'Clockwork', 'Illuminate' ]));

		$hash = implode('-', [ $model, $relation, $shortTrace->first()->file, $shortTrace->first()->line ]);

		if (! isset($this->duplicateQueries[$hash])) {
			$this->duplicateQueries[$hash] = [
				'count'    => 0,
				'model'    => $model,
				'relation' => $relation,
				'trace'    => $trace
			];
		}

		$this->duplicateQueries[$hash]['count']++;
	}
}
