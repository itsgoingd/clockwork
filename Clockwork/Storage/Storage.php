<?php namespace Clockwork\Storage;

use Clockwork\Storage\StorageInterface;
use Clockwork\Request\Request;

abstract class Storage implements StorageInterface
{
	// Update existing request
	public function update(Request $request)
	{
	}
}
