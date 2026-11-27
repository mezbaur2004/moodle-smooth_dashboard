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
 * Tests for the course cards.
 *
 * @package    block_smooth_dashboard
 * @category   test
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\block_smooth_dashboard\local\courses::class)]
final class courses_test extends \advanced_testcase {
    /**
     * Only courses in progress, without those removed from view, the most recently visited first.
     */
    public function test_in_progress(): void {
        global $DB;
        $this->resetAfterTest();
        $now = time();
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $old = $generator->create_course(['startdate' => $now - 400 * DAYSECS, 'enddate' => $now - 30 * DAYSECS]);
        $future = $generator->create_course(['startdate' => $now + 30 * DAYSECS]);
        $first = $generator->create_course(['startdate' => $now - DAYSECS, 'enddate' => 0]);
        $second = $generator->create_course(['startdate' => $now - DAYSECS, 'enddate' => 0]);
        $removed = $generator->create_course(['startdate' => $now - DAYSECS, 'enddate' => 0]);
        foreach ([$old, $future, $first, $second, $removed] as $course) {
            $generator->enrol_user($user->id, $course->id, 'student');
        }
        set_user_preference('block_myoverview_hidden_course_' . $removed->id, 1, $user);
        $DB->insert_record('user_lastaccess', ['userid' => $user->id, 'courseid' => $second->id, 'timeaccess' => $now]);
        $this->setUser($user);

        $courses = courses::in_progress(courses::enrolled(), (int) $user->id);
        $this->assertSame([(int) $second->id, (int) $first->id], array_map('intval', array_keys($courses)));
    }

    /**
     * Progress and the activities left, in course order; completed ones are left out.
     */
    public function test_card(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1, 'fullname' => 'Math & Science']);
        $user = $generator->create_and_enrol($course, 'student');
        $pages = [];
        foreach (['One', 'Two', 'Three', 'Four'] as $name) {
            $pages[] = $generator->create_module('page', ['course' => $course->id, 'name' => $name,
                'completion' => COMPLETION_TRACKING_MANUAL]);
        }
        $generator->create_module('page', ['course' => $course->id, 'name' => 'Not tracked']);
        $generator->create_module('page', ['course' => $course->id, 'name' => 'Hidden', 'visible' => 0,
            'completion' => COMPLETION_TRACKING_MANUAL]);
        $completion = new \completion_info($course);
        $completion->update_state(get_fast_modinfo($course)->get_cm($pages[0]->cmid), COMPLETION_COMPLETE, $user->id);
        $this->setUser($user);

        $card = courses::card($course, (int) $user->id, 2);
        $this->assertSame('Math &amp; Science', $card['name']);
        $this->assertSame(25, $card['progress']);
        $this->assertSame(3, $card['todocount']);
        $this->assertSame(['Two', 'Three'], array_column($card['todo'], 'name'));
        $this->assertStringContainsString('/mod/page/view.php?id=' . $pages[1]->cmid, $card['todo'][0]['url']);
        $this->assertNotEmpty($card['image']);
    }

    /**
     * Without completion tracking there is no progress and nothing listed.
     */
    public function test_card_without_completion(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 0]);
        $user = $generator->create_and_enrol($course, 'student');
        $generator->create_module('page', ['course' => $course->id]);
        $this->setUser($user);

        $card = courses::card($course, (int) $user->id, 3);
        $this->assertNull($card['progress']);
        $this->assertSame([], $card['todo']);
        $this->assertSame(0, $card['todocount']);
    }

    /**
     * A closed quiz and an assignment past its cut-off date are not "to do" any more: they are
     * counted as closed. An extension or a later close date for the user keeps them to do.
     */
    public function test_card_closed_activities(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->resetAfterTest();
        $now = time();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $user = $generator->create_and_enrol($course, 'student');
        $tracked = ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL];
        /** @var \mod_quiz_generator $quizgenerator */
        $quizgenerator = $generator->get_plugin_generator('mod_quiz');
        $quizgenerator->create_instance($tracked + ['name' => 'Closed quiz', 'timeclose' => $now - DAYSECS]);
        $quizgenerator->create_instance($tracked + ['name' => 'Open quiz', 'timeclose' => $now + DAYSECS]);
        $overridden = $quizgenerator->create_instance($tracked + ['name' => 'Quiz open for me', 'timeclose' => $now - DAYSECS]);
        $quizgenerator->create_override(['quiz' => $overridden->id, 'userid' => $user->id, 'timeclose' => $now + DAYSECS]);
        $assignment = ['duedate' => $now - 2 * DAYSECS, 'cutoffdate' => $now - DAYSECS, 'allowsubmissionsfromdate' => 0,
            'assignsubmission_onlinetext_enabled' => 1];
        $generator->create_module('assign', $tracked + $assignment + ['name' => 'Past cut-off']);
        $extended = $generator->create_module('assign', $tracked + $assignment + ['name' => 'Extended']);
        $this->setAdminUser();
        $assign = new \assign(\context_module::instance($extended->cmid), false, false);
        $assign->save_user_extension($user->id, $now + DAYSECS);
        $this->setUser($user);

        $card = courses::card($course, (int) $user->id, 5, $now);
        $this->assertSame(['Open quiz', 'Quiz open for me', 'Extended'], array_column($card['todo'], 'name'));
        $this->assertSame(3, $card['todocount']);
        $this->assertSame(2, $card['closedcount']);
    }
}
