<?php namespace Clockwork\Authentication;

class NullAuthenticator implements AuthenticatorInterface
{
	public function attempt(array $credentials)
	{
		return true;
	}

	public function check($token)
	{
		return true;
	}

	public function requires()
	{
		return [];
	}
}
