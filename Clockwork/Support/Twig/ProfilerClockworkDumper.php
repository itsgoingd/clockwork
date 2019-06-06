<?php namespace Clockwork\Support\Twig;

use Clockwork\Request\Timeline;

use Twig\Profiler\Profile;

class ProfilerClockworkDumper
{
	protected $lastId = 1;

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

		$timeline->addEvent(
			$id,
			$name,
			$profile->__serialize()[3]['wt'],
			$profile->__serialize()[4]['wt'],
			[ 'data' => [], 'parent' => $parent ]
		);
	}
}
