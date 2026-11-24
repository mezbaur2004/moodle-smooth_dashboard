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
 * Moodle app support.
 *
 * @package    block_smooth_dashboard
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_smooth_dashboard\output;

use block_smooth_dashboard\local\content;

/**
 * The dashboard on the Moodle app's Dashboard, with the same sections as on the website.
 *
 * The data goes to the app as plain text in otherdata; the app's template escapes it. A small
 * script keeps the countdown ticking, and stops when the block leaves the screen. The script uses
 * the device's clock: the app keeps block content for a while, so the server's time when the
 * content was made would not be the time now.
 */
class mobile {
    /**
     * The block.
     *
     * @param array $args From the app.
     * @return array
     */
    public static function mobile_block_view(array $args): array {
        global $PAGE;
        $otherdata = ['show' => self::flag(false)];
        if (isloggedin() && !isguestuser()) {
            $PAGE->set_context(\context_system::instance());
            $data = content::build();
            if (!content::is_empty($data)) {
                $dashboard = new dashboard($data);
                $otherdata = self::otherdata($dashboard->export_for_template($PAGE->get_renderer('block_smooth_dashboard')));
            }
        }
        return [
            'templates' => [['id' => 'main', 'html' => self::template()]],
            'javascript' => self::javascript(),
            'otherdata' => $otherdata,
        ];
    }

    /**
     * The template context as plain text, each value a string as the app expects (arrays as JSON).
     *
     * @param array $context From dashboard::export_for_template().
     * @return array
     */
    public static function otherdata(array $context): array {
        $plain = [dashboard::class, 'plain'];
        $classes = json_decode($context['classesjson'], true) ?: [];
        $deadlines = [];
        foreach ($context['deadlines'] as $item) {
            $deadlines[] = [
                'name' => $plain($item['name']),
                'course' => $plain($item['course']),
                'modtype' => $item['modtype'],
                'url' => $item['url'],
                'status' => $item['status'],
                'statustext' => $item['statustext'],
                'color' => [
                    'overdue' => 'danger', 'today' => 'warning', 'tomorrow' => 'primary', 'open' => 'success',
                ][$item['status']] ?? 'medium',
                'when' => $item['when'],
            ];
        }
        $courses = [];
        foreach ($context['courses'] as $card) {
            $todo = [];
            foreach ($card['todo'] as $item) {
                $todo[] = ['name' => $plain($item['name']), 'url' => $item['url']];
            }
            $courses[] = [
                'name' => $plain($card['name']),
                'url' => $card['url'],
                'hasprogress' => $card['hasprogress'],
                'progress' => (int) $card['progress'],
                'fraction' => $card['hasprogress'] ? $card['progress'] / 100 : 0,
                'todotext' => $card['todotext'],
                'todo' => $todo,
                'attendance' => $card['attendance'] ? [
                    'text' => $card['attendance']['text'],
                    'color' => ['good' => 'success', 'fair' => 'warning', 'low' => 'danger'][$card['attendance']['level']],
                ] : null,
                'rank' => $card['rank'] ? $card['rank']['text'] : '',
            ];
        }
        $rankings = [];
        foreach ($context['rankings'] as $board) {
            $rows = [];
            foreach ($board['rows'] as $row) {
                $rows[] = ['rank' => $row['rank'], 'name' => $row['name'], 'points' => $row['points'], 'isme' => $row['isme']];
            }
            $rankings[] = [
                'course' => $plain($board['course']),
                'url' => $board['url'],
                'rows' => $rows,
                'me' => $board['meoutside'] ? $board['metext'] : '',
            ];
        }

        $strings = [];
        foreach (
            [
            'nextclass', 'livenow', 'startingsoon', 'endsin', 'startsin', 'joinclass', 'joinnow', 'comingup', 'duesoon',
            'mycourses', 'leaderboards', 'noclasses', 'noclasses_help', 'nomoreclasses', 'allcaughtup', 'nodeadlines',
            'upnext', 'nocourses',
            ] as $identifier
        ) {
            $strings[$identifier] = get_string($identifier, 'block_smooth_dashboard');
        }
        $strings['days'] = get_string('unitdaysshort', 'block_smooth_dashboard');
        $strings['hours'] = get_string('durationhours', 'block_smooth_dashboard', (object) ['hours' => '{h}', 'minutes' => '{m}']);
        $strings['minutes'] = get_string('durationminutes', 'block_smooth_dashboard', '{m}');

        return [
            'show' => self::flag(true),
            // An object, or empty (false in the template) when the greeting is off.
            'greeting' => $context['greeting'] ? json_encode($context['greeting']) : '',
            'shownextclass' => self::flag($context['shownextclass']),
            'showclasses' => self::flag($context['showclasses']),
            'classes' => json_encode($classes),
            'showdeadlines' => self::flag($context['showdeadlines']),
            'deadlines' => json_encode($deadlines),
            'showcourses' => self::flag($context['showcourses']),
            'courses' => json_encode($courses),
            'rankings' => json_encode($rankings),
            'strings' => json_encode($strings),
        ];
    }

    /**
     * A flag for otherdata. The app only decodes values that start with { or [, so "false" would
     * reach the template as a non-empty, true, string: flags are "1" or "".
     *
     * @param bool $value
     * @return string
     */
    public static function flag(bool $value): string {
        return $value ? '1' : '';
    }

    /**
     * The app template (Ionic and Angular). Values are interpolated, so the app escapes them.
     *
     * @return string
     */
    protected static function template(): string {
        return <<<'HTML'
<div *ngIf="CONTENT_OTHERDATA.show">
    <ion-item lines="none" *ngIf="CONTENT_OTHERDATA.greeting">
        <ion-label>
            <h2><strong>{{ CONTENT_OTHERDATA.greeting.text }}</strong></h2>
            <p>{{ CONTENT_OTHERDATA.greeting.date }}</p>
            <p>
                <ion-badge *ngFor="let chip of CONTENT_OTHERDATA.greeting.chips" color="light" class="ion-margin-end">
                    {{ chip.text }}
                </ion-badge>
            </p>
        </ion-label>
    </ion-item>

    <ng-container *ngIf="CONTENT_OTHERDATA.shownextclass">
        <ion-card *ngIf="sdCurrent() as next; else noClass" [color]="sdIsLive(next) ? 'danger' : 'primary'">
            <ion-card-header>
                <ion-card-subtitle>
                    {{ CONTENT_OTHERDATA.strings.nextclass }}
                    <ion-badge *ngIf="sdIsLive(next)" color="light">{{ CONTENT_OTHERDATA.strings.livenow }}</ion-badge>
                    <ion-badge *ngIf="sdIsSoon(next)" color="light">{{ CONTENT_OTHERDATA.strings.startingsoon }}</ion-badge>
                </ion-card-subtitle>
                <ion-card-title>{{ next.name }}</ion-card-title>
            </ion-card-header>
            <ion-card-content>
                <p>{{ next.course }}</p>
                <p>{{ next.day }} · {{ next.range }}</p>
                <h1 *ngIf="!sdIsLive(next)" class="ion-text-center ion-margin-vertical">
                    <strong>{{ sdCountdown(next) }}</strong>
                </h1>
                <h2 *ngIf="sdIsLive(next)" class="ion-text-center ion-margin-vertical">
                    {{ CONTENT_OTHERDATA.strings.endsin }} <strong>{{ sdEndsIn(next) }}</strong>
                </h2>
                <ion-button expand="block" color="light" core-link [href]="next.url" [capture]="true">
                    {{ sdIsLive(next) ? CONTENT_OTHERDATA.strings.joinnow : CONTENT_OTHERDATA.strings.joinclass }}
                </ion-button>
            </ion-card-content>
        </ion-card>
        <ng-template #noClass>
            <ion-card>
                <ion-card-header>
                    <ion-card-subtitle>{{ CONTENT_OTHERDATA.strings.nextclass }}</ion-card-subtitle>
                    <ion-card-title>{{ CONTENT_OTHERDATA.strings.noclasses }}</ion-card-title>
                </ion-card-header>
                <ion-card-content>{{ CONTENT_OTHERDATA.strings.noclasses_help }}</ion-card-content>
            </ion-card>
        </ng-template>
    </ng-container>

    <ion-list *ngIf="CONTENT_OTHERDATA.showclasses && sdLater().length">
        <ion-item-divider><ion-label><h2>{{ CONTENT_OTHERDATA.strings.comingup }}</h2></ion-label></ion-item-divider>
        <ion-item *ngFor="let item of sdLater()" button core-link [href]="item.url" [capture]="true" detail="true">
            <ion-label>
                <h3>{{ item.name }}</h3>
                <p>{{ item.course }}</p>
                <p>{{ item.day }} · {{ item.range }}</p>
            </ion-label>
        </ion-item>
    </ion-list>

    <ion-list *ngIf="CONTENT_OTHERDATA.showdeadlines">
        <ion-item-divider><ion-label><h2>{{ CONTENT_OTHERDATA.strings.duesoon }}</h2></ion-label></ion-item-divider>
        <ion-item *ngFor="let item of CONTENT_OTHERDATA.deadlines" button core-link [href]="item.url" [capture]="true">
            <ion-label>
                <h3>{{ item.name }}</h3>
                <p>{{ item.modtype }} · {{ item.course }}</p>
                <p>{{ item.when }}</p>
            </ion-label>
            <ion-badge slot="end" [color]="item.color">{{ item.statustext }}</ion-badge>
        </ion-item>
        <ion-item *ngIf="!CONTENT_OTHERDATA.deadlines.length" lines="none">
            <ion-label>
                <h3>{{ CONTENT_OTHERDATA.strings.allcaughtup }}</h3>
                <p>{{ CONTENT_OTHERDATA.strings.nodeadlines }}</p>
            </ion-label>
        </ion-item>
    </ion-list>

    <ion-list *ngIf="CONTENT_OTHERDATA.showcourses && CONTENT_OTHERDATA.courses.length">
        <ion-item-divider><ion-label><h2>{{ CONTENT_OTHERDATA.strings.mycourses }}</h2></ion-label></ion-item-divider>
        <ng-container *ngFor="let course of CONTENT_OTHERDATA.courses">
            <ion-item button core-link [href]="course.url" [capture]="true" lines="none">
                <ion-label>
                    <h3><strong>{{ course.name }}</strong></h3>
                    <p *ngIf="course.hasprogress">{{ course.progress }}%</p>
                </ion-label>
                <ion-badge slot="end" *ngIf="course.attendance" [color]="course.attendance.color">
                    {{ course.attendance.text }}
                </ion-badge>
                <ion-badge slot="end" *ngIf="course.rank" color="warning">{{ course.rank }}</ion-badge>
            </ion-item>
            <ion-progress-bar *ngIf="course.hasprogress" [value]="course.fraction" class="ion-padding-horizontal">
            </ion-progress-bar>
            <ion-item *ngFor="let item of course.todo" button core-link [href]="item.url" [capture]="true" lines="none">
                <ion-label><p>{{ CONTENT_OTHERDATA.strings.upnext }}: {{ item.name }}</p></ion-label>
            </ion-item>
        </ng-container>
    </ion-list>

    <ion-list *ngFor="let board of CONTENT_OTHERDATA.rankings">
        <ion-item-divider>
            <ion-label><h2>{{ CONTENT_OTHERDATA.strings.leaderboards }}: {{ board.course }}</h2></ion-label>
        </ion-item-divider>
        <ion-item *ngFor="let row of board.rows" [color]="row.isme ? 'light' : ''">
            <ion-badge slot="start" [color]="row.rank === 1 ? 'warning' : 'medium'">{{ row.rank }}</ion-badge>
            <ion-label>{{ row.name }}</ion-label>
            <ion-note slot="end">{{ row.points }}</ion-note>
        </ion-item>
        <ion-item *ngIf="board.me" lines="none"><ion-label><strong>{{ board.me }}</strong></ion-label></ion-item>
    </ion-list>
</div>
HTML;
    }

    /**
     * The countdown: the component's time moves on every second while the block is on screen, and
     * stops when it leaves (the app calls a plugin's own ngOnDestroy).
     *
     * @return string
     */
    protected static function javascript(): string {
        return <<<'JS'
var self = this;
var soon = 15 * 60;
self.sdNow = Math.floor(Date.now() / 1000);
var pad = function(value) {
    return (value < 10 ? '0' : '') + value;
};
self.sdCurrent = function() {
    var classes = (self.CONTENT_OTHERDATA && self.CONTENT_OTHERDATA.classes) || [];
    for (var i = 0; i < classes.length; i++) {
        if (classes[i].end > self.sdNow) {
            return classes[i];
        }
    }
    return null;
};
self.sdLater = function() {
    var current = self.sdCurrent();
    var classes = (self.CONTENT_OTHERDATA && self.CONTENT_OTHERDATA.classes) || [];
    var showsnext = self.CONTENT_OTHERDATA && self.CONTENT_OTHERDATA.shownextclass;
    return classes.filter(function(item) {
        return item.end > self.sdNow && (!showsnext || !current || item.index > current.index);
    });
};
self.sdIsLive = function(item) {
    return item.start <= self.sdNow;
};
self.sdIsSoon = function(item) {
    return item.start > self.sdNow && item.start - self.sdNow <= soon;
};
self.sdCountdown = function(item) {
    var left = Math.max(0, item.start - self.sdNow);
    var days = Math.floor(left / 86400);
    var text = pad(Math.floor((left % 86400) / 3600)) + ':' + pad(Math.floor((left % 3600) / 60)) + ':' + pad(left % 60);
    return days ? days + self.CONTENT_OTHERDATA.strings.days + ' ' + text : text;
};
self.sdEndsIn = function(item) {
    var left = Math.max(0, item.end - self.sdNow);
    var hours = Math.floor(left / 3600);
    var minutes = Math.ceil((left % 3600) / 60);
    if (minutes === 60) {
        hours++;
        minutes = 0;
    }
    var strings = self.CONTENT_OTHERDATA.strings;
    return hours ? strings.hours.replace('{h}', hours).replace('{m}', pad(minutes)) : strings.minutes.replace('{m}', minutes);
};
// The app calls ngOnDestroy set here when the block leaves the screen.
var timer = setInterval(function() {
    self.sdNow = Math.floor(Date.now() / 1000);
}, 1000);
self.ngOnDestroy = function() {
    clearInterval(timer);
};
JS;
    }
}
