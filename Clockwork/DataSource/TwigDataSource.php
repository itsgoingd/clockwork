<?php namespace Clockwork\DataSource;

use Clockwork\Request\Request;
use Clockwork\Support\Twig\ProfilerClockworkDumper;

use Twig\Environment;
use Twig\Extension\ProfilerExtension;
use Twig\Profiler\Profile;


// Data source for Twig, provides rendered views
class TwigDataSource extends DataSource
{
	// Twig environment instance
	protected $twig;

	// Twig profile instance
	protected $profile;

	// Create a new data source, takes Twig instance as an argument
	public function __construct(Environment $twig)
	{
		$this->twig = $twig;
	}

	// Register the Twig profiler extension
	public function listenToEvents()
	{
		$this->twig->addExtension(
			new ProfilerExtension(($this->profile = new Profile()))
		);
	}

	// Adds rendered views to the request
	public function resolve(Request $request)
	{
		$timeline = (new ProfilerClockworkDumper)->dump($this->profile);

		$request->viewsData = array_merge($request->viewsData, $timeline->finalize());

		return $request;
	}
}
