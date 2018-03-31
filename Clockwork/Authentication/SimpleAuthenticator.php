<?php namespace Clockwork\Authentication;

class SimpleAuthenticator implements AuthenticatorInterface
{
	protected $password;

	public function __construct($password)
	{
		$this->password = $password;
	}

	public function attempt(array $credentials)
	{
		if (! isset($credentials['password'])) {
			return false;
		}

		if (! hash_equals($credentials['password'], $this->password)) {
			return false;
		}

		return password_hash($this->password, \PASSWORD_DEFAULT);
	}

	public function check($token)
	{
		return password_verify($this->password, $token);
	}

	public function requires()
	{
		return [ AuthenticatorInterface::REQUIRES_PASSWORD ];
	}
}
