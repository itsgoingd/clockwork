<?php namespace Clockwork\Support\Twig;

use Clockwork\Request\Timeline\Timeline;

use Twig\Profiler\Profile;

// Converts Twig profiles to a Clockwork rendered views timelines
class ProfilerClockworkDumper
{
	protected $lastId = 1;

	// Dumps a profile into a new rendered views timeline
	public function dump(Profile $profile)
	{
		$timeline = new Timeline;

		$this->dumpProfile($profile, $timeline);

		return $timeline;
	}

	public function dumpProfile(Profile $profile, Timeline $timeline, $parent = null)
	{
		$id = $this->lastId++;

		if ($profile->isRoot()) {
			$name = $profile->getName();
		} elseif ($profile->isTemplate()) {
			$name = basename($profile->getTemplate());
		} else {
			$name = basename($profile->getTemplate()) . '::' . $profile->getType() . '(' . $profile->getName() . ')';
		}

		foreach ($profile as $p) {
			$this->dumpProfile($p, $timeline, $id);
		}

		$data = $profile->__serialize();

		$timeline->event($name, [
			'name'  => $id,
			'start' => isset($data[3]['wt']) ? $data[3]['wt'] : null,
			'end'   => isset($data[4]['wt']) ? $data[4]['wt'] : null,
			'data'  => [
				'data'        => [],
				'memoryUsage' => isset($data[4]['mu']) ? $data[4]['mu'] : null,
				'parent'      => $parent
			]
		]);
	}
}
