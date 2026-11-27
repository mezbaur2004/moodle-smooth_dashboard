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
 * Upgrade steps.
 *
 * @package    block_smooth_dashboard
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the block.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_smooth_dashboard_upgrade($oldversion) {
    if ($oldversion < 2026112700) {
        // Overdue items used to stay listed for 7 days by default, so work two weeks late showed
        // "All caught up". Sites still on that default get the new one.
        if ((string) get_config('block_smooth_dashboard', 'overduedays') === '7') {
            set_config('overduedays', 30, 'block_smooth_dashboard');
        }
        upgrade_block_savepoint(true, 2026112700, 'smooth_dashboard');
    }
    return true;
}
