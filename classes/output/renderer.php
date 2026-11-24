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
 * Renderer for block_smooth_dashboard.
 *
 * @package    block_smooth_dashboard
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_smooth_dashboard\output;

use core\output\plugin_renderer_base;

/**
 * Renders the dashboard with block_smooth_dashboard/main.
 */
class renderer extends plugin_renderer_base {
    /**
     * The dashboard.
     *
     * @param dashboard $dashboard
     * @return string HTML.
     */
    protected function render_dashboard(dashboard $dashboard): string {
        return $this->render_from_template('block_smooth_dashboard/main', $dashboard->export_for_template($this));
    }
}
