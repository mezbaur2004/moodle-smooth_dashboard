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
 * Smooth dashboard block.
 *
 * @package    block_smooth_dashboard
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_smooth_dashboard\local\content;
use block_smooth_dashboard\output\dashboard;

/**
 * A student's day at a glance on the Dashboard: the next Zoom class with a live countdown, the
 * classes after it, quizzes and assignments due soon, course progress with what is left to do,
 * Zoom attendance and leaderboard places, across every course they are in.
 */
class block_smooth_dashboard extends block_base {
    /**
     * Set the title.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_smooth_dashboard');
    }

    /**
     * The Dashboard only: the block spans every course of the user.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['my' => true, 'site-index' => false, 'course-view' => false, 'mod' => false];
    }

    /**
     * One per page.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * The block has site settings.
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * The greeting is the heading, so the block's own header is only shown while editing.
     *
     * @return bool
     */
    public function hide_header() {
        return !$this->page->user_is_editing();
    }

    /**
     * Build the content.
     *
     * @return stdClass
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = (object) ['text' => '', 'footer' => ''];
        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }
        $data = content::build();
        if (content::is_empty($data)) {
            return $this->content;
        }
        $this->content->text = $this->page->get_renderer('block_smooth_dashboard')->render(new dashboard($data));
        return $this->content;
    }
}
