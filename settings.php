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
 * Site settings for block_smooth_dashboard.
 *
 * @package    block_smooth_dashboard
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $plugin = 'block_smooth_dashboard';

    // Sections.
    $settings->add(new admin_setting_heading(
        "$plugin/sectionsheading",
        get_string('settings_sections', $plugin),
        get_string('settings_sections_desc', $plugin)
    ));
    foreach (['greeting', 'nextclass', 'classes', 'deadlines', 'courses', 'leaderboard', 'attendance'] as $section) {
        $settings->add(new admin_setting_configcheckbox(
            "$plugin/show$section",
            get_string("show$section", $plugin),
            get_string("show{$section}_desc", $plugin),
            1
        ));
    }

    // Appearance.
    $settings->add(new admin_setting_heading("$plugin/appearanceheading", get_string('appearance', $plugin), ''));
    $settings->add(new admin_setting_configcolourpicker(
        "$plugin/accent",
        get_string('accent', $plugin),
        get_string('accent_desc', $plugin),
        ''
    ));
    $settings->add(new admin_setting_configcolourpicker(
        "$plugin/accent2",
        get_string('accent2', $plugin),
        get_string('accent2_desc', $plugin),
        ''
    ));

    // Classes.
    $settings->add(new admin_setting_heading("$plugin/classesheading", get_string('classes', $plugin), ''));
    $settings->add(new admin_setting_configtext(
        "$plugin/classescount",
        get_string('classescount', $plugin),
        get_string('classescount_desc', $plugin),
        5,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        "$plugin/classesdays",
        get_string('classesdays', $plugin),
        get_string('classesdays_desc', $plugin),
        14,
        PARAM_INT
    ));

    // Due soon.
    $settings->add(new admin_setting_heading("$plugin/deadlinesheading", get_string('deadlines', $plugin), ''));
    $choices = [];
    foreach (core_component::get_plugin_list('mod') as $modname => $unused) {
        if (get_string_manager()->string_exists('modulename', $modname)) {
            $choices[$modname] = get_string('modulename', $modname);
        }
    }
    core_collator::asort($choices);
    $settings->add(new admin_setting_configmulticheckbox(
        "$plugin/deadlinemodules",
        get_string('deadlinemodules', $plugin),
        get_string('deadlinemodules_desc', $plugin),
        ['assign' => 1, 'quiz' => 1],
        $choices
    ));
    $settings->add(new admin_setting_configtext(
        "$plugin/deadlinesdays",
        get_string('deadlinesdays', $plugin),
        get_string('deadlinesdays_desc', $plugin),
        14,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        "$plugin/overduedays",
        get_string('overduedays', $plugin),
        get_string('overduedays_desc', $plugin),
        7,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        "$plugin/deadlinescount",
        get_string('deadlinescount', $plugin),
        get_string('deadlinescount_desc', $plugin),
        6,
        PARAM_INT
    ));

    // My courses.
    $settings->add(new admin_setting_heading("$plugin/coursesheading", get_string('courses', $plugin), ''));
    $settings->add(new admin_setting_configtext(
        "$plugin/coursescount",
        get_string('coursescount', $plugin),
        get_string('coursescount_desc', $plugin),
        6,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        "$plugin/todocount",
        get_string('todocount', $plugin),
        get_string('todocount_desc', $plugin),
        3,
        PARAM_INT
    ));
}
