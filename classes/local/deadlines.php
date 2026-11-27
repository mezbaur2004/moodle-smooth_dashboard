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
 * submitted assignments and finished quizzes drop out, and user and group overrides are already
 * applied to the dates. Assignment extensions are not in the calendar, so they are applied here.
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
     * @param \stdClass[]|null $courses The user's courses, keyed by id: quiz attempts waiting for
     *     manual grading in those the user grades are added (quizzes have no calendar event for it).
     * @return array[] Each with id, cmid, instance, modname, name, courseid, course, type (due,
     *     open, close, gradingdue, tograde or other), time, extended (an assignment extension moved it),
     *     url, actionname, actionurl, actionable and itemcount.
     */
    public static function upcoming(
        array $modules,
        int $days,
        int $overduedays,
        int $limit,
        ?int $now = null,
        ?array $courses = null
    ): array {
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
                if ($row && !self::missed($row, $now)) {
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
        if (isset($modules['assign'])) {
            $rows = self::apply_extensions($rows, $modules, $from, $to);
            $rows = array_values(array_filter($rows, function (array $row) use ($now) {
                return !self::missed($row, $now);
            }));
        }
        if (isset($modules['quiz']) && $courses) {
            $rows = array_merge($rows, self::quiz_grading($courses));
        }
        usort($rows, function (array $a, array $b) {
            return ($a['time'] <=> $b['time']) ?: ($a['id'] <=> $b['id']);
        });
        return array_slice($rows, 0, $limit);
    }

    /**
     * Whether an item is past and can no longer be done: an assignment past its cut-off date, for
     * example. The calendar still lists it, marked as not actionable; it is no longer something to
     * do, and the block would otherwise call it "Not open yet".
     *
     * @param array $row From export().
     * @param int $now
     * @return bool
     */
    protected static function missed(array $row, int $now): bool {
        return $row['time'] < $now && $row['type'] !== 'open' && !$row['actionable'];
    }

    /**
     * Quiz attempts waiting for manual grading (essay questions, for example), one row per quiz,
     * in the courses where the current user grades quizzes. A finished attempt with no total mark
     * yet is one with questions still to grade.
     *
     * @param \stdClass[] $courses Keyed by id.
     * @return array[] Rows like export(), of type tograde, dated by the oldest such attempt.
     */
    public static function quiz_grading(array $courses): array {
        global $DB;
        $courseids = [];
        foreach ($courses as $course) {
            if (has_capability('mod/quiz:grade', \context_course::instance($course->id))) {
                $courseids[] = (int) $course->id;
            }
        }
        if (!$courseids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        $params['finished'] = 'finished';
        $records = $DB->get_records_sql(
            "SELECT q.id, q.course, COUNT(qa.id) AS attempts, MIN(qa.timefinish) AS oldest
               FROM {quiz} q
               JOIN {quiz_attempts} qa ON qa.quiz = q.id
              WHERE q.course $insql AND qa.state = :finished AND qa.sumgrades IS NULL AND qa.preview = 0
           GROUP BY q.id, q.course",
            $params
        );
        $rows = [];
        foreach ($records as $record) {
            $cm = get_fast_modinfo($record->course)->get_instances_of('quiz')[$record->id] ?? null;
            if (!$cm || !$cm->uservisible || $cm->deletioninprogress || !has_capability('mod/quiz:grade', $cm->context)) {
                continue;
            }
            $course = $courses[$record->course];
            $rows[] = [
                // Not a calendar event: a negative id keeps rows apart when sorting.
                'id' => -(int) $cm->id,
                'cmid' => (int) $cm->id,
                'instance' => (int) $cm->instance,
                'modname' => 'quiz',
                'modtype' => get_string('modulename', 'quiz'),
                'name' => $cm->get_formatted_name(),
                'courseid' => (int) $course->id,
                'course' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
                'type' => 'tograde',
                'time' => (int) $record->oldest,
                'extended' => false,
                'url' => $cm->url->out(false),
                'actionname' => get_string('gradeverb'),
                'actionurl' => (new \moodle_url('/mod/quiz/report.php', ['id' => $cm->id, 'mode' => 'grading']))->out(false),
                'actionable' => true,
                'itemcount' => (int) $record->attempts,
            ];
        }
        return $rows;
    }

    /**
     * Move assignments to the current user's extension date.
     *
     * An extension is not a calendar event: the assignment's event keeps its due date, so without
     * this an extended assignment would show as overdue, or not at all once its due date has left
     * the overdue period. Assignments with an extension in the period are rebuilt from their due
     * event (the user's own override event when they have one), and the assignment decides as
     * usual whether it still needs the user.
     *
     * @param array[] $rows From export().
     * @param array $modules Module names as keys.
     * @param int $from
     * @param int $to
     * @return array[]
     */
    protected static function apply_extensions(array $rows, array $modules, int $from, int $to): array {
        global $DB, $USER;
        $extensions = $DB->get_records_select_menu(
            'assign_user_flags',
            'userid = :userid AND extensionduedate >= :from AND extensionduedate <= :to',
            ['userid' => $USER->id, 'from' => $from, 'to' => $to],
            '',
            'assignment, extensionduedate'
        );
        if (!$extensions) {
            return $rows;
        }
        $rows = array_values(array_filter($rows, function (array $row) use ($extensions) {
            return !($row['modname'] === 'assign' && $row['type'] === 'due' && isset($extensions[$row['instance']]));
        }));

        [$insql, $params] = $DB->get_in_or_equal(array_keys($extensions), SQL_PARAMS_NAMED);
        // The assignment's own due event (no priority; its userid is whoever created it), or the
        // user's override event (with a priority).
        $params += ['modulename' => 'assign', 'eventtype' => 'due', 'userid' => $USER->id];
        $records = $DB->get_records_select(
            'event',
            "modulename = :modulename AND eventtype = :eventtype AND instance $insql AND groupid = 0
             AND (priority IS NULL OR userid = :userid)",
            $params,
            'id ASC'
        );
        // One event per assignment: the user's override when there is one.
        $chosen = [];
        foreach ($records as $record) {
            if (!isset($chosen[$record->instance]) || $record->priority !== null) {
                $chosen[$record->instance] = $record;
            }
        }
        \core_calendar\local\event\container::set_requesting_user($USER->id);
        $factory = \core_calendar\local\event\container::get_event_factory();
        foreach ($chosen as $record) {
            $event = $factory->create_instance($record);
            if (!$event instanceof action_event_interface) {
                continue;
            }
            $row = self::export($event, $modules);
            if ($row) {
                $row['time'] = max($row['time'], (int) $extensions[$record->instance]);
                $row['extended'] = true;
                $rows[] = $row;
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
            'instance' => (int) $cm->instance,
            'modname' => $cm->modname,
            'modtype' => get_string('modulename', $cm->modname),
            'name' => $cm->get_formatted_name(),
            'courseid' => (int) $course->id,
            'course' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
            'type' => in_array($type, ['due', 'open', 'close', 'gradingdue']) ? $type : 'other',
            'time' => $event->get_times()->get_sort_time()->getTimestamp(),
            'extended' => false,
            'url' => $cm->url ? $cm->url->out(false) : (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'actionname' => $action->get_name(),
            'actionurl' => $action->get_url()->out(false),
            'actionable' => (bool) $action->is_actionable(),
            'itemcount' => (int) $action->get_item_count(),
        ];
    }
}
