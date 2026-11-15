<?php

namespace App\Push;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

/** A time of day ("9:00AM", "21:30") delivered in the time zone of each device. */
final class LocalTime
{
    private function __construct(public readonly int $hour, public readonly int $minute) {}

    /** Accepts the OneSignal format ("9:00AM", "9:00 pm") and 24-hour times ("21:30"). */
    public static function parse(mixed $value): ?self
    {
        if (! is_string($value) || ! preg_match('/^\s*(\d{1,2})(?::(\d{2}))?\s*([ap]\.?m\.?)?\s*$/i', $value, $match)) {
            return null;
        }

        $hour = (int) $match[1];
        $minute = (int) ($match[2] ?? 0);
        $meridiem = isset($match[3]) ? strtolower($match[3][0]) : null;

        if ($meridiem !== null) {
            if ($hour < 1 || $hour > 12) {
                return null;
            }
            $hour = ($hour % 12) + ($meridiem === 'p' ? 12 : 0);
        }

        return $hour <= 23 && $minute <= 59 ? new self($hour, $minute) : null;
    }

    public function format(): string
    {
        return sprintf('%02d:%02d', $this->hour, $this->minute);
    }

    /**
     * Next occurrence of this time in the time zone, from $from: today if it
     * has not passed there yet, else tomorrow. Unknown zones use UTC.
     */
    public function nextIn(?string $timezone, CarbonInterface $from): CarbonImmutable
    {
        try {
            $local = CarbonImmutable::instance($from)->setTimezone($timezone ?: 'UTC');
        } catch (Throwable) {
            $local = CarbonImmutable::instance($from)->setTimezone('UTC');
        }

        $at = $local->setTime($this->hour, $this->minute);

        return ($at->lessThan($local) ? $at->addDay() : $at)->utc();
    }
}
