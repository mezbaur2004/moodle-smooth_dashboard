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
 * Upcoming Zoom classes.
 *
 * @package    block_smooth_dashboard
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_smooth_dashboard\local;

/**
 * The current user's upcoming and live Zoom classes, across their courses.
 *
 * mod_zoom writes one calendar event per scheduled class: one for a single meeting, one per
 * occurrence of a recurring meeting with a fixed time. Recurring meetings without a fixed time
 * have hidden events and no schedule, so they are not upcoming classes: rooms() lists them, as
 * classes to join at their usual time. Only classes of Zoom activities the user can open are
 * listed, so access restrictions and hidden activities are respected.
 */
class schedule {
    /** @var int Length assumed for a class whose event has no duration. */
    public const DEFAULT_LENGTH = HOURSECS;

    /**
     * Classes that have not ended yet, the next one first.
     *
     * @param \stdClass[] $courses The user's courses, keyed by id.
     * @param int $limit How many classes at most.
     * @param int $days How far ahead to look.
     * @param int|null $now The current time (for tests).
     * @return array[] Each with cmid, name, courseid, course, start, end, url and joinurl.
     */
    public static function upcoming(array $courses, int $limit, int $days, ?int $now = null): array {
        global $DB;
        if (!$courses || $limit < 1 || !\core_component::get_component_directory('mod_zoom')) {
            return [];
        }
        $now = $now ?? time();
        [$insql, $params] = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED, 'c');
        $params += [
            'modulename' => 'zoom',
            'now' => $now,
            'defaultlength' => self::DEFAULT_LENGTH,
            'until' => $now + $days * DAYSECS,
        ];
        // A class is still upcoming until it ends; one without a duration is given an hour.
        $select = "modulename = :modulename AND visible = 1 AND courseid $insql
                   AND timestart < :until
                   AND timestart + (CASE WHEN timeduration > 0 THEN timeduration ELSE :defaultlength END) > :now";
        // Fetch more than needed: some may belong to activities the user cannot open.
        $events = $DB->get_records_select(
            'event',
            $select,
            $params,
            'timestart ASC, id ASC',
            'id, courseid, instance, timestart, timeduration',
            0,
            $limit * 5 + 20
        );

        $rows = [];
        $seen = [];
        foreach ($events as $event) {
            $cm = self::get_cm((int) $event->courseid, (int) $event->instance);
            if (!$cm) {
                continue;
            }
            $length = $event->timeduration > 0 ? (int) $event->timeduration : self::DEFAULT_LENGTH;
            $key = $cm->id . '-' . $event->timestart;
            if (isset($seen[$key])) {
                // The same class twice (an event rewritten by mod_zoom): keep one.
                continue;
            }
            $seen[$key] = true;
            $course = $courses[$event->courseid];
            $rows[] = [
                'cmid' => (int) $cm->id,
                'name' => $cm->get_formatted_name(),
                'courseid' => (int) $course->id,
                'course' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
                'start' => (int) $event->timestart,
                'end' => (int) $event->timestart + $length,
                'url' => (new \moodle_url('/mod/zoom/view.php', ['id' => $cm->id]))->out(false),
                'joinurl' => (new \moodle_url('/mod/zoom/loadmeeting.php', ['id' => $cm->id]))->out(false),
            ];
            if (count($rows) >= $limit) {
                break;
            }
        }
        return $rows;
    }

    /**
     * Recurring Zoom meetings without a fixed time (mod_zoom's "No fixed time" recurrence), in
     * course order: they have no schedule in Moodle, but students join them at their usual class
     * time.
     *
     * @param \stdClass[] $courses The user's courses, keyed by id, in the order to list them.
     * @param int $limit How many at most.
     * @return array[] Each with cmid, name, courseid, course, url and joinurl.
     */
    public static function rooms(array $courses, int $limit): array {
        global $DB;
        if (!$courses || $limit < 1 || !\core_component::get_component_directory('mod_zoom')) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED, 'c');
        // Recurrence type 0 is ZOOM_RECURRINGTYPE_NOTIME.
        $ids = $DB->get_records_select_menu(
            'zoom',
            "course $insql AND recurring = 1 AND recurrence_type = 0",
            $params,
            '',
            'id, course'
        );
        if (!$ids) {
            return [];
        }
        $rows = [];
        foreach ($courses as $course) {
            if (!in_array($course->id, $ids)) {
                continue;
            }
            foreach (get_fast_modinfo($course->id)->get_instances_of('zoom') as $instance => $cm) {
                if (!isset($ids[$instance]) || !self::get_cm((int) $course->id, (int) $instance)) {
                    continue;
                }
                $rows[] = [
                    'cmid' => (int) $cm->id,
                    'name' => $cm->get_formatted_name(),
                    'courseid' => (int) $course->id,
                    'course' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
                    'url' => (new \moodle_url('/mod/zoom/view.php', ['id' => $cm->id]))->out(false),
                    'joinurl' => (new \moodle_url('/mod/zoom/loadmeeting.php', ['id' => $cm->id]))->out(false),
                ];
                if (count($rows) >= $limit) {
                    return $rows;
                }
            }
        }
        return $rows;
    }

    /**
     * The Zoom activity of an event, if the current user can open it.
     *
     * @param int $courseid
     * @param int $instance The zoom id.
     * @return \cm_info|null
     */
    protected static function get_cm(int $courseid, int $instance): ?\cm_info {
        $instances = get_fast_modinfo($courseid)->get_instances_of('zoom');
        $cm = $instances[$instance] ?? null;
        if (!$cm || !$cm->uservisible || $cm->deletioninprogress || !has_capability('mod/zoom:view', $cm->context)) {
            return null;
        }
        return $cm;
    }
}
