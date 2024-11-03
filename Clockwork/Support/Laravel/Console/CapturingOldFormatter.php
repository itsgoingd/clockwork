<?php namespace Clockwork\Support\Laravel\Console;

use Symfony\Component\Console\Formatter\{OutputFormatterInterface, OutputFormatterStyleInterface};

// Formatter wrapping around a "real" formatter, capturing the formatted output (Symfony 5.x and earlier)
class CapturingOldFormatter implements OutputFormatterInterface
{
	protected $formatter;

	protected $capturedOutput;

	public function __construct(OutputFormatterInterface $formatter)
	{
		$this->formatter = $formatter;
	}

	public function capturedOutput()
	{
		$capturedOutput = $this->capturedOutput;

		$this->capturedOutput = null;

		return $capturedOutput;
	}

	public function setDecorated($decorated)
	{
		return $this->formatter->setDecorated($decorated);
	}

	public function isDecorated()
	{
		return $this->formatter->isDecorated();
	}

	public function setStyle($name, OutputFormatterStyleInterface $style)
	{
		return $this->formatter->setStyle($name, $style);
	}

	public function hasStyle($name)
	{
		return $this->formatter->hasStyle($name);
	}

	public function getStyle($name)
	{
		return $this->formatter->getStyle($name);
	}

	public function format($message)
	{
		$formatted = $this->formatter->format($message);

		$this->capturedOutput .= $formatted;

		return $formatted;
	}

	public function __call($method, $args)
	{
		return $this->formatter->$method(...$args);
	}

	public function __clone()
	{
		$this->formatter = clone $this->formatter;
	}
}
