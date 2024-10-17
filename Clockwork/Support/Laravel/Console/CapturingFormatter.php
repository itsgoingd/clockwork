<?php namespace Clockwork\Support\Laravel\Console;

use Symfony\Component\Console\Formatter\{OutputFormatterInterface, OutputFormatterStyleInterface};

// Formatter wrapping around a "real" formatter, capturing the formatted output (Symfony 7.x and later)
class CapturingFormatter implements OutputFormatterInterface
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

	public function setDecorated(bool $decorated): void
	{
		$this->formatter->setDecorated($decorated);
	}

	public function isDecorated(): bool
	{
		return $this->formatter->isDecorated();
	}

	public function setStyle(string $name, OutputFormatterStyleInterface $style): void
	{
		$this->formatter->setStyle($name, $style);
	}

	public function hasStyle(string $name): bool
	{
		return $this->formatter->hasStyle($name);
	}

	public function getStyle(string $name): OutputFormatterStyleInterface
	{
		return $this->formatter->getStyle($name);
	}

	public function format(?string $message): ?string
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
