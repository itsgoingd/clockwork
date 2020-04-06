<?php namespace Clockwork\Request;

// Supported request types
class RequestType
{
	const REQUEST = 'request';
	const COMMAND = 'command';
	const QUEUE_JOB = 'queue-job';
	const TEST = 'test';
}
