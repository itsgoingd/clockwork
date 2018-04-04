<?php namespace Clockwork\Authentication;

interface AuthenticatorInterface
{
	const REQUIRES_USERNAME = 'username';
	const REQUIRES_PASSWORD = 'password';

	public function attempt(array $credentials);

	public function check($token);

	public function requires();
}
