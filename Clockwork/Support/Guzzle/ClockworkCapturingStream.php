<?php namespace Clockwork\Support\Guzzle;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

class ClockworkCapturingStream implements StreamInterface
{
	use StreamDecoratorTrait { __construct as private initializeStream; }

	protected $capture;

	public function __construct(StreamInterface $stream, callable $capture)
	{
		$this->initializeStream($stream);
		$this->capture = $capture;
	}

	public function read($length): string
	{
		$chunk = $this->stream->read($length);

		call_user_func($this->capture, $chunk, $this->stream->eof());

		return $chunk;
	}
}
