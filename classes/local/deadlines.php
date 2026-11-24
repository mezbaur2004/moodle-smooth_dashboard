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
 * Upcoming and overdue quizzes and assignments.
 *
 * @package    block_smooth_dashboard
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_smooth_dashboard\local;

use core_calendar\local\api as calendar_api;
use core_calendar\local\event\entities\action_event_interface;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/calendar/lib.php');

/**
 * What the current user has to do next, from Moodle's calendar action events: the same API the
 * Timeline block uses. Each activity decides whether an event still needs the user's action, so
 * submitted assignments and finished quizzes drop out, and each student's extensions and user or
 * group overrides are already applied to the dates.
 */
class deadlines {
    /** @var int Events per calendar API call (its maximum). */
    private const PAGE = 50;
    /** @var int Calendar pages read at most, so a calendar full of other events stays cheap. */
    private const MAX_PAGES = 6;

    /**
     * Action events of the chosen activity types, overdue ones first, then by date.
     *
     * @param string[] $modules Module names, for example assign and quiz.
     * @param int $days How far ahead to look.
     * @param int $overduedays How far back to look for overdue items.
     * @param int $limit How many items at most.
     * @param int|null $now The current time (for tests).
     * @return array[] Each with id, cmid, modname, name, courseid, course, type (due, open,
     *     close, gradingdue or other), time, url, actionname, actionurl, actionable and itemcount.
     */
    public static function upcoming(array $modules, int $days, int $overduedays, int $limit, ?int $now = null): array {
        if (!$modules || $limit < 1) {
            return [];
        }
        $now = $now ?? time();
        $from = $now - $overduedays * DAYSECS;
        $to = $now + $days * DAYSECS;
        $modules = array_flip($modules);

        $rows = [];
        $after = null;
        for ($page = 0; $page < self::MAX_PAGES && count($rows) < $limit; $page++) {
            $events = calendar_api::get_action_events_by_timesort($from, $to, $after, self::PAGE, true);
            foreach ($events as $event) {
                $after = $event->get_id();
                $row = self::export($event, $modules);
                if ($row) {
                    $rows[] = $row;
                    if (count($rows) >= $limit) {
                        break;
                    }
                }
            }
            if (count($events) < self::PAGE) {
                break;
            }
        }
        return $rows;
    }

    /**
     * One event as a row, or null when it is not of a chosen activity type.
     *
     * @param action_event_interface $event
     * @param array $modules Module names as keys.
     * @return array|null
     */
    protected static function export(action_event_interface $event, array $modules): ?array {
        $cmproxy = $event->get_course_module();
        if (!$cmproxy || !isset($modules[$cmproxy->get('modname')])) {
            return null;
        }
        /** @var \cm_info $cm */
        $cm = $cmproxy->get_proxied_instance();
        if (!$cm->uservisible) {
            return null;
        }
        $course = $event->get_course()->get_proxied_instance();
        $action = $event->get_action();
        $type = $event->get_type();
        return [
            'id' => (int) $event->get_id(),
            'cmid' => (int) $cm->id,
            'modname' => $cm->modname,
            'modtype' => get_string('modulename', $cm->modname),
            'name' => $cm->get_formatted_name(),
            'courseid' => (int) $course->id,
            'course' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
            'type' => in_array($type, ['due', 'open', 'close', 'gradingdue']) ? $type : 'other',
            'time' => $event->get_times()->get_sort_time()->getTimestamp(),
            'url' => $cm->url ? $cm->url->out(false) : (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'actionname' => $action->get_name(),
            'actionurl' => $action->get_url()->out(false),
            'actionable' => (bool) $action->is_actionable(),
            'itemcount' => (int) $action->get_item_count(),
        ];
    }
}
