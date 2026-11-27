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
 * The dashboard, ready for its template.
 *
 * @package    block_smooth_dashboard
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_smooth_dashboard\output;

use block_smooth_dashboard\local\content;
use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;

/**
 * Formats content::build() for the block_smooth_dashboard/main template and the Moodle app:
 * dates in the user's time zone and language, "Today" and "Tomorrow", statuses and counts.
 */
class dashboard implements renderable, templatable {
    /** @var int A class this close to its start is "starting soon". */
    public const SOON_SECS = 15 * MINSECS;

    /** @var array From content::build(). */
    protected array $data;

    /** @var int The current time. */
    protected int $now;

    /**
     * Constructor.
     *
     * @param array $data From content::build().
     */
    public function __construct(array $data) {
        $this->data = $data;
        $this->now = $data['now'];
    }

    /**
     * Template context.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $classes = $this->export_classes();
        $deadlines = $this->export_deadlines();
        $courses = $this->export_courses();
        $rankings = $this->export_rankings();

        $context = [
            'uniqid' => \html_writer::random_id('smooth-dashboard-'),
            'style' => \block_smooth_dashboard\local\config::accent_style(),
            'greeting' => $this->data['greeting'] ? $this->export_greeting($classes, $deadlines) : null,
            'shownextclass' => $this->data['shownextclass'],
            'next' => $this->data['shownextclass'] && $classes ? $this->export_next($classes[0]) : null,
            // Recurring classes with no fixed time, on the card when no class has a time.
            'rooms' => array_values($this->data['rooms'] ?? []),
            'classesjson' => json_encode(self::plain_classes($classes)),
            'now' => $this->now,
            // Formats for the countdown script, which fills in {h} and {m}.
            'hoursformat' => get_string('durationhours', 'block_smooth_dashboard', (object) ['hours' => '{h}', 'minutes' => '{m}']),
            'minutesformat' => get_string('durationminutes', 'block_smooth_dashboard', '{m}'),
            'showclasses' => $this->data['showclasses'],
            // The first class is in the hero when it is on; the list starts after it.
            'classes' => array_values(array_slice($classes, $this->data['shownextclass'] ? 1 : 0)),
            'showdeadlines' => $this->data['deadlines'] !== null,
            'deadlines' => $deadlines,
            'showcourses' => $this->data['courses'] !== null,
            'courses' => $courses,
            'coursestotal' => (int) ($this->data['coursestotal'] ?? 0),
            'morecourses' => count($courses) < (int) ($this->data['coursestotal'] ?? 0),
            'mycoursesurl' => (new \moodle_url('/my/courses.php'))->out(false),
            'showrankings' => (bool) $rankings,
            'rankings' => $rankings,
            'calendarurl' => (new \moodle_url('/calendar/view.php', ['view' => 'upcoming']))->out(false),
        ];
        $context['hasclasses'] = (bool) $context['classes'];
        $context['hasrooms'] = (bool) $context['rooms'];
        $context['hasdeadlines'] = (bool) $deadlines;
        $context['hascourses'] = (bool) $courses;
        $context['showside'] = $context['showclasses'] || $context['showdeadlines'];
        return $context;
    }

    /**
     * Greeting by time of day, today's date and a short summary of the day.
     *
     * @param array $classes From export_classes().
     * @param array $deadlines From export_deadlines().
     * @return array
     */
    protected function export_greeting(array $classes, array $deadlines): array {
        global $USER;
        $hour = (int) userdate($this->now, '%H');
        $key = $hour < 5 ? 'greetingevening' : ($hour < 12 ? 'greetingmorning' : ($hour < 17 ? 'greetingafternoon'
            : 'greetingevening'));
        $classestoday = count(array_filter($classes, function ($class) {
            return $class['istoday'];
        }));
        // Overdue, today or tomorrow; an activity opening is not something due, nor is grading.
        $duesoon = count(array_filter($deadlines, function ($item) {
            return !in_array($item['type'], ['open', 'tograde']) && ($item['overdue'] || $item['days'] <= 1);
        }));
        $chips = [];
        if ($classestoday) {
            $chips[] = ['text' => get_string(
                $classestoday == 1 ? 'chipclass' : 'chipclasses',
                'block_smooth_dashboard',
                $classestoday
            ), 'kind' => 'class'];
        }
        if ($duesoon) {
            $chips[] = ['text' => get_string($duesoon == 1 ? 'chipdue' : 'chipdues', 'block_smooth_dashboard', $duesoon),
                'kind' => 'due'];
        }
        if (!$chips) {
            $chips[] = ['text' => get_string('chipclear', 'block_smooth_dashboard'), 'kind' => 'clear'];
        }
        return [
            // Plain text: the template escapes it.
            'text' => get_string($key, 'block_smooth_dashboard', $USER->firstname),
            'date' => userdate($this->now, get_string('strftimedaydate', 'langconfig')),
            'chips' => $chips,
        ];
    }

    /**
     * Upcoming classes with their dates as text.
     *
     * @return array[]
     */
    protected function export_classes(): array {
        $rows = [];
        foreach ($this->data['classes'] ?? [] as $index => $class) {
            $days = $this->days_from_today($class['start']);
            $row = $class + [
                'index' => $index,
                'day' => $this->day_label($class['start']),
                'daynum' => userdate($class['start'], '%d'),
                'month' => userdate($class['start'], '%b'),
                'time' => $this->time($class['start']),
                'range' => get_string('timerange', 'block_smooth_dashboard', (object) [
                    'start' => $this->time($class['start']),
                    'end' => $this->time($class['end']),
                ]),
                'when' => get_string('daytime', 'block_smooth_dashboard', (object) [
                    'day' => $this->day_label($class['start']),
                    'time' => $this->time($class['start']),
                ]),
                'istoday' => $days === 0,
                'islive' => $class['start'] <= $this->now,
            ];
            $row['startsat'] = get_string('startsat', 'block_smooth_dashboard', get_string(
                'daytime',
                'block_smooth_dashboard',
                (object) ['day' => $this->day_label($class['start'], true), 'time' => $row['time']]
            ));
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * The hero: the next class and the countdown to it, as rendered before JavaScript runs.
     *
     * @param array $class From export_classes().
     * @return array
     */
    protected function export_next(array $class): array {
        $left = max(0, $class['start'] - $this->now);
        $live = $class['islive'];
        return $class + [
            'live' => $live,
            'soon' => !$live && $left <= self::SOON_SECS,
            'nodays' => $left < DAYSECS,
            'units' => [
                ['unit' => 'days', 'value' => sprintf('%02d', intdiv($left, DAYSECS)),
                    'label' => get_string('unitdays', 'block_smooth_dashboard')],
                ['unit' => 'hours', 'value' => sprintf('%02d', intdiv($left % DAYSECS, HOURSECS)),
                    'label' => get_string('unithours', 'block_smooth_dashboard')],
                ['unit' => 'minutes', 'value' => sprintf('%02d', intdiv($left % HOURSECS, MINSECS)),
                    'label' => get_string('unitminutes', 'block_smooth_dashboard')],
                ['unit' => 'seconds', 'value' => sprintf('%02d', $left % MINSECS),
                    'label' => get_string('unitseconds', 'block_smooth_dashboard')],
            ],
            'endsin' => $live ? $this->duration($class['end'] - $this->now) : '',
        ];
    }

    /**
     * Due soon, with a status for each item.
     *
     * @return array[]
     */
    protected function export_deadlines(): array {
        global $OUTPUT;
        $rows = [];
        foreach ($this->data['deadlines'] ?? [] as $item) {
            $days = $this->days_from_today($item['time']);
            $past = $item['time'] < $this->now;
            // An activity that has opened is ready to do, not late; attempts to grade are not late either.
            $overdue = $past && !in_array($item['type'], ['open', 'tograde']);
            if ($item['type'] === 'tograde') {
                $status = 'tograde';
                $statustext = get_string('statustograde', 'block_smooth_dashboard');
            } else if ($overdue) {
                $status = 'overdue';
                $statustext = get_string('statusoverdue', 'block_smooth_dashboard');
            } else if ($past) {
                $status = 'open';
                $statustext = get_string('statusopen', 'block_smooth_dashboard');
            } else if ($days <= 0) {
                $status = 'today';
                $statustext = get_string('statustoday', 'block_smooth_dashboard');
            } else if ($days === 1) {
                $status = 'tomorrow';
                $statustext = get_string('statustomorrow', 'block_smooth_dashboard');
            } else {
                $status = 'later';
                $statustext = get_string('statusindays', 'block_smooth_dashboard', $days);
            }
            if (!empty($item['extended'])) {
                $whenkey = 'eventextended';
            } else if ($item['type'] === 'open' && $past) {
                $whenkey = 'eventopened';
            } else {
                $whenkey = 'event' . $item['type'];
            }
            $rows[] = $item + [
                'days' => $days,
                'overdue' => $overdue,
                'status' => $status,
                'statustext' => $statustext,
                'when' => get_string($whenkey, 'block_smooth_dashboard', (object) [
                    'day' => $this->day_label($item['time'], $item['type'] !== 'other'),
                    'time' => $this->time($item['time']),
                ]),
                'iconurl' => $OUTPUT->image_url('monologo', $item['modname'])->out(false),
                // The calendar marks an activity that has not opened yet as not actionable, and still
                // names its action ("Attempt quiz now"): say why it cannot be done instead.
                'actiontext' => !$item['actionable'] ? get_string('notopenyet', 'block_smooth_dashboard')
                    : ($item['itemcount'] > 1
                    ? get_string('actioncount', 'block_smooth_dashboard', (object) [
                        'action' => $item['actionname'], 'count' => $item['itemcount']])
                    : $item['actionname']),
            ];
        }
        return $rows;
    }

    /**
     * Course cards, with attendance and leaderboard place when available.
     *
     * @return array[]
     */
    protected function export_courses(): array {
        $cards = [];
        foreach ($this->data['courses'] ?? [] as $card) {
            $attendance = $this->data['attendance'][$card['id']] ?? null;
            $ranking = $this->data['rankings'][$card['id']] ?? null;
            $card['hasprogress'] = $card['progress'] !== null;
            // Not "All done" while activities that closed before being done are left.
            $card['done'] = $card['hasprogress'] && $card['todocount'] === 0 && empty($card['closedcount']);
            $card['todomore'] = max(0, $card['todocount'] - count($card['todo']));
            $card['hastodo'] = (bool) $card['todo'];
            $card['todotext'] = $card['todocount']
                ? get_string($card['todocount'] == 1 ? 'todoone' : 'todomany', 'block_smooth_dashboard', $card['todocount'])
                : '';
            $card['continueurl'] = $card['todo'] ? $card['todo'][0]['url'] : $card['url'];
            $card['attendance'] = $attendance ? [
                'text' => get_string('attendancevalue', 'block_smooth_dashboard', format_float($attendance['percentage'], 0)),
                'level' => $attendance['level'],
                'url' => $attendance['url'],
                'low' => $attendance['level'] === 'low',
            ] : null;
            $card['rank'] = $ranking && $ranking['me'] ? [
                'text' => get_string('rankvalue', 'block_smooth_dashboard', (object) $ranking['me']),
                'url' => $ranking['url'],
            ] : null;
            $card['hasmeta'] = $card['attendance'] || $card['rank'] || $card['todotext'];
            $cards[] = $card;
        }
        return $cards;
    }

    /**
     * Leaderboards: the top students and the user's own place, per course.
     *
     * @return array[]
     */
    protected function export_rankings(): array {
        $boards = [];
        foreach ($this->data['rankings'] ?? [] as $board) {
            $meinlist = false;
            foreach ($board['rows'] as $row) {
                $meinlist = $meinlist || $row['isme'];
            }
            $board['meoutside'] = $board['me'] && !$meinlist;
            $board['metext'] = $board['me'] ? get_string('rankyou', 'block_smooth_dashboard', (object) $board['me']) : '';
            $boards[] = $board;
        }
        return $boards;
    }

    /**
     * Classes as plain text, for the countdown script (which sets text, never HTML) and the app.
     *
     * @param array[] $classes From export_classes().
     * @return array[] Each with index, name, course, start, end, url, joinurl, day, when, range and startsat.
     */
    public static function plain_classes(array $classes): array {
        $rows = [];
        foreach ($classes as $class) {
            $row = array_intersect_key($class, array_flip(['index', 'name', 'course', 'start', 'end', 'url', 'joinurl',
                'day', 'when', 'range', 'startsat']));
            $row['name'] = self::plain($row['name']);
            $row['course'] = self::plain($row['course']);
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Text from format_string() (HTML-safe) as plain text.
     *
     * @param string $text
     * @return string
     */
    public static function plain(string $text): string {
        return html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Whole days from today to a time's day, in the user's time zone: 0 today, 1 tomorrow, -1
     * yesterday.
     *
     * @param int $time
     * @return int
     */
    public function days_from_today(int $time): int {
        // Rounded, because a day across a daylight saving change is 23 or 25 hours.
        return (int) round((usergetmidnight($time) - usergetmidnight($this->now)) / DAYSECS);
    }

    /**
     * "Today", "Tomorrow", "Yesterday", a weekday within the week, or a short date.
     *
     * @param int $time
     * @param bool $insentence For use inside a sentence ("Due today"), where some languages
     *     write it differently.
     * @return string
     */
    public function day_label(int $time, bool $insentence = false): string {
        $days = $this->days_from_today($time);
        $suffix = $insentence ? '_insentence' : '';
        if ($days === 0) {
            return get_string('today' . $suffix, 'block_smooth_dashboard');
        } else if ($days === 1) {
            return get_string('tomorrow' . $suffix, 'block_smooth_dashboard');
        } else if ($days === -1) {
            return get_string('yesterday' . $suffix, 'block_smooth_dashboard');
        } else if ($days > 1 && $days < 7) {
            return userdate($time, '%A');
        }
        return userdate($time, get_string('strftimeshortday', 'block_smooth_dashboard'));
    }

    /**
     * A time of day, in the user's format (12 or 24 hours).
     *
     * @param int $time
     * @return string
     */
    public function time(int $time): string {
        return userdate($time, get_string('strftimetime', 'langconfig'));
    }

    /**
     * A short duration: "1 h 05 min", "42 min".
     *
     * @param int $secs
     * @return string
     */
    public function duration(int $secs): string {
        $secs = max(0, $secs);
        $hours = intdiv($secs, HOURSECS);
        $minutes = (int) ceil(($secs % HOURSECS) / MINSECS);
        if ($minutes === 60) {
            $hours++;
            $minutes = 0;
        }
        if ($hours) {
            return get_string('durationhours', 'block_smooth_dashboard', (object) [
                'hours' => $hours, 'minutes' => sprintf('%02d', $minutes)]);
        }
        return get_string('durationminutes', 'block_smooth_dashboard', $minutes);
    }

    /**
     * The raw content.
     *
     * @return array
     */
    public function get_data(): array {
        return $this->data;
    }
}
