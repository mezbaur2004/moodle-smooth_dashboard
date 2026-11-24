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
 * Zoom attendance, from local_zoomattendance.
 *
 * @package    block_smooth_dashboard
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_smooth_dashboard\local;

use local_zoomattendance\local\course_summary;
use local_zoomattendance\local\data_version;
use local_zoomattendance\local\settings;

/**
 * The current user's own Zoom attendance (course overall) per course, exactly as
 * local_zoomattendance's My Zoom attendance page shows it. Without local_zoomattendance nothing is
 * shown.
 *
 * Course summaries are not cheap, so results are cached per user for up to an hour and rebuilt
 * as soon as local_zoomattendance's data changes (a sync, an exclusion, an enrolment).
 */
class attendance {
    /** @var int Seconds an entry is kept. */
    public const CACHE_SECS = HOURSECS;

    /**
     * Whether local_zoomattendance is installed.
     *
     * @return bool
     */
    public static function available(): bool {
        return class_exists(course_summary::class) && class_exists(data_version::class);
    }

    /**
     * The present and partial thresholds of local_zoomattendance (its site defaults, which
     * Course overall uses).
     *
     * @return float[] With present and partial.
     */
    public static function thresholds(): array {
        $settings = settings::site_defaults();
        return ['present' => (float) $settings->presentpct, 'partial' => (float) $settings->latepct];
    }

    /**
     * Course overall per course, for courses with a Zoom activity where the user has a figure.
     *
     * @param \stdClass[] $courses Keyed by id.
     * @param int $userid
     * @return array[] Keyed by course id, each with percentage (float), level (good, fair or low)
     *     and url (the user's attendance page).
     */
    public static function for_courses(array $courses, int $userid): array {
        if (!$courses || !self::available()) {
            return [];
        }
        $percentages = self::percentages($courses, $userid);
        $thresholds = self::thresholds();
        $result = [];
        foreach ($percentages as $courseid => $percentage) {
            $result[$courseid] = [
                'percentage' => $percentage,
                'level' => $percentage >= $thresholds['present'] ? 'good'
                    : ($percentage >= $thresholds['partial'] ? 'fair' : 'low'),
                'url' => (new \moodle_url('/local/zoomattendance/user.php', ['course' => $courseid]))->out(false),
            ];
        }
        return $result;
    }

    /**
     * Course overall per course, from the cache when it is current.
     *
     * @param \stdClass[] $courses
     * @param int $userid
     * @return float[] Keyed by course id.
     */
    protected static function percentages(array $courses, int $userid): array {
        $cache = \cache::make('block_smooth_dashboard', 'attendance');
        $version = data_version::get();
        $ids = array_map('intval', array_keys($courses));
        sort($ids);
        $entry = $cache->get($userid);
        if (
            is_array($entry) && ($entry['version'] ?? '') === $version && ($entry['courses'] ?? []) === $ids
                && $entry['time'] > time() - self::CACHE_SECS
        ) {
            return $entry['data'];
        }
        $data = [];
        foreach ($courses as $course) {
            try {
                $percentage = self::course_percentage($course, $userid);
            } catch (\Throwable $e) {
                debugging(
                    'block_smooth_dashboard: attendance of course ' . $course->id . ': ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
                $percentage = null;
            }
            if ($percentage !== null) {
                $data[(int) $course->id] = $percentage;
            }
        }
        $cache->set($userid, ['time' => time(), 'version' => $version, 'courses' => $ids, 'data' => $data]);
        return $data;
    }

    /**
     * The user's course overall in one course, or null when they have none.
     *
     * @param \stdClass $course
     * @param int $userid
     * @return float|null
     */
    protected static function course_percentage(\stdClass $course, int $userid): ?float {
        if (!get_fast_modinfo($course)->get_instances_of('zoom')) {
            return null;
        }
        if (!has_capability('local/zoomattendance:viewown', \context_course::instance($course->id), $userid)) {
            return null;
        }
        $summary = course_summary::build(get_course($course->id), 0, $userid, 'local/zoomattendance:viewown');
        return isset($summary->overall[$userid]) ? $summary->overall[$userid]->percentage() : null;
    }
}
