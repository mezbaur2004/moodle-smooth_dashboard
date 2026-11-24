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
 * following class once it ends. Classes that are over, or moved into the card, are dropped from
 * Coming up, also when the next class card is switched off.
 *
 * @module     block_smooth_dashboard/countdown
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @var {number} A class this close to its start is "starting soon", in seconds. */
const SOON = 15 * 60;

/** @var {number} A device clock further than this from the server's is corrected, in seconds. */
const SKEW = 60;

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
 * The page carries everything needed (class times, the server's time and the duration formats),
 * so this makes no requests and cannot fail to start for want of one.
 *
 * @param {string} rootId The dashboard's element id.
 */
export const init = (rootId) => {
    const root = document.getElementById(rootId);
    if (!root) {
        return;
    }
    const hero = root.querySelector('[data-region="nextclass"]');
    const list = root.querySelector('[data-region="classes"]');
    if (!hero && !list) {
        return;
    }
    let classes = [];
    try {
        classes = JSON.parse(root.dataset.classes || '[]');
    } catch (e) {
        classes = [];
    }

    // A phone whose clock is wrong would count down to the wrong moment: go by the server's clock
    // when the two are clearly apart. Small gaps are left alone, as they are mostly page load time.
    const servernow = Number(root.dataset.now) || 0;
    let offset = servernow ? servernow - Date.now() / 1000 : 0;
    if (Math.abs(offset) < SKEW) {
        offset = 0;
    }
    const clock = () => Math.floor(Date.now() / 1000 + offset);

    const hoursformat = root.dataset.hoursformat || '{h} h {m} min';
    const minutesformat = root.dataset.minutesformat || '{m} min';
    const duration = (secs) => {
        let h = Math.floor(secs / 3600);
        let m = Math.ceil((secs % 3600) / 60);
        if (m === 60) {
            h++;
            m = 0;
        }
        return h ? hoursformat.replace('{h}', h).replace('{m}', pad(m)) : minutesformat.replace('{m}', m);
    };

    const field = (name) => hero.querySelector(`[data-field="${name}"]`);
    let shown = hero && classes.length ? classes[0].index : null;

    /**
     * Drop classes from Coming up once they are over, or once they move into the next class card.
     *
     * @param {number} now
     * @param {Object|undefined} current The class in the card.
     */
    const prune = (now, current) => {
        if (!list) {
            return;
        }
        const ends = new Map(classes.map((item) => [item.index, item.end]));
        let left = 0;
        list.querySelectorAll('[data-index]').forEach((item) => {
            const index = Number(item.dataset.index);
            item.hidden = (ends.get(index) || 0) <= now || (hero !== null && (!current || index <= current.index));
            left += item.hidden ? 0 : 1;
        });
        const empty = root.querySelector('[data-region="classes-empty"]');
        if (empty) {
            empty.classList.toggle('d-none', left > 0);
        }
    };

    const tick = () => {
        const now = clock();
        const current = classes.find((item) => item.end > now);
        prune(now, current);
        if (!hero) {
            return Boolean(current);
        }

        if (!current) {
            // What is shown in each state is up to the styles (is-empty, is-live, is-soon).
            hero.classList.add('is-empty');
            hero.classList.remove('is-live', 'is-soon');
            return false;
        }

        if (current.index !== shown) {
            // The class ended: show the following one.
            shown = current.index;
            field('name').textContent = current.name;
            field('course').textContent = current.course;
            field('day').textContent = current.day;
            field('range').textContent = current.range;
            field('startsat').textContent = current.startsat;
            hero.querySelector('[data-link="url"]').href = current.url;
            hero.querySelector('[data-link="joinurl"]').href = current.joinurl;
        }

        const live = current.start <= now;
        const left = Math.max(0, current.start - now);
        hero.classList.toggle('is-live', live);
        hero.classList.toggle('is-soon', !live && left <= SOON);
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
        if (!document.body.contains(root) || !tick()) {
            clearInterval(timer);
        }
    }, 1000);
};
