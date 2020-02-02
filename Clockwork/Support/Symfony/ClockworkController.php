<?php namespace Clockwork\Support\Symfony;

use Clockwork\Clockwork;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ClockworkController extends AbstractController
{
	protected $clockwork;
	protected $support;

	public function __construct(Clockwork $clockwork, ClockworkSupport $support)
	{
		$this->clockwork = $clockwork;
		$this->support = $support;
	}

	public function authenticate(Request $request)
	{
		$token = $this->clockwork->getAuthenticator()->attempt($request->request->all());

		return new JsonResponse([ 'token' => $token ], $token ? 200 : 403);
	}

	public function getData(Request $request, $id = null, $direction = null, $count = null)
	{
		return $this->support->getData($request, $id, $direction, $count);
	}

	public function webIndex(Request $request)
	{
		if ($this->support->isWebUsingDarkTheme() && ! $request->query->has('dark')) {
			return $this->redirect('/__clockwork/app?dark');
		}

		return $this->support->getWebAsset('index.html');
	}

	public function webAsset($path)
	{
		return $this->support->getWebAsset($path);
	}

	public function webRedirect()
	{
		return $this->redirect('/__clockwork/app');
	}
}
