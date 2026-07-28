<?php namespace Clockwork\DataSource\Concerns;

trait HttpCollectResponseBody
{
	protected $maxResponseDataSize;

	protected function collectResponseBody($body)
	{
		$content = null;
		$stream = ! $body->isSeekable();
		$truncated = false;
		$limit = $this->maxResponseDataSize;

		if (! $stream && ($this->collectContent || $this->collectRawContent)) {
			$content = $this->readResponseBody($body, $limit === null ? null : $limit + 1);

			$truncated = $limit !== null && strlen($content) > $limit;
			if ($truncated) $content = substr($content, 0, $limit);
		}

		if (! $stream) $body->rewind();

		return (object) [
			'content'   => ! $stream && ! $truncated && $this->collectContent ? json_decode($content, true) : null,
			'body'      => ! $stream && $this->collectRawContent ? $content : null,
			'stream'    => $stream,
			'truncated' => $truncated
		];
	}

	protected function readResponseBody($body, $limit)
	{
		$body->rewind();

		if ($limit === null) return $body->getContents();

		$content = '';

		while (! $body->eof() && strlen($content) < $limit) {
			$chunk = $body->read(min(8192, $limit - strlen($content)));

			if ($chunk === '') break;

			$content .= $chunk;
		}

		return $content;
	}
}
