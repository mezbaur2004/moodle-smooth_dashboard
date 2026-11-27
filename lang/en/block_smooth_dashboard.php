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
 * English strings for block_smooth_dashboard.
 *
 * @package    block_smooth_dashboard
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accent'] = 'Accent colour';
$string['accent_desc'] = 'Main colour of the dashboard: the next class card, buttons and progress bars. Leave empty to use the theme\'s primary colour.';
$string['accent2'] = 'Second accent colour';
$string['accent2_desc'] = 'The next class card fades from the accent colour to this one. Leave empty for violet.';
$string['actioncount'] = '{$a->action} ({$a->count})';
$string['allcaughtup'] = 'All caught up';
$string['allcourses'] = 'All courses';
$string['allcoursescount'] = 'All {$a} courses';
$string['alldone'] = 'All done';
$string['andmore'] = 'and {$a} more';
$string['appearance'] = 'Appearance';
$string['attendancevalue'] = '{$a}% attendance';
$string['calendar'] = 'Calendar';
$string['chipclass'] = '1 class today';
$string['chipclasses'] = '{$a} classes today';
$string['chipclear'] = 'Nothing due today';
$string['chipdue'] = '1 due soon';
$string['chipdues'] = '{$a} due soon';
$string['classes'] = 'Classes';
$string['classescount'] = 'Classes listed';
$string['classescount_desc'] = 'How many classes Coming up lists after the next one.';
$string['classesdays'] = 'Classes ahead (days)';
$string['classesdays_desc'] = 'How far ahead to look for classes.';
$string['classrooms'] = 'Your classes';
$string['classrooms_help'] = 'These classes have no fixed time in Moodle. Join at your usual class time.';
$string['comingup'] = 'Coming up';
$string['continue'] = 'Continue';
$string['courses'] = 'My courses';
$string['coursescount'] = 'Courses shown';
$string['coursescount_desc'] = 'How many courses in progress get a card, the most recently visited first.';
$string['daytime'] = '{$a->day}, {$a->time}';
$string['deadlinemodules'] = 'Activity types';
$string['deadlinemodules_desc'] = 'Which activities Due soon lists. Their dates come from the calendar, with each student\'s extensions and overrides applied.';
$string['deadlines'] = 'Due soon';
$string['deadlinescount'] = 'Items listed';
$string['deadlinescount_desc'] = 'How many items Due soon lists at most.';
$string['deadlinesdays'] = 'Due soon ahead (days)';
$string['deadlinesdays_desc'] = 'How far ahead to look for due dates.';
$string['duesoon'] = 'Due soon';
$string['durationhours'] = '{$a->hours} h {$a->minutes} min';
$string['durationminutes'] = '{$a} min';
$string['endsin'] = 'Ends in';
$string['eventclose'] = 'Closes {$a->day}, {$a->time}';
$string['eventdue'] = 'Due {$a->day}, {$a->time}';
$string['eventextended'] = 'Extended to {$a->day}, {$a->time}';
$string['eventgradingdue'] = 'Grading due {$a->day}, {$a->time}';
$string['eventopen'] = 'Opens {$a->day}, {$a->time}';
$string['eventopened'] = 'Opened {$a->day}, {$a->time}';
$string['eventother'] = '{$a->day}, {$a->time}';
$string['eventtograde'] = 'Waiting since {$a->day}, {$a->time}';
$string['greetingafternoon'] = 'Good afternoon, {$a}';
$string['greetingevening'] = 'Good evening, {$a}';
$string['greetingmorning'] = 'Good morning, {$a}';
$string['joinclass'] = 'Join class';
$string['joinnow'] = 'Join now';
$string['leaderboards'] = 'Leaderboards';
$string['livenow'] = 'Live now';
$string['low'] = 'Low';
$string['mycourses'] = 'My courses';
$string['nextclass'] = 'Next class';
$string['noclasses'] = 'No classes scheduled';
$string['noclasses_help'] = 'Your next Zoom class will show here, with a countdown.';
$string['nocourses'] = 'No courses in progress.';
$string['nodeadlines'] = 'No quizzes or assignments due soon.';
$string['nomoreclasses'] = 'No other classes scheduled.';
$string['notopenyet'] = 'Not open yet';
$string['opencourse'] = 'Open course';
$string['overduedays'] = 'Overdue kept (days)';
$string['overduedays_desc'] = 'How long overdue items stay listed, as long as they can still be submitted. 0 hides overdue items.';
$string['percentcomplete'] = '{$a}% complete';
$string['pluginname'] = 'Smooth dashboard';
$string['points'] = '{$a} pts';
$string['privacy:metadata'] = 'The Smooth dashboard block only shows data that other plugins store, and stores no personal data of its own.';
$string['progress'] = 'Course progress';
$string['rankvalue'] = '#{$a->rank} of {$a->total}';
$string['rankyou'] = 'You: #{$a->rank} of {$a->total} · {$a->points} pts';
$string['settings_sections'] = 'Sections';
$string['settings_sections_desc'] = 'Each section can be switched off for the whole site. Sections with nothing to show stay out of the way.';
$string['showattendance'] = 'Zoom attendance';
$string['showattendance_desc'] = 'Each course card shows the student\'s own Zoom attendance (course overall), coloured by the Zoom attendance thresholds. Needs local_zoomattendance and My courses.';
$string['showclasses'] = 'Coming up';
$string['showclasses_desc'] = 'The Zoom classes after the next one.';
$string['showcourses'] = 'My courses';
$string['showcourses_desc'] = 'Cards for courses in progress: progress, what is left to do and a button to carry on. Progress needs completion tracking in the course.';
$string['showdeadlines'] = 'Due soon';
$string['showdeadlines_desc'] = 'Quizzes and assignments still to do, overdue ones first.';
$string['showgreeting'] = 'Greeting';
$string['showgreeting_desc'] = 'A greeting by name, today\'s date and a one-line summary of the day.';
$string['showleaderboard'] = 'Leaderboards';
$string['showleaderboard_desc'] = 'The top 3 and the student\'s own place for each course with a leaderboard. Needs mod_leaderboard; its name and anonymous rules apply.';
$string['shownextclass'] = 'Next class';
$string['shownextclass_desc'] = 'The next Zoom class with a live countdown, "Live now" while it runs, and a Join button.';
$string['smooth_dashboard:addinstance'] = 'Add a new Smooth dashboard block';
$string['smooth_dashboard:myaddinstance'] = 'Add a new Smooth dashboard block to the Dashboard';
$string['startingsoon'] = 'Starting soon';
$string['startsat'] = 'Starts {$a}';
$string['startsin'] = 'Starts in';
$string['statusindays'] = 'In {$a} days';
$string['statusopen'] = 'Open now';
$string['statusoverdue'] = 'Overdue';
$string['statustoday'] = 'Today';
$string['statustograde'] = 'To grade';
$string['statustomorrow'] = 'Tomorrow';
$string['strftimeshortday'] = '%a, %d %b';
$string['timerange'] = '{$a->start} – {$a->end}';
$string['today'] = 'Today';
$string['today_insentence'] = 'today';
$string['todaysummary'] = 'Today at a glance';
$string['todocount'] = 'Activities listed per course';
$string['todocount_desc'] = 'How many activities left to complete each course card lists under Up next. 0 lists none.';
$string['todomany'] = '{$a} to do';
$string['todoone'] = '1 to do';
$string['tomorrow'] = 'Tomorrow';
$string['tomorrow_insentence'] = 'tomorrow';
$string['tryagain'] = 'Try again';
$string['unitdays'] = 'days';
$string['unitdaysshort'] = 'd';
$string['unithours'] = 'hours';
$string['unitminutes'] = 'min';
$string['unitseconds'] = 'sec';
$string['upnext'] = 'Up next';
$string['viewall'] = 'View all';
$string['yesterday'] = 'Yesterday';
$string['yesterday_insentence'] = 'yesterday';
