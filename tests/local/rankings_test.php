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

namespace block_smooth_dashboard\local;

/**
 * Tests for the leaderboard places.
 *
 * @package    block_smooth_dashboard
 * @category   test
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\block_smooth_dashboard\local\rankings::class)]
final class rankings_test extends \advanced_testcase {
    /**
     * Skip when mod_leaderboard is not installed.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!rankings::available()) {
            $this->markTestSkipped('mod_leaderboard is not installed.');
        }
        $this->resetAfterTest();
    }

    /**
     * A course with a leaderboard, two students and an assignment.
     *
     * @return array [course, student, other student, assignment]
     */
    private function setup_course(): array {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Physics']);
        if (!get_fast_modinfo($course)->get_instances_of('leaderboard')) {
            $generator->create_module('leaderboard', ['course' => $course->id]);
        }
        $student = $generator->create_and_enrol($course, 'student', ['firstname' => 'Rafi', 'lastname' => 'Ahmed']);
        $other = $generator->create_and_enrol($course, 'student', ['firstname' => 'Mim', 'lastname' => 'Akter']);
        $assign = $generator->create_module('assign', ['course' => $course->id, 'grade' => 10]);
        return [$course, $student, $other, $assign];
    }

    /**
     * The top students and the user's own place, once there are points.
     */
    public function test_for_courses(): void {
        [$course, $student, $other, $assign] = $this->setup_course();
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign->id, 0, [
            $student->id => (object) ['userid' => $student->id, 'rawgrade' => 6],
            $other->id => (object) ['userid' => $other->id, 'rawgrade' => 9],
        ]);
        \mod_leaderboard\api::course_changed((int) $course->id);
        $this->setUser($student);

        $boards = rankings::for_courses([$course->id => $course]);
        $this->assertCount(1, $boards);
        $board = $boards[$course->id];
        $this->assertSame('Physics', $board['course']);
        $this->assertSame(['Mim Akter', 'Rafi Ahmed'], array_column($board['rows'], 'name'));
        $this->assertSame([false, true], array_column($board['rows'], 'isme'));
        $this->assertSame(2, $board['me']['rank']);
        $this->assertSame(2, $board['me']['total']);
    }

    /**
     * A leaderboard where nobody has points yet says nothing, so it is left out.
     */
    public function test_no_points_yet(): void {
        [$course, $student] = $this->setup_course();
        $this->setUser($student);
        $this->assertSame([], rankings::for_courses([$course->id => $course]));
    }
}
