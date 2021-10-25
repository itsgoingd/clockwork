<?php namespace Clockwork\Helpers\Concerns;

use Clockwork\Helpers\StackFrame;

// Replaces the first stack frame rendering a Laravel view with a duplicate with a resolved original view path (instead
// of the compiled view path)
trait ResolvesViewName
{
	public function resolveViewName()
	{
		$viewFrame = $this->first(function ($frame) {
			return $frame->shortPath ? preg_match('#^/storage/framework/views/[a-z0-9]+\.php$#', $frame->shortPath) : false;
		});

		if (! $viewFrame) return $this;

		$renderFrame = $this->first(function ($frame) {
			return $frame->call == 'Illuminate\View\View->getContents()'
				&& $frame->object instanceof \Illuminate\View\View;
		});

		if (! $renderFrame) return $this;

		$resolvedViewFrame = new StackFrame(
			[ 'file' => $renderFrame->object->getPath(), 'line' => $viewFrame->line ],
			$this->basePath,
			$this->vendorPath
		);

		return $this->copy(array_merge(
			array_slice($this->frames, 0, array_search($viewFrame, $this->frames)),
			[ $resolvedViewFrame ],
			array_slice($this->frames, array_search($viewFrame, $this->frames) + 2)
		));
	}
}
