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
 * The user's courses, their progress and what is left to do in them.
 *
 * @package    block_smooth_dashboard
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_smooth_dashboard\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Courses for the dashboard, as Moodle's own My courses page classifies them.
 */
class courses {
    /**
     * The current user's enrolled courses, keyed by id: those they can access, in the order of
     * the course list.
     *
     * @return \stdClass[]
     */
    public static function enrolled(): array {
        return enrol_get_my_courses(['enddate', 'enablecompletion'], 'visible DESC, sortorder ASC');
    }

    /**
     * Courses in progress (not past, not future, as on My courses), without those the user has
     * removed from view there, the most recently visited first.
     *
     * @param \stdClass[] $courses From enrolled().
     * @param int $userid
     * @return \stdClass[] Keyed by id.
     */
    public static function in_progress(array $courses, int $userid): array {
        global $DB;
        $lastaccess = $DB->get_records_menu('user_lastaccess', ['userid' => $userid], '', 'courseid, timeaccess');
        $hidden = [];
        foreach (get_user_preferences(null, null, $userid) as $name => $value) {
            if (str_starts_with($name, 'block_myoverview_hidden_course_') && $value) {
                $hidden[(int) substr($name, strlen('block_myoverview_hidden_course_'))] = true;
            }
        }
        $user = \core_user::get_user($userid);
        $result = [];
        foreach ($courses as $course) {
            if (isset($hidden[$course->id]) || course_classify_for_timeline($course, $user) !== COURSE_TIMELINE_INPROGRESS) {
                continue;
            }
            $result[$course->id] = $course;
        }
        uasort($result, function ($a, $b) use ($lastaccess) {
            return ($lastaccess[$b->id] ?? 0) <=> ($lastaccess[$a->id] ?? 0);
        });
        return $result;
    }

    /**
     * A course's card: progress and the first activities left to complete, in course order.
     *
     * Activities that can no longer be done (a closed quiz, an assignment past its cut-off date)
     * are not "to do": they are counted in closedcount instead.
     *
     * @param \stdClass $course
     * @param int $userid
     * @param int $todolimit How many activities left to list.
     * @param int|null $now The current time (for tests).
     * @return array With id, name, url, image, progress (int or null when completion is not
     *     tracked for the user), todo (each with cmid, name, url, modname, iconurl), todocount and
     *     closedcount.
     */
    public static function card(\stdClass $course, int $userid, int $todolimit, ?int $now = null): array {
        $now = $now ?? time();
        $context = \context_course::instance($course->id);
        $card = [
            'id' => (int) $course->id,
            'name' => format_string($course->fullname, true, ['context' => $context]),
            'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'image' => self::image($course),
            'progress' => null,
            'todo' => [],
            'todocount' => 0,
            'closedcount' => 0,
        ];

        $completion = new \completion_info($course);
        if (!$completion->is_enabled() || !$completion->is_tracked_user($userid)) {
            return $card;
        }
        $progress = \core_completion\progress::get_course_progress_percentage($course, $userid);
        $card['progress'] = $progress === null ? null : (int) floor($progress);

        $modinfo = get_fast_modinfo($course, $userid);
        foreach ($modinfo->get_cms() as $cm) {
            if (
                !$cm->uservisible || $cm->deletioninprogress || !$cm->is_visible_on_course_page()
                    || $cm->completion == COMPLETION_TRACKING_NONE
            ) {
                continue;
            }
            $state = (int) $completion->get_data($cm, true, $userid)->completionstate;
            if ($state === COMPLETION_COMPLETE || $state === COMPLETION_COMPLETE_PASS) {
                continue;
            }
            if (self::is_closed($cm, $userid, $now)) {
                $card['closedcount']++;
                continue;
            }
            $card['todocount']++;
            if (count($card['todo']) < $todolimit) {
                $card['todo'][] = [
                    'cmid' => (int) $cm->id,
                    'name' => $cm->get_formatted_name(),
                    'url' => $cm->url ? $cm->url->out(false)
                        : (new \moodle_url('/course/view.php', ['id' => $course->id], 'module-' . $cm->id))->out(false),
                    'modname' => $cm->modname,
                    'modtype' => get_string('modulename', $cm->modname),
                    'iconurl' => $cm->get_icon_url()->out(false),
                    'failed' => $state === COMPLETION_COMPLETE_FAIL,
                ];
            }
        }
        return $card;
    }

    /**
     * Whether the user can no longer do an activity: a quiz past its close date, or an assignment
     * past its cut-off date, with the user's overrides and extension applied.
     *
     * @param \cm_info $cm
     * @param int $userid
     * @param int $now
     * @return bool
     */
    public static function is_closed(\cm_info $cm, int $userid, int $now): bool {
        global $CFG;
        if ($cm->modname === 'quiz') {
            $quiz = \mod_quiz\quiz_settings::create((int) $cm->instance, $userid)->get_quiz();
            return !empty($quiz->timeclose) && $quiz->timeclose <= $now;
        }
        if ($cm->modname === 'assign') {
            require_once($CFG->dirroot . '/mod/assign/locallib.php');
            $assign = new \assign($cm->context, $cm, $cm->get_course());
            $cutoff = (int) $assign->get_instance($userid)->cutoffdate;
            if (!$cutoff || $cutoff > $now) {
                return false;
            }
            $flags = $assign->get_user_flags($userid, false);
            return !$flags || (int) $flags->extensionduedate <= $now;
        }
        return false;
    }

    /**
     * The course image, or Moodle's generated pattern for courses without one.
     *
     * @param \stdClass $course
     * @return string URL.
     */
    public static function image(\stdClass $course): string {
        global $OUTPUT;
        $image = \core_course\external\course_summary_exporter::get_course_image($course);
        return $image ?: $OUTPUT->get_generated_image_for_id($course->id);
    }
}
