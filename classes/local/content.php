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
 * Everything the dashboard shows the current user.
 *
 * @package    block_smooth_dashboard
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_smooth_dashboard\local;

/**
 * Collects the current user's dashboard from each source, for the sections switched on in the
 * site settings. Raw values only (timestamps, numbers); output\dashboard formats them.
 */
class content {
    /**
     * Build the current user's dashboard.
     *
     * @param int|null $now The current time (for tests).
     * @return array With now, classes, deadlines, courses (cards), rankings and the show flags
     *     of each section. A section that is switched off is null.
     */
    public static function build(?int $now = null): array {
        global $USER;
        $now = $now ?? time();
        $userid = (int) $USER->id;
        $enrolled = courses::enrolled();
        $inprogress = courses::in_progress($enrolled, $userid);

        $data = [
            'now' => $now,
            'greeting' => config::show('greeting'),
            'classes' => null,
            'shownextclass' => config::show('nextclass'),
            'showclasses' => config::show('classes'),
            'deadlines' => null,
            'courses' => null,
            'rankings' => null,
            'attendance' => [],
        ];

        if ($data['shownextclass'] || $data['showclasses']) {
            // Classes of every course the user can access, not only those in progress: a course
            // can hold classes before its start date.
            $data['classes'] = schedule::upcoming(
                $enrolled,
                config::number('classescount') + 1,
                config::number('classesdays'),
                $now
            );
        }

        if (config::show('deadlines')) {
            $data['deadlines'] = deadlines::upcoming(
                config::deadline_modules(),
                config::number('deadlinesdays'),
                config::number('overduedays'),
                config::number('deadlinescount'),
                $now
            );
        }

        $shown = array_slice($inprogress, 0, config::number('coursescount'), true);
        if (config::show('courses')) {
            $data['courses'] = [];
            foreach ($shown as $course) {
                $data['courses'][] = courses::card($course, $userid, config::number('todocount'));
            }
            $data['morecourses'] = count($inprogress) > count($shown);
        }
        if (config::show('leaderboard') && rankings::available()) {
            $data['rankings'] = rankings::for_courses($inprogress);
        }
        if (config::show('attendance') && config::show('courses') && attendance::available()) {
            $data['attendance'] = attendance::for_courses($shown, $userid);
        }
        return $data;
    }

    /**
     * Whether there is nothing at all to show.
     *
     * @param array $data From build().
     * @return bool
     */
    public static function is_empty(array $data): bool {
        return !$data['greeting'] && empty($data['classes']) && empty($data['deadlines']) && empty($data['courses'])
            && empty($data['rankings']);
    }
}
