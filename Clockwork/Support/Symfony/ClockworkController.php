<?php namespace Clockwork\Support\Symfony;

use Clockwork\Clockwork;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Profiler\Profiler;

class ClockworkController extends Controller
{
	private $clockwork;
	private $profiler;

	public function __construct(Clockwork $clockwork, Profiler $profiler)
	{
		$this->clockwork = $clockwork;
		$this->profiler = $profiler;
	}

	public function getData($id = null, $direction = null, $count = null)
	{
		$this->profiler->disable();

		return $this->container->get('clockwork.support')->getData($id, $direction, $count);
	}
}
