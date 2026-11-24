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
 * Tests for Due soon.
 *
 * @package    block_smooth_dashboard
 * @category   test
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\block_smooth_dashboard\local\deadlines::class)]
final class deadlines_test extends \advanced_testcase {
    /**
     * Assignments due in the period are listed in date order, overdue ones included, and other
     * activity types only when chosen.
     */
    public function test_upcoming(): void {
        $this->resetAfterTest();
        $now = time();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'English']);
        $user = $generator->create_and_enrol($course, 'student');
        $common = ['course' => $course->id, 'assignsubmission_onlinetext_enabled' => 1, 'cutoffdate' => 0,
            'allowsubmissionsfromdate' => 0];
        $generator->create_module('assign', $common + ['name' => 'Later', 'duedate' => $now + 3 * DAYSECS]);
        $generator->create_module('assign', $common + ['name' => 'Overdue', 'duedate' => $now - DAYSECS]);
        $generator->create_module('assign', $common + ['name' => 'Long overdue', 'duedate' => $now - 20 * DAYSECS]);
        $generator->create_module('assign', $common + ['name' => 'Too far', 'duedate' => $now + 30 * DAYSECS]);
        $generator->create_module('choice', ['course' => $course->id, 'name' => 'A choice', 'timeopen' => $now - DAYSECS,
            'timeclose' => $now + DAYSECS]);
        $this->setUser($user);

        $items = deadlines::upcoming(['assign'], 14, 7, 10, $now);
        $this->assertSame(['Overdue', 'Later'], array_column($items, 'name'));
        $this->assertSame('due', $items[0]['type']);
        $this->assertSame('assign', $items[0]['modname']);
        $this->assertSame('English', $items[0]['course']);
        $this->assertTrue($items[0]['actionable']);
        $this->assertSame($now - DAYSECS, $items[0]['time']);

        $this->assertCount(1, deadlines::upcoming(['assign'], 14, 7, 1, $now));
        $this->assertSame(['Later'], array_column(deadlines::upcoming(['assign'], 14, 0, 10, $now), 'name'));
        $this->assertContains('A choice', array_column(deadlines::upcoming(['assign', 'choice'], 14, 7, 10, $now), 'name'));
        $this->assertSame([], deadlines::upcoming([], 14, 7, 10, $now));
    }

    /**
     * A submitted assignment no longer needs the student, so it drops out.
     */
    public function test_submitted_assignment_drops_out(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->resetAfterTest();
        $now = time();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_and_enrol($course, 'student');
        $assign = $generator->create_module('assign', ['course' => $course->id, 'duedate' => $now + DAYSECS,
            'assignsubmission_onlinetext_enabled' => 1, 'submissiondrafts' => 0]);
        $this->setUser($user);
        $this->assertCount(1, deadlines::upcoming(['assign'], 14, 7, 10, $now));

        $generator->get_plugin_generator('mod_assign')->create_submission([
            'userid' => $user->id,
            'cmid' => $assign->cmid,
            'onlinetext' => 'Done',
        ]);
        $this->assertSame([], deadlines::upcoming(['assign'], 14, 7, 10, $now));
    }
}
