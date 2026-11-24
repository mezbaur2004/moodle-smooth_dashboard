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
 * Leaderboard places, from mod_leaderboard.
 *
 * @package    block_smooth_dashboard
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_smooth_dashboard\local;

/**
 * The top students and the current user's own place, per course with a leaderboard.
 *
 * Only reads mod_leaderboard, through its public API and its own output class, which applies
 * its access checks and its names and anonymous rules. Without mod_leaderboard nothing is shown.
 */
class rankings {
    /**
     * Whether mod_leaderboard is installed.
     *
     * @return bool
     */
    public static function available(): bool {
        return class_exists(\mod_leaderboard\api::class) && class_exists(\mod_leaderboard\output\board::class);
    }

    /**
     * Per course with a leaderboard the user can see and a ranking to show.
     *
     * @param \stdClass[] $courses Keyed by id.
     * @param int $top How many top students.
     * @return array[] Keyed by course id, each with courseid, course, url, name, rows (rank, name,
     *     points, isme, medal) and me (rank, total, points, text) or null when the user is not ranked.
     */
    public static function for_courses(array $courses, int $top = 3): array {
        if (!self::available()) {
            return [];
        }
        $result = [];
        foreach ($courses as $course) {
            try {
                $cm = \mod_leaderboard\api::find_course_leaderboard((int) $course->id);
                if (!$cm) {
                    continue;
                }
                $summary = (new \mod_leaderboard\output\board($cm))->export_summary($top);
                if (empty($summary['showboard'])) {
                    continue;
                }
                $rows = [];
                foreach ($summary['rows'] as $row) {
                    $rows[] = [
                        'rank' => (int) $row['rank'],
                        'name' => $row['name'],
                        'points' => $row['points'],
                        'isme' => !empty($row['isme']),
                        'medal' => $row['rank'] <= 3 ? (int) $row['rank'] : 0,
                    ];
                }
                $me = $summary['me'] ?? null;
                $result[$course->id] = [
                    'courseid' => (int) $course->id,
                    'course' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
                    'url' => $summary['url'],
                    'name' => $summary['name'],
                    'rows' => $rows,
                    'me' => $me ? [
                        'rank' => (int) $me['rank'],
                        'total' => (int) $me['total'],
                        'points' => $me['points'],
                        'text' => $me['text'],
                    ] : null,
                ];
            } catch (\Throwable $e) {
                // A leaderboard problem must never break the dashboard.
                debugging(
                    'block_smooth_dashboard: leaderboard of course ' . $course->id . ': ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }
        return $result;
    }
}
