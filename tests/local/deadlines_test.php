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

    /**
     * An extension moves an assignment to its new date, also when the original due date is older
     * than the overdue period; other students keep the original date.
     */
    public function test_extensions(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->resetAfterTest();
        $now = time();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_and_enrol($course, 'student');
        $other = $generator->create_and_enrol($course, 'student');
        $common = ['course' => $course->id, 'assignsubmission_onlinetext_enabled' => 1, 'cutoffdate' => 0,
            'allowsubmissionsfromdate' => 0];
        $recent = $generator->create_module('assign', $common + ['name' => 'Recent', 'duedate' => $now - DAYSECS]);
        $old = $generator->create_module('assign', $common + ['name' => 'Old', 'duedate' => $now - 30 * DAYSECS]);
        $this->setAdminUser();
        foreach ([$recent, $old] as $i => $module) {
            $assign = new \assign(\context_module::instance($module->cmid), false, false);
            $assign->save_user_extension($user->id, $now + ($i + 1) * DAYSECS);
        }

        $this->setUser($user);
        $items = deadlines::upcoming(['assign'], 14, 7, 10, $now);
        $this->assertSame(['Recent', 'Old'], array_column($items, 'name'));
        $this->assertSame([$now + DAYSECS, $now + 2 * DAYSECS], array_column($items, 'time'));
        $this->assertSame([true, true], array_column($items, 'extended'));

        $this->setUser($other);
        $items = deadlines::upcoming(['assign'], 14, 7, 10, $now);
        $this->assertSame(['Recent'], array_column($items, 'name'));
        $this->assertSame($now - DAYSECS, $items[0]['time']);
        $this->assertFalse($items[0]['extended']);
    }

    /**
     * An overdue assignment past its cut-off date can no longer be submitted, so it drops out
     * (the calendar marks it not actionable, which would read "Not open yet"); one before its
     * cut-off date stays.
     */
    public function test_missed_assignment_drops_out(): void {
        $this->resetAfterTest();
        $now = time();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_and_enrol($course, 'student');
        $common = ['course' => $course->id, 'assignsubmission_onlinetext_enabled' => 1, 'allowsubmissionsfromdate' => 0,
            'duedate' => $now - 3 * DAYSECS];
        $generator->create_module('assign', $common + ['name' => 'Closed', 'cutoffdate' => $now - DAYSECS]);
        $generator->create_module('assign', $common + ['name' => 'Still open', 'cutoffdate' => $now + DAYSECS]);
        $this->setUser($user);

        $items = deadlines::upcoming(['assign'], 14, 7, 10, $now);
        $this->assertSame(['Still open'], array_column($items, 'name'));
        $this->assertTrue($items[0]['actionable']);
    }

    /**
     * Teachers see quizzes with attempts waiting for manual grading (Due soon has no calendar event
     * for it); students do not, and the rows only come with quizzes chosen.
     */
    public function test_quiz_grading(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'English']);
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        /** @var \mod_quiz_generator $quizgenerator */
        $quizgenerator = $generator->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(['course' => $course->id, 'name' => 'Essay quiz', 'questionsperpage' => 0,
            'grade' => 10, 'sumgrades' => 1]);
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category(['contextid' => \context_module::instance($quiz->cmid)->id]);
        $essay = $questiongenerator->create_question('essay', null, ['category' => $category->id]);
        quiz_add_quiz_question($essay->id, $quiz);

        // The student writes the essay and submits: the attempt waits for the teacher.
        $this->setUser($student);
        $attempt = $quizgenerator->create_attempt($quiz->id, $student->id);
        $finished = time() - HOURSECS;
        $quizgenerator->submit_responses($attempt->id, [1 => 'My essay'], false, true, $finished);
        $courses = [$course->id => $course];
        $this->assertSame([], deadlines::quiz_grading($courses));

        $this->setUser($teacher);
        $rows = deadlines::quiz_grading($courses);
        $this->assertCount(1, $rows);
        $this->assertSame('Essay quiz', $rows[0]['name']);
        $this->assertSame('English', $rows[0]['course']);
        $this->assertSame('tograde', $rows[0]['type']);
        $this->assertSame(1, $rows[0]['itemcount']);
        $this->assertSame($finished, $rows[0]['time']);
        $this->assertTrue($rows[0]['actionable']);
        $this->assertStringContainsString('/mod/quiz/report.php?id=' . $quiz->cmid . '&mode=grading', $rows[0]['actionurl']);

        $this->assertSame(
            ['Essay quiz'],
            array_column(deadlines::upcoming(['assign', 'quiz'], 14, 7, 10, null, $courses), 'name')
        );
        $this->assertSame([], deadlines::upcoming(['assign'], 14, 7, 10, null, $courses));
        $this->assertSame([], deadlines::upcoming(['assign', 'quiz'], 14, 7, 10));
    }
}
