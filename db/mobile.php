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
 * Moodle app support for block_smooth_dashboard.
 *
 * The block shows inline on the app's Dashboard, like on the website. After changing this
 * file, purge Moodle's caches; app users may need to log out and in again.
 *
 * @package    block_smooth_dashboard
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'block_smooth_dashboard' => [
        'handlers' => [
            'smooth_dashboard' => [
                'delegate' => 'CoreBlockDelegate',
                'method' => 'mobile_block_view',
                'displaydata' => [
                    'title' => 'pluginname',
                    'class' => 'block_smooth_dashboard',
                    'type' => 'template',
                ],
            ],
        ],
        'lang' => [
            ['pluginname', 'block_smooth_dashboard'],
        ],
    ],
];
