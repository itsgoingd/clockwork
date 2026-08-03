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

	protected function collectStreamResponseBody($request)
	{
		$content = '';

		return function ($chunk, $complete) use (&$content, $request) {
			if (! $request->response || $request->response->truncated) return;

			$limit = $this->maxResponseDataSize;
			$remaining = $limit === null ? null : $limit - strlen($content);

			if ($limit === null || $remaining >= strlen($chunk)) {
				$content .= $chunk;
			} else {
				$content .= substr($chunk, 0, $remaining);
				$request->response->truncated = true;
			}

			if ($this->collectRawContent) $request->response->body = $content;
			if ($complete && ! $request->response->truncated && $this->collectContent) {
				$request->response->content = json_decode($content, true);
			}
		};
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
