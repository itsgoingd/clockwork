<?php namespace Clockwork\Support\Symfony;

use Clockwork\Clockwork;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Profiler\Profiler;

class ClockworkController extends Controller
{
	protected $clockwork;
	protected $profiler;

	public function __construct(Clockwork $clockwork, ClockworkSupport $support, Profiler $profiler)
	{
		$this->clockwork = $clockwork;
		$this->support = $support;
		$this->profiler = $profiler;
	}

	public function authenticate(Request $request)
	{
		$token = $this->clockwork->getAuthenticator()->attempt($request->request->all());

		return new JsonResponse([ 'token' => $token ], $token ? 200 : 403);
	}

	public function getData(Request $request, $id = null, $direction = null, $count = null)
	{
		$this->profiler->disable();

		return $this->support->getData($request, $id, $direction, $count);
	}

	public function webIndex(Request $request)
	{
		$this->profiler->disable();

		if ($this->support->isWebUsingDarkTheme() && ! $request->query->has('dark')) {
			return $this->redirect('/__clockwork/app?dark');
		}

		return $this->support->getWebAsset('index.html');
	}

	public function webAsset($path)
	{
		$this->profiler->disable();

		return $this->support->getWebAsset($path);
	}

	public function webRedirect()
	{
		$this->profiler->disable();

		return $this->redirect('/__clockwork/app');
	}
}
