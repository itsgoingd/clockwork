<?php namespace Clockwork\Storage;

use Clockwork\Request\Request;
use Clockwork\Storage\Storage;
use Clockwork\Support\Symfony\ProfileTransformer;

use Symfony\Component\HttpKernel\Profiler\Profiler;

// Storage wrapping Symfony profiler
class SymfonyStorage extends FileStorage
{
	// Symfony profiler instance
	protected $profiler;

	// Create a new instance, takes Symfony profiler instance as argument
	public function __construct(Profiler $profiler)
	{
		$this->profiler = $profiler;
	}

	// Store request, no-op since this is read-only storage implementation
	public function store(Request $request)
	{
		return;
	}

	// Cleanup old requests, no-op since this is read-only storage implementation
	public function cleanup($force = false)
	{
		return;
	}

	// Return all ids (Symfony profiler tokens)
	protected function ids()
	{
		return array_reverse(array_map(function ($item) {
			return $item['token'];
		}, $this->profiler->find(null, null, PHP_INT_MAX, null, null, null, null)));
	}

	// Return request instances for passed tokens
	protected function idsToRequests($tokens)
	{
		return array_filter(array_map(function ($token) {
			return $this->transformProfile($this->profiler->loadProfile($token));
		}, $tokens));
	}

	// Transform Symfony profile instance to Clockwork request
	public function transformProfile($profile)
	{
		return $profile ? (new ProfileTransformer)->transform($profile) : null;
	}
}
