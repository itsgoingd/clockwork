<?php namespace Clockwork\DataSource;

use Clockwork\Helpers\{Serializer, StackTrace};
use Clockwork\Request\Request;
use Clockwork\Support\Laravel\Eloquent\{ResolveModelLegacyScope, ResolveModelScope};

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;

// Data source for Eloquent (Laravel ORM), provides database queries, stats, model actions and counts
class EloquentDataSource extends DataSource
{
	use Concerns\EloquentDetectDuplicateQueries;

	// Database manager instance
	protected $databaseManager;

	// Event dispatcher instance
	protected $eventDispatcher;

	// Array of collected queries
	protected $queries = [];

	// Query counts by type
	protected $count = [
		'total' => 0, 'slow' => 0, 'select' => 0, 'insert' => 0, 'update' => 0, 'delete' => 0, 'other' => 0
	];

	// Collected models actions
	protected $modelsActions = [];

	// Model action counts by model, eg. [ 'retrieved' => [ User::class => 1 ] ]
	protected $modelsCount = [
		'retrieved' => [], 'created' => [], 'updated' => [], 'deleted' => []
	];

	// Whether we are collecting database queries or stats only
	protected $collectQueries = true;

	// Whether we are collecting models actions or stats only
	protected $collectModelsActions = true;

	// Whether we are collecting retrieved models as well when collecting models actions
	protected $collectModelsRetrieved = false;

	// Query execution time threshold in ms after which the query is marked as slow
	protected $slowThreshold;

	// Enable duplicate queries detection
	protected $detectDuplicateQueries = false;

	// Enable explanation of queries
	protected $explain = false;

	// Model name to associate with the next executed query, used to map queries to models
	public $nextQueryModel;

	// Create a new data source instance, takes a database manager, an event dispatcher as arguments and additional
	// options as arguments
	public function __construct(ConnectionResolverInterface $databaseManager, EventDispatcher $eventDispatcher, $collectQueries = true, $slowThreshold = null, $slowOnly = false, $detectDuplicateQueries = false, $collectModelsActions = true, $collectModelsRetrieved = false, $explain = false)
	{
		$this->databaseManager = $databaseManager;
		$this->eventDispatcher = $eventDispatcher;

		$this->collectQueries         = $collectQueries;
		$this->slowThreshold          = $slowThreshold;
		$this->detectDuplicateQueries = $detectDuplicateQueries;
		$this->collectModelsActions   = $collectModelsActions;
		$this->collectModelsRetrieved = $collectModelsRetrieved;
		$this->explain                = $explain;

		if ($slowOnly) $this->addFilter(function ($query) { return $query['duration'] > $this->slowThreshold; });
	}

	// Adds ran database queries, query counts, models actions and models counts to the request
	public function resolve(Request $request)
	{
		$request->databaseQueries = array_merge($request->databaseQueries, $this->queries);

		$request->databaseQueriesCount += $this->count['total'];
		$request->databaseSlowQueries  += $this->count['slow'];
		$request->databaseSelects      += $this->count['select'];
		$request->databaseInserts      += $this->count['insert'];
		$request->databaseUpdates      += $this->count['update'];
		$request->databaseDeletes      += $this->count['delete'];
		$request->databaseOthers       += $this->count['other'];

		$request->modelsActions = array_merge($request->modelsActions, $this->modelsActions);

		$request->modelsRetrieved = $this->modelsCount['retrieved'];
		$request->modelsCreated   = $this->modelsCount['created'];
		$request->modelsUpdated   = $this->modelsCount['updated'];
		$request->modelsDeleted   = $this->modelsCount['deleted'];

		$this->appendDuplicateQueriesWarnings($request);

		return $request;
	}

	// Reset the data source to an empty state, clearing any collected data
	public function reset()
	{
		$this->queries = [];
		$this->count = [
			'total' => 0, 'slow' => 0, 'select' => 0, 'insert' => 0, 'update' => 0, 'delete' => 0, 'other' => 0
		];

		$this->modelsActions = [];
		$this->modelsCount = [
			'retrieved' => [], 'created' => [], 'updated' => [], 'deleted' => []
		];

		$this->nextQueryModel = null;
	}

	// Start listening to Eloquent events
	public function listenToEvents()
	{
		if ($scope = $this->getModelResolvingScope()) {
			$this->eventDispatcher->listen('eloquent.booted: *', function ($model, $data = null) use ($scope) {
				if (is_string($model) && is_array($data)) { // Laravel 5.4 wildcard event
					$model = reset($data);
				}

				$model->addGlobalScope($scope);
			});
		}

		if (class_exists(\Illuminate\Database\Events\QueryExecuted::class)) {
			// Laravel 5.2 and up
			$this->eventDispatcher->listen(\Illuminate\Database\Events\QueryExecuted::class, function ($event) {
				$this->registerQuery($event);
			});
		} else {
			// Laravel 5.0 to 5.1
			$this->eventDispatcher->listen('illuminate.query', function ($event) {
				$this->registerLegacyQuery($event);
			});
		}

		// Laravel 5.2 and up
		if (class_exists(\Illuminate\Database\Events\TransactionBeginning::class)) {
			$this->eventDispatcher->listen(\Illuminate\Database\Events\TransactionBeginning::class, function ($event) {
				$this->registerTransactionQuery($event, 'START TRANSACTION');
			});
		}
		if (class_exists(\Illuminate\Database\Events\TransactionCommitted::class)) {
			$this->eventDispatcher->listen(\Illuminate\Database\Events\TransactionCommitted::class, function ($event) {
				$this->registerTransactionQuery($event, 'COMMIT');
			});
		}
		if (class_exists(\Illuminate\Database\Events\TransactionRolledBack::class)) {
			$this->eventDispatcher->listen(\Illuminate\Database\Events\TransactionRolledBack::class, function ($event) {
				$this->registerTransactionQuery($event, 'ROLLBACK');
			});
		}

		// register all event listeners individually so we don't have to regex the event type and support Laravel <5.4
		$this->listenToModelEvent('retrieved');
		$this->listenToModelEvent('created');
		$this->listenToModelEvent('updated');
		$this->listenToModelEvent('deleted');
	}

	// Register a listener collecting model events of specified type
	protected function listenToModelEvent($event)
	{
		$this->eventDispatcher->listen("eloquent.{$event}: *", function ($model, $data = null) use ($event) {
			if (is_string($model) && is_array($data)) { // Laravel 5.4 wildcard event
				$model = reset($data);
			}

			$this->collectModelEvent($event, $model);
		});
	}

	// Collect an executed database query
	protected function registerQuery($event)
	{
		$trace = StackTrace::get([ 'arguments' => $this->detectDuplicateQueries ])->resolveViewName();

		if ($this->detectDuplicateQueries) $this->detectDuplicateQuery($trace);

		$query = [
			'query'      => $this->createRunnableQuery($event->sql, $event->bindings, $event->connectionName),
			'duration'   => $event->time,
			'connection' => $event->connectionName,
			'time'       => microtime(true) - $event->time / 1000,
			'trace'      => (new Serializer)->trace($trace),
			'model'      => $this->nextQueryModel,
			'tags'       => $this->slowThreshold !== null && $event->time > $this->slowThreshold ? [ 'slow' ] : [],
			'explanation'=> $this->explain && str_starts_with($event->sql, 'SELECT') ? array_map(fn ($row) => $row->{'QUERY PLAN'}, $this->databaseManager->connection($event->connectionName)->select('EXPLAIN ANALYZE ' . $event->sql, $event->bindings)) : null
		];

		$this->nextQueryModel = null;

		if (! $this->passesFilters([ $query, $trace ], 'early')) return;

		$this->incrementQueryCount($query);

		if (! $this->collectQueries || ! $this->passesFilters([ $query, $trace ])) return;

		$this->queries[] = $query;
	}

	// Collect an executed database query (pre Laravel 5.2)
	protected function registerLegacyQuery($sql, $bindings, $time, $connection)
	{
		return $this->registerQuery((object) [
			'sql'            => $sql,
			'bindings'       => $bindings,
			'time'           => $time,
			'connectionName' => $connection
		]);
	}

	// Collect an executed transaction query
	protected function registerTransactionQuery($event, $name)
	{
		$trace = StackTrace::get()->resolveViewName();

		$query = [
			'query'      => $name,
			'duration'   => 0,
			'connection' => $event->connectionName,
			'time'       => microtime(true),
			'trace'      => (new Serializer)->trace($trace),
			'model'      => null,
			'tags'       => []
		];

		if (! $this->collectQueries) return;

		$this->queries[] = $query;
	}

	// Collect a model event and update stats
	protected function collectModelEvent($event, $model)
	{
		$lastQuery = ($queryCount = count($this->queries)) ? $this->queries[$queryCount - 1] : null;

		$action = [
			'model'      => $modelClass = get_class($model),
			'key'        => $this->getModelKey($model),
			'action'     => $event,
			'attributes' => $this->collectModelsRetrieved && $event == 'retrieved' ? $model->getOriginal() : [],
			'changes'    => $this->collectModelsActions && method_exists($model, 'getChanges') ? $model->getChanges() : [],
			'time'       => microtime(true) / 1000,
			'query'      => $lastQuery ? $lastQuery['query'] : null,
			'duration'   => $lastQuery ? $lastQuery['duration'] : null,
			'connection' => $lastQuery ? $lastQuery['connection'] : null,
			'trace'      => null,
			'tags'       => []
		];

		if ($lastQuery) $this->queries[$queryCount - 1]['model'] = $modelClass;

		if (! $this->passesFilters([ $action ], 'models-early')) return;

		$this->incrementModelsCount($action['action'], $action['model']);

		if (! $this->collectModelsActions) return;
		if (! $this->collectModelsRetrieved && $event == 'retrieved') return;
		if (! $this->passesFilters([ $action ], 'models')) return;

		$action['trace'] = (new Serializer)->trace(StackTrace::get()->resolveViewName());

		$this->modelsActions[] = $action;
	}

	// Takes a query, an array of bindings and the connection as arguments, returns runnable query with upper-cased keywords
	protected function createRunnableQuery($query, $bindings, $connection)
	{
		// add bindings to query
		$bindings = $this->databaseManager->connection($connection)->prepareBindings($bindings);

		$index = 0;
		$query = preg_replace_callback('/\'[^\']*\'|[^?]\?|\W:[a-z]+/', function ($matches) use ($bindings, $connection, &$index) {
			$match = $matches[0];

			if ($match[0] == '\'') { // quoted string
				return $match;
			} elseif ($match[1] == '?' && isset($bindings[$index])) { // question-mark binding
				$binding = $this->quoteBinding($bindings[$index++], $connection);
			} elseif ($match[1] == ':' && isset($bindings[substr($match, 2)])) { // named binding
				$binding = $this->quoteBinding($bindings[substr($match, 2)], $connection);
			} else {
				return $match;
			}

			// convert binary bindings to hexadecimal representation
			if (! preg_match('//u', (string) $binding)) $binding = '0x' . bin2hex($binding);

			return $match[0] . ((string) $binding);
		}, $query);

		// highlight keywords
		$keywords = [
			'select', 'insert', 'update', 'delete', 'into', 'values', 'set', 'where', 'from', 'limit', 'is', 'null',
			'having', 'group by', 'order by', 'asc', 'desc'
		];
		$regexp = '/\b' . implode('\b|\b', $keywords) . '\b/i';

		return preg_replace_callback($regexp, function ($match) { return strtoupper($match[0]); }, $query);
	}

	// Takes a query binding and a connection name, returns a quoted binding value
	protected function quoteBinding($binding, $connection)
	{
		$connection = $this->databaseManager->connection($connection);

		if (! method_exists($connection, 'getPdo')) return;

		$pdo = $connection->getPdo();

		if ($pdo === null) return;

		if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'odbc' || $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'crate') {
			// PDO_ODBC and PDO Crate driver doesn't support the quote method, apply simple MSSQL style quoting instead - Crate sometimes uses a object as a binding - for json support
			$binding = is_object($binding) ? json_encode($binding) : $binding;
			return "'" . str_replace("'", "''", $binding) . "'";
		}

		return is_string($binding) ? $pdo->quote($binding) : $binding;
	}

	// Increment query counts for collected query
	protected function incrementQueryCount($query)
	{
		$sql = ltrim($query['query']);

		$this->count['total']++;

		if (preg_match('/^select\b/i', $sql)) {
			$this->count['select']++;
		} elseif (preg_match('/^insert\b/i', $sql)) {
			$this->count['insert']++;
		} elseif (preg_match('/^update\b/i', $sql)) {
			$this->count['update']++;
		} elseif (preg_match('/^delete\b/i', $sql)) {
			$this->count['delete']++;
		} else {
			$this->count['other']++;
		}

		if (in_array('slow', $query['tags'])) {
			$this->count['slow']++;
		}
	}

	// Increment model counts for collected model action
	protected function incrementModelsCount($action, $model)
	{
		if (! isset($this->modelsCount[$action][$model])) {
			$this->modelsCount[$action][$model] = 0;
		}

		$this->modelsCount[$action][$model]++;
	}

	// Returns model resolving scope for the installed Laravel version
	protected function getModelResolvingScope()
	{
		if (interface_exists(\Illuminate\Database\Eloquent\ScopeInterface::class)) {
			// Laravel 5.0 to 5.1
			return new ResolveModelLegacyScope($this);
		}

		return new ResolveModelScope($this);
	}

	// Returns model key without crashing when using Eloquent strict mode and it's not loaded
	protected function getModelKey($model)
	{
		// Some applications use non-string primary keys, even when this is not supported by Laravel
		if (! is_string($model->getKeyName())) return;

		try {
			return $model->getKey();
		} catch (\Illuminate\Database\Eloquent\MissingAttributeException $e) {}
	}
}
