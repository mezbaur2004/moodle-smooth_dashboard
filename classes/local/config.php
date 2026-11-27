<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Site settings, with their defaults.
 *
 * @package    block_smooth_dashboard
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_smooth_dashboard\local;

/**
 * Reads the block's site settings. Every value has a default, so the block works before the
 * settings page has been saved.
 */
class config {
    /** @var array Numeric settings: name => [default, minimum, maximum]. */
    private const NUMBERS = [
        'classescount' => [5, 1, 20],
        'classesdays' => [14, 1, 90],
        'deadlinesdays' => [14, 1, 90],
        'overduedays' => [30, 0, 60],
        'deadlinescount' => [6, 1, 30],
        'coursescount' => [6, 1, 30],
        'todocount' => [3, 0, 10],
    ];

    /**
     * Whether a section is switched on.
     *
     * @param string $section greeting, nextclass, classes, deadlines, courses, leaderboard or attendance.
     * @return bool
     */
    public static function show(string $section): bool {
        $value = get_config('block_smooth_dashboard', 'show' . $section);
        return $value === false || (bool) $value;
    }

    /**
     * A numeric setting, kept within sensible bounds.
     *
     * @param string $name See NUMBERS.
     * @return int
     */
    public static function number(string $name): int {
        [$default, $min, $max] = self::NUMBERS[$name];
        $value = get_config('block_smooth_dashboard', $name);
        if ($value === false || $value === '' || !is_numeric($value)) {
            return $default;
        }
        return max($min, min($max, (int) $value));
    }

    /**
     * The accent colours as CSS custom properties, for the dashboard's style attribute. Only
     * plain colour values are used, so the setting cannot inject other CSS.
     *
     * @return string
     */
    public static function accent_style(): string {
        $style = '';
        foreach (['accent' => '--sd-accent-site', 'accent2' => '--sd-accent-2-site'] as $name => $property) {
            $value = trim((string) get_config('block_smooth_dashboard', $name));
            if (preg_match('/^(#[0-9a-f]{3,8}|[a-z]{3,20}|(rgb|hsl)a?\([0-9., %]+\))$/i', $value)) {
                $style .= "$property: $value; ";
            }
        }
        return trim($style);
    }

    /**
     * Activity types listed under Due soon.
     *
     * @return string[] Module names.
     */
    public static function deadline_modules(): array {
        $value = get_config('block_smooth_dashboard', 'deadlinemodules');
        if ($value === false) {
            return ['assign', 'quiz'];
        }
        return array_values(array_filter(explode(',', (string) $value)));
    }
}
