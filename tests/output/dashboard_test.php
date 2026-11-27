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

namespace block_smooth_dashboard\output;

use block_smooth_dashboard\local\content;

/**
 * Tests for the dashboard's formatting, the whole content and the app output.
 *
 * @package    block_smooth_dashboard
 * @category   test
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\block_smooth_dashboard\output\dashboard::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\block_smooth_dashboard\output\mobile::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\block_smooth_dashboard\local\content::class)]
final class dashboard_test extends \advanced_testcase {
    /**
     * A dashboard with no sources, at a fixed time.
     *
     * @param int $now
     * @param array $data Content overrides.
     * @return dashboard
     */
    private function dashboard(int $now, array $data = []): dashboard {
        return new dashboard($data + [
            'now' => $now, 'greeting' => true, 'classes' => [], 'shownextclass' => true, 'showclasses' => true,
            'deadlines' => [], 'courses' => [], 'rankings' => [], 'attendance' => [],
        ]);
    }

    /**
     * Day labels and durations.
     */
    public function test_labels(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $noon = usergetmidnight(time()) + 12 * HOURSECS;
        $dashboard = $this->dashboard($noon);

        $this->assertSame('Today', $dashboard->day_label($noon + HOURSECS));
        $this->assertSame('today', $dashboard->day_label($noon + HOURSECS, true));
        $this->assertSame('Tomorrow', $dashboard->day_label($noon + DAYSECS));
        $this->assertSame('Yesterday', $dashboard->day_label($noon - DAYSECS));
        $this->assertSame(userdate($noon + 3 * DAYSECS, '%A'), $dashboard->day_label($noon + 3 * DAYSECS));
        $this->assertSame(2, $dashboard->days_from_today($noon + 2 * DAYSECS));

        $this->assertSame('42 min', $dashboard->duration(41 * MINSECS + 5));
        $this->assertSame('1 h 05 min', $dashboard->duration(HOURSECS + 5 * MINSECS));
        $this->assertSame('2 h 00 min', $dashboard->duration(2 * HOURSECS - 10));
    }

    /**
     * The next class goes into the card and the rest into Coming up; statuses of due items.
     */
    public function test_export(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $now = usergetmidnight(time()) + 10 * HOURSECS;
        $class = function (string $name, int $start) {
            return ['cmid' => 1, 'name' => $name, 'courseid' => 2, 'course' => 'Fish &amp; Chips', 'start' => $start,
                'end' => $start + HOURSECS, 'url' => 'u', 'joinurl' => 'j'];
        };
        $item = function (string $name, int $time, string $type = 'due') {
            return ['id' => 1, 'cmid' => 1, 'modname' => 'assign', 'modtype' => 'Assignment', 'name' => $name,
                'courseid' => 2, 'course' => 'C', 'type' => $type, 'time' => $time, 'url' => 'u', 'actionname' => 'Add',
                'actionurl' => 'a', 'actionable' => true, 'itemcount' => 1];
        };
        $dashboard = $this->dashboard($now, [
            'classes' => [$class('Live', $now - 10 * MINSECS), $class('Next', $now + DAYSECS)],
            'deadlines' => [
                $item('Late', $now - HOURSECS),
                $item('Tonight', $now + 8 * HOURSECS),
                $item('Tomorrow', $now + DAYSECS),
                $item('Later', $now + 3 * DAYSECS),
                $item('Opened', $now - DAYSECS, 'open'),
                ['extended' => true] + $item('Extended', $now + DAYSECS),
            ],
        ]);
        $context = $dashboard->export_for_template($PAGE->get_renderer('block_smooth_dashboard'));

        $this->assertSame('Live', $context['next']['name']);
        $this->assertTrue($context['next']['live']);
        $this->assertSame('50 min', $context['next']['endsin']);
        $this->assertSame(['Next'], array_column($context['classes'], 'name'));
        $classes = json_decode($context['classesjson'], true);
        $this->assertSame('Fish & Chips', $classes[0]['course']);
        $this->assertSame(
            ['overdue', 'today', 'tomorrow', 'later', 'open', 'tomorrow'],
            array_column($context['deadlines'], 'status')
        );
        $this->assertSame('Open now', $context['deadlines'][4]['statustext']);
        $this->assertStringStartsWith('Opened yesterday', $context['deadlines'][4]['when']);
        $this->assertStringStartsWith('Extended to tomorrow', $context['deadlines'][5]['when']);
        $this->assertSame('In 3 days', $context['deadlines'][3]['statustext']);
        $this->assertStringStartsWith('Due today', $context['deadlines'][1]['when']);
        $this->assertSame(['1 class today', '4 due soon'], array_column($context['greeting']['chips'], 'text'));

        // With the next class card off, every class goes into Coming up.
        $dashboard = $this->dashboard($now, ['shownextclass' => false,
            'classes' => [$class('Live', $now - 10 * MINSECS), $class('Next', $now + DAYSECS)]]);
        $context = $dashboard->export_for_template($PAGE->get_renderer('block_smooth_dashboard'));
        $this->assertNull($context['next']);
        $this->assertSame(['Live', 'Next'], array_column($context['classes'], 'name'));
    }

    /**
     * The whole dashboard for a student renders, and the app gets strings only.
     */
    public function test_content_and_app(): void {
        global $PAGE;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1, 'fullname' => 'Physics']);
        $user = $generator->create_and_enrol($course, 'student', ['firstname' => 'Rafi']);
        $generator->create_module('assign', ['course' => $course->id, 'name' => 'Report', 'duedate' => time() + DAYSECS,
            'assignsubmission_onlinetext_enabled' => 1]);
        $generator->create_module('page', ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $this->setUser($user);
        $PAGE->set_url('/my/');

        $data = content::build();
        $this->assertFalse(content::is_empty($data));
        $this->assertSame(['Report'], array_column($data['deadlines'], 'name'));
        $this->assertCount(1, $data['courses']);

        $html = $PAGE->get_renderer('block_smooth_dashboard')->render(new dashboard($data));
        $this->assertStringContainsString('Rafi', $html);
        $this->assertStringContainsString('Report', $html);
        $this->assertStringContainsString('Physics', $html);

        $app = mobile::mobile_block_view([]);
        $this->assertNotEmpty($app['templates'][0]['html']);
        foreach ($app['otherdata'] as $value) {
            $this->assertIsString($value);
        }
        $this->assertSame('Report', json_decode($app['otherdata']['deadlines'], true)[0]['name']);

        $this->assertSame('1', $app['otherdata']['showdeadlines']);

        // Switched off sections are not built, and reach the app as empty (false) values: the app
        // only decodes values that start with { or [.
        set_config('showdeadlines', 0, 'block_smooth_dashboard');
        set_config('showcourses', 0, 'block_smooth_dashboard');
        $data = content::build();
        $this->assertNull($data['deadlines']);
        $this->assertNull($data['courses']);
        $app = mobile::mobile_block_view([]);
        $this->assertSame('1', $app['otherdata']['show']);
        $this->assertSame('', $app['otherdata']['showdeadlines']);
        $this->assertSame('', $app['otherdata']['showcourses']);
        $this->assertStringStartsWith('{', $app['otherdata']['greeting']);

        // With the greeting off too, this student has nothing to see: the block stays hidden.
        set_config('showgreeting', 0, 'block_smooth_dashboard');
        $this->assertTrue(content::is_empty(content::build()));
        $app = mobile::mobile_block_view([]);
        $this->assertSame(['show' => ''], $app['otherdata']);
    }

    /**
     * Only plain colour values reach the style attribute.
     */
    public function test_accent_style(): void {
        $this->resetAfterTest();
        $this->assertSame('', \block_smooth_dashboard\local\config::accent_style());
        set_config('accent', '#0f6cbf', 'block_smooth_dashboard');
        set_config('accent2', 'red; background: url(x)', 'block_smooth_dashboard');
        $this->assertSame('--sd-accent-site: #0f6cbf;', \block_smooth_dashboard\local\config::accent_style());
    }

    /**
     * Quiz attempts to grade say "To grade" and are not counted as due; a card with closed
     * activities left is not "All done".
     */
    public function test_tograde_and_closed(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $now = usergetmidnight(time()) + 10 * HOURSECS;
        $dashboard = $this->dashboard($now, [
            'deadlines' => [['id' => -5, 'cmid' => 5, 'modname' => 'quiz', 'modtype' => 'Quiz', 'name' => 'Essay quiz',
                'courseid' => 2, 'course' => 'C', 'type' => 'tograde', 'time' => $now - DAYSECS, 'url' => 'u',
                'actionname' => 'Grade', 'actionurl' => 'a', 'actionable' => true, 'itemcount' => 3]],
            'courses' => [
                ['id' => 2, 'name' => 'C', 'url' => 'u', 'image' => 'i', 'progress' => 50, 'todo' => [], 'todocount' => 0,
                    'closedcount' => 1],
                ['id' => 3, 'name' => 'D', 'url' => 'u', 'image' => 'i', 'progress' => 100, 'todo' => [], 'todocount' => 0,
                    'closedcount' => 0],
            ],
        ]);
        $context = $dashboard->export_for_template($PAGE->get_renderer('block_smooth_dashboard'));

        $item = $context['deadlines'][0];
        $this->assertSame('tograde', $item['status']);
        $this->assertSame('To grade', $item['statustext']);
        $this->assertFalse($item['overdue']);
        $this->assertStringStartsWith('Waiting since yesterday', $item['when']);
        $this->assertSame('Grade (3)', $item['actiontext']);
        $this->assertSame(['Nothing due today'], array_column($context['greeting']['chips'], 'text'));
        $this->assertSame([false, true], array_column($context['courses'], 'done'));
    }

    /**
     * Without a class that has a time, the Next class card lists the recurring classes with no
     * fixed time, on the website and in the app.
     */
    public function test_rooms_on_card(): void {
        global $PAGE;
        $this->resetAfterTest();
        if (!\core_component::get_component_directory('mod_zoom')) {
            $this->markTestSkipped('mod_zoom is not installed.');
        }
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Spoken English']);
        $user = $generator->create_and_enrol($course, 'student');
        $generator->create_module('zoom', ['course' => $course->id, 'name' => 'Batch 1 class', 'recurring' => 1,
            'recurrence_type' => 0, 'start_time' => time(), 'duration' => HOURSECS]);
        $this->setUser($user);
        $PAGE->set_url('/my/');

        $data = content::build();
        $this->assertSame([], $data['classes']);
        $this->assertSame(['Batch 1 class'], array_column($data['rooms'], 'name'));
        $html = $PAGE->get_renderer('block_smooth_dashboard')->render(new dashboard($data));
        $this->assertStringContainsString('Your classes', $html);
        $this->assertStringContainsString('Batch 1 class', $html);
        $this->assertStringNotContainsString('No classes scheduled', $html);

        $app = mobile::mobile_block_view([]);
        $this->assertSame('Batch 1 class', json_decode($app['otherdata']['rooms'], true)[0]['name']);
        $this->assertStringContainsString('CONTENT_OTHERDATA.rooms', $app['templates'][0]['html']);
    }

    /**
     * Someone with no courses (a parent, office staff) gets no block: an empty student dashboard
     * would say their child has no classes.
     */
    public function test_no_courses_no_block(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $data = content::build();
        $this->assertTrue(content::is_empty($data));
        $this->assertSame(['show' => ''], mobile::mobile_block_view([])['otherdata']);
    }
}
