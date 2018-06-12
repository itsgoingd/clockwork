<?php namespace Clockwork\Support\Symfony;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Profiler\Profiler;

class ClockworkController extends Controller
{
	protected $clockwork;
	protected $profiler;

	public function __construct(ClockworkSupport $clockwork, Profiler $profiler)
	{
		$this->clockwork = $clockwork;
		$this->profiler = $profiler;
	}

	public function getData($id = null, $direction = null, $count = null)
	{
		$this->profiler->disable();

		return $this->clockwork->getData($id, $direction, $count);
	}

	public function webIndex(Request $request)
	{
		$this->profiler->disable();

		if ($this->clockwork->isWebUsingDarkTheme() && ! $request->query->has('dark')) {
			return $this->redirect('/__clockwork/app?dark');
		}

		return $this->clockwork->getWebAsset('app.html');
	}

	public function webAsset($path)
	{
		$this->profiler->disable();

		return $this->clockwork->getWebAsset("assets/{$path}");
	}

	public function webRedirect()
	{
		$this->profiler->disable();

		return $this->redirect('/__clockwork/app');
	}
}
