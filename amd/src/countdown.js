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
 * Live countdown to the next class.
 *
 * Ticks every second from the class times in the page (data-classes), so it needs no requests:
 * "Starting soon" from 15 minutes before, "Live now" with the time left during the class, and the
 * following class once it ends. Classes moved into the card are dropped from Coming up.
 *
 * @module     block_smooth_dashboard/countdown
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';

/** @var {number} A class this close to its start is "starting soon", in seconds. */
const SOON = 15 * 60;

/**
 * Two digits.
 *
 * @param {number} value
 * @returns {string}
 */
const pad = (value) => String(value).padStart(2, '0');

/**
 * Start the countdown of one dashboard.
 *
 * @param {string} rootId The dashboard's element id.
 */
export const init = async(rootId) => {
    const root = document.getElementById(rootId);
    const hero = root ? root.querySelector('[data-region="nextclass"]') : null;
    if (!hero) {
        return;
    }
    let classes = [];
    try {
        classes = JSON.parse(hero.dataset.classes || '[]');
    } catch (e) {
        classes = [];
    }

    const [hours, minutes] = await Promise.all([
        getString('durationhours', 'block_smooth_dashboard', {hours: '{h}', minutes: '{m}'}),
        getString('durationminutes', 'block_smooth_dashboard', '{m}'),
    ]);
    const duration = (secs) => {
        const h = Math.floor(secs / 3600);
        const m = Math.ceil((secs % 3600) / 60);
        if (h || m === 60) {
            return hours.replace('{h}', m === 60 ? h + 1 : h).replace('{m}', pad(m === 60 ? 0 : m));
        }
        return minutes.replace('{m}', m);
    };

    const field = (name) => hero.querySelector(`[data-field="${name}"]`);
    let shown = classes.length ? classes[0].index : null;

    const tick = () => {
        const now = Math.floor(Date.now() / 1000);
        const current = classes.find((item) => item.end > now);

        if (!current) {
            // What is shown in each state is up to the styles (is-empty, is-live, is-soon).
            hero.classList.add('is-empty');
            hero.classList.remove('is-live', 'is-soon');
            return false;
        }

        if (current.index !== shown) {
            // The class ended: show the following one and drop it from Coming up.
            shown = current.index;
            field('name').textContent = current.name;
            field('course').textContent = current.course;
            field('day').textContent = current.day;
            field('range').textContent = current.range;
            hero.querySelector('[data-link="url"]').href = current.url;
            hero.querySelector('[data-link="joinurl"]').href = current.joinurl;
            const startsat = field('startsat');
            if (startsat) {
                startsat.textContent = current.startsat;
            }
        }
        const list = root.querySelector('[data-region="classes"]');
        if (list) {
            let left = 0;
            list.querySelectorAll('[data-index]').forEach((item) => {
                item.hidden = Number(item.dataset.index) <= shown;
                left += item.hidden ? 0 : 1;
            });
            const empty = root.querySelector('[data-region="classes-empty"]');
            if (empty) {
                empty.classList.toggle('d-none', left > 0);
            }
        }

        const live = current.start <= now;
        const left = Math.max(0, current.start - now);
        const soon = !live && left <= SOON;
        hero.classList.toggle('is-live', live);
        hero.classList.toggle('is-soon', soon);
        hero.classList.remove('is-empty');

        if (live) {
            field('endsin').textContent = duration(current.end - now);
        } else {
            field('days').textContent = pad(Math.floor(left / 86400));
            field('hours').textContent = pad(Math.floor((left % 86400) / 3600));
            field('minutes').textContent = pad(Math.floor((left % 3600) / 60));
            field('seconds').textContent = pad(left % 60);
            // Days are only worth showing when there are some.
            hero.classList.toggle('no-days', left < 86400);
        }
        return true;
    };

    if (!tick()) {
        return;
    }
    const timer = setInterval(() => {
        if (!document.body.contains(hero) || !tick()) {
            clearInterval(timer);
        }
    }, 1000);
};
