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
 * Task log.
 *
 * @package    admin
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once("{$CFG->libdir}/adminlib.php");
require_once($CFG->dirroot.'/grade/lib.php');

use core_grades\local\systemreports\summary;
use core_reportbuilder\system_report_factory;

$courseid      = required_param('id', PARAM_INT);        // course id

if (!$course = $DB->get_record('course', ['id' => $courseid])) {
    print_error('invalidcourseid');
}
require_login($course);
$context = context_course::instance($course->id);

$PAGE->set_url('/grade/report/summary.php', ['id' => $courseid]);

$PAGE->set_context($context);

$PAGE->set_pagelayout('report');
//$PAGE->set_title($course->fullname);
//$PAGE->set_heading($course->fullname);

//admin_externalpage_setup('tasklogs');

print_grade_page_head($courseid, 'report', false, 'test', false, false);

$logid = optional_param('logid', null, PARAM_INT);
$download = optional_param('download', false, PARAM_BOOL);
$filter = optional_param('filter', null, PARAM_TEXT);

//echo $OUTPUT->header();
//$report = system_report_factory::create(summary::class, context_system::instance());
$report = system_report_factory::create(summary::class, context_course::instance($courseid));

if (!empty($filter)) {
    $report->set_filter_values([
        'task_log:name_operator' => \core_reportbuilder\local\filters\text::IS_EQUAL_TO,
        'task_log:name_value' => $filter,
    ]);
}

echo $report->output();
echo $OUTPUT->footer();
