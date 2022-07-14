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

declare(strict_types=1);

namespace core_grades\local\helpers;

use grade_item;
use grade_plugin_return;
use grade_report_grader;

require_once($CFG->dirroot.'/grade/report/grader/lib.php');
require_once($CFG->dirroot.'/grade/lib.php');

/**
 * Helper class for column aggregation related methods
 *
 * @package     core_grades
 * @copyright   2022 Ilya Tregubov <ilya@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class average extends grade_report_grader {

    /**
     * Calculate average grade for a given grade item.
     *
     * @param int $gradeitemid
     * @param array $ungradedcounts Ungraded grade items counts.
     * @return string Average grade.
     */
    public static function calculate_average(int $gradeitemid, array $ungradedcounts): string {
        global $DB;

        $gradeitem = grade_item::fetch(array('id' => $gradeitemid));
        $courseid = $gradeitem->courseid;
        $context = \context_course::instance($courseid);

        $course = get_course($courseid);

        $gpr = new grade_plugin_return(
            [
                'type' => 'report',
                'plugin' => 'grader',
                'course' => $course,
            ]
        );

        $report1 = new grade_report_grader($course->id, $gpr, $context);

        $averagesdisplaytype = $report1->get_pref('averagesdisplaytype');
        $averagesdecimalpoints = $report1->get_pref('averagesdecimalpoints');
        $meanselection = $report1->get_pref('meanselection');
        $shownumberofgrades = $report1->get_pref('shownumberofgrades');

        $groupsql = $report1->groupsql;
        $groupwheresql = $report1->groupwheresql;
        $totalcount = $report1->get_numusers(false);

        // We want to query both the current context and parent contexts.
        list($relatedctxsql, $relatedctxparams) = $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'relatedctx');

        // Limit to users with a gradeable role ie students.
        list($gradebookrolessql, $gradebookrolesparams) = $DB->get_in_or_equal(explode(',', $report1->gradebookroles), SQL_PARAMS_NAMED, 'grbr0');

        // Limit to users with an active enrolment.
        $defaultgradeshowactiveenrol = !empty($CFG->grade_report_showonlyactiveenrol);
        $showonlyactiveenrol = get_user_preferences('grade_report_showonlyactiveenrol', $defaultgradeshowactiveenrol);
        $showonlyactiveenrol = $showonlyactiveenrol || !has_capability('moodle/course:viewsuspendedusers', $context);
        list($enrolledsql, $enrolledparams) = get_enrolled_sql($context, '', 0, $showonlyactiveenrol);

        $params = array_merge($report1->groupwheresql_params, $gradebookrolesparams, $enrolledparams, $relatedctxparams);
        $params['courseid'] = $course->id;

        // find sums of all grade items in course
        $sql = "SELECT gg.itemid, SUM(gg.finalgrade) AS sum
                      FROM {grade_items} gi
                      JOIN {grade_grades} gg ON gg.itemid = gi.id
                      JOIN {user} u ON u.id = gg.userid
                      JOIN ($enrolledsql) je ON je.id = gg.userid
                      JOIN (
                                   SELECT DISTINCT ra.userid
                                     FROM {role_assignments} ra
                                    WHERE ra.roleid $gradebookrolessql
                                      AND ra.contextid $relatedctxsql
                           ) rainner ON rainner.userid = u.id
                      $groupsql
                     WHERE gi.courseid = :courseid
                       AND u.deleted = 0
                       AND gg.finalgrade IS NOT NULL
                       AND gg.hidden = 0
                       $groupwheresql
                  GROUP BY gg.itemid";

        $sum_array = array();
        $sums = $DB->get_recordset_sql($sql, $params);
        foreach ($sums as $itemid => $csum) {
            $sum_array[$itemid] = $csum->sum;
        }
        $sums->close();

        /*            if (!empty($gradeitem->avg)) {
                        continue;
                    }*/

        if ($gradeitem->needsupdate) {
            return get_string('error'); // MORE SEPCIFIC ERROR NEEDED!!!!!!!!!!!!!!!
        }

        if (empty($sum_array[$gradeitemid])) {
            $sum_array[$gradeitemid] = 0;
        }

        if (empty($ungradedcounts[$gradeitemid])) {
            $ungradedcounts = 0;
        } else {
            $ungradedcounts = $ungradedcounts[$gradeitemid]->count;
        }

        //do they want the averages to include all grade items
        if ($meanselection == GRADE_REPORT_MEAN_GRADED) {
            $mean_count = $totalcount - $ungradedcounts;
        } else { // Bump up the sum by the number of ungraded items * grademin
            $sum_array[$gradeitemid] += ($ungradedcounts * $gradeitem->grademin);
            $mean_count = $totalcount;
        }

        // Determine which display type to use for this average
        if ($averagesdisplaytype == GRADE_REPORT_PREFERENCE_INHERIT) { // no ==0 here, please resave the report and user preferences
            $displaytype = $gradeitem->get_displaytype();

        } else {
            $displaytype = $averagesdisplaytype;
        }

        // Override grade_item setting if a display preference (not inherit) was set for the averages
        if ($averagesdecimalpoints == GRADE_REPORT_PREFERENCE_INHERIT) {
            $decimalpoints = $gradeitem->get_decimals();
        } else {
            $decimalpoints = $averagesdecimalpoints;
        }

        if (empty($sum_array[$gradeitemid]) || $mean_count == 0) {
            $average = '-';
        } else {
            $sum = $sum_array[$gradeitemid];
            $avgradeval = $sum / $mean_count;
            $gradehtml = grade_format_gradevalue($avgradeval, $gradeitem, true, $displaytype, $decimalpoints);

            $numberofgrades = '';
            if ($shownumberofgrades) {
                $numberofgrades = " ($mean_count)";
            }

            $average = $gradehtml . $numberofgrades;
        }
        return $average;
    }

    /**
     * Get ungraded grade items info for a course.
     *
     * @param int $courseid Course ID
     * @return array Ungraded grade items counts.
     */
    public static function ungraded_counts(int $courseid): array {
        global $DB;

        $course = get_course($courseid);
        $context = \context_course::instance($courseid);

        $gpr = new grade_plugin_return(
            [
                'type' => 'report',
                'plugin' => 'grader',
                'course' => $course,
            ]
        );

        $report = new grade_report_grader($course->id, $gpr, $context);

        // We want to query both the current context and parent contexts.
        list($relatedctxsql, $relatedctxparams) =
            $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'relatedctx');

        // Limit to users with a gradeable role ie students.
        list($gradebookrolessql, $gradebookrolesparams) =
            $DB->get_in_or_equal(explode(',', $report->gradebookroles), SQL_PARAMS_NAMED, 'grbr0');

        // Limit to users with an active enrolment.
        $defaultgradeshowactiveenrol = !empty($CFG->grade_report_showonlyactiveenrol);
        $showonlyactiveenrol = get_user_preferences('grade_report_showonlyactiveenrol', $defaultgradeshowactiveenrol);
        $showonlyactiveenrol = $showonlyactiveenrol || !has_capability('moodle/course:viewsuspendedusers', $context);
        list($enrolledsql, $enrolledparams) = get_enrolled_sql($context, '', 0, $showonlyactiveenrol); // THIS IS CALLED 2 times!!!!!!

        $params = array_merge($report->groupwheresql_params, $gradebookrolesparams, $enrolledparams, $relatedctxparams);
        $params['courseid'] = $course->id;

        // find sums of all grade items in course
        $sql = "SELECT gg.itemid, SUM(gg.finalgrade) AS sum
                      FROM {grade_items} gi
                      JOIN {grade_grades} gg ON gg.itemid = gi.id
                      JOIN {user} u ON u.id = gg.userid
                      JOIN ($enrolledsql) je ON je.id = gg.userid
                      JOIN (
                                   SELECT DISTINCT ra.userid
                                     FROM {role_assignments} ra
                                    WHERE ra.roleid $gradebookrolessql
                                      AND ra.contextid $relatedctxsql
                           ) rainner ON rainner.userid = u.id
                      $report->groupsql
                     WHERE gi.courseid = :courseid
                       AND u.deleted = 0
                       AND gg.finalgrade IS NOT NULL
                       AND gg.hidden = 0
                       $report->groupwheresql
                  GROUP BY gg.itemid";

        $sum_array = [];
        $sums = $DB->get_recordset_sql($sql, $params);
        foreach ($sums as $itemid => $csum) {
            $sum_array[$itemid] = $csum->sum;
        }
        $sums->close();

        // Empty grades must be evaluated as grademin, NOT always 0
        // This query returns a count of ungraded grades (NULL finalgrade OR no matching record in grade_grades table)
        // No join condition when joining grade_items and user to get a grade item row for every user
        // Then left join with grade_grades and look for rows with null final grade (which includes grade items with no grade_grade)
        $sql = "SELECT gi.id, COUNT(u.id) AS count
                      FROM {grade_items} gi
                      JOIN {user} u ON u.deleted = 0
                      JOIN ($enrolledsql) je ON je.id = u.id
                      JOIN (
                               SELECT DISTINCT ra.userid
                                 FROM {role_assignments} ra
                                WHERE ra.roleid $gradebookrolessql
                                  AND ra.contextid $relatedctxsql
                           ) rainner ON rainner.userid = u.id
                      LEFT JOIN {grade_grades} gg
                             ON (gg.itemid = gi.id AND gg.userid = u.id AND gg.finalgrade IS NOT NULL AND gg.hidden = 0)
                      $report->groupsql
                     WHERE gi.courseid = :courseid
                           AND gg.finalgrade IS NULL
                           $report->groupwheresql
                  GROUP BY gi.id";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get grade item types in a course.
     *
     * @param int $courseid Course ID
     * @return array Item types.
     */
    public static function item_types(int $courseid): array {
        global $DB, $CFG;

        $sql = "(SELECT gi.itemmodule
                   FROM {grade_items} gi
                   WHERE gi.courseid = :courseid1
                   AND gi.itemmodule IS NOT NULL)
                   UNION
                (SELECT gi1.itemtype
                   FROM {grade_items} gi1
                  WHERE gi1.courseid = :courseid2
                    AND gi1.itemtype = 'manual')";

        $itemtypes = $DB->get_records_sql($sql, ['courseid1' => $courseid, 'courseid2' => $courseid]);
        foreach ($itemtypes as $itemtype => $value) {
            if (file_exists("$CFG->dirroot/mod/$itemtype/lib.php")) {
                $modnames[$itemtype] = get_string("modulename", "$itemtype", null, true);
            } else if ($itemtype == 'manual') {
                $modnames[$itemtype] = get_string('manualitem', 'grades', null, true);
            }
        }

        return $modnames;
    }

}
