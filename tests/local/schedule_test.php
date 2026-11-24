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
 * Tests for the upcoming Zoom classes.
 *
 * @package    block_smooth_dashboard
 * @category   test
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\block_smooth_dashboard\local\schedule::class)]
final class schedule_test extends \advanced_testcase {
    /**
     * Skip when mod_zoom is not installed.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!\core_component::get_component_directory('mod_zoom')) {
            $this->markTestSkipped('mod_zoom is not installed.');
        }
        $this->resetAfterTest();
    }

    /**
     * A Zoom activity with its calendar event, as mod_zoom creates them.
     *
     * @param \stdClass $course
     * @param int $start
     * @param int $duration Seconds.
     * @param array $options Course module options.
     * @return \stdClass
     */
    private function create_zoom(\stdClass $course, int $start, int $duration, array $options = []): \stdClass {
        return $this->getDataGenerator()->create_module('zoom', [
            'course' => $course->id,
            'start_time' => $start,
            'duration' => $duration,
        ] + $options);
    }

    /**
     * Classes that have not ended, the next first, with their course and links.
     */
    public function test_upcoming_lists_classes_in_order(): void {
        $now = time();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Physics']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $later = $this->create_zoom($course, $now + 3 * DAYSECS, HOURSECS, ['name' => 'Later']);
        $live = $this->create_zoom($course, $now - 10 * MINSECS, HOURSECS, ['name' => 'Live']);
        $this->create_zoom($course, $now - 2 * HOURSECS, HOURSECS, ['name' => 'Over']);
        $this->create_zoom($course, $now + 40 * DAYSECS, HOURSECS, ['name' => 'Too far']);
        $this->setUser($user);

        $classes = schedule::upcoming([$course->id => $course], 5, 14, $now);

        $this->assertSame(['Live', 'Later'], array_column($classes, 'name'));
        $this->assertSame((int) $live->cmid, $classes[0]['cmid']);
        $this->assertSame('Physics', $classes[0]['course']);
        $this->assertSame($now - 10 * MINSECS, $classes[0]['start']);
        $this->assertSame($now + 50 * MINSECS, $classes[0]['end']);
        $this->assertStringContainsString('/mod/zoom/view.php?id=' . $later->cmid, $classes[1]['url']);
        $this->assertStringContainsString('/mod/zoom/loadmeeting.php?id=' . $later->cmid, $classes[1]['joinurl']);
    }

    /**
     * Hidden activities and courses the user is not in are left out, and the limit applies.
     */
    public function test_upcoming_respects_visibility_and_limit(): void {
        $now = time();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->create_zoom($course, $now + HOURSECS, HOURSECS, ['name' => 'Hidden', 'visible' => 0]);
        $this->create_zoom($other, $now + HOURSECS, HOURSECS, ['name' => 'Other course']);
        foreach ([2, 3, 4] as $hours) {
            $this->create_zoom($course, $now + $hours * HOURSECS, HOURSECS, ['name' => 'Class ' . $hours]);
        }
        $this->setUser($user);

        $classes = schedule::upcoming([$course->id => $course], 2, 14, $now);
        $this->assertSame(['Class 2', 'Class 3'], array_column($classes, 'name'));

        $this->assertSame([], schedule::upcoming([], 5, 14, $now));
    }

    /**
     * A class without a duration is given the default length, so it stays listed while it runs.
     */
    public function test_upcoming_without_duration(): void {
        $now = time();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->create_zoom($course, $now - 30 * MINSECS, 0, ['name' => 'No duration']);
        $this->setUser($user);

        $classes = schedule::upcoming([$course->id => $course], 5, 14, $now);
        $this->assertCount(1, $classes);
        $this->assertSame($now - 30 * MINSECS + schedule::DEFAULT_LENGTH, $classes[0]['end']);
    }
}
