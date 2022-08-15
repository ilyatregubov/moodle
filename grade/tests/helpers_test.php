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
 * Unit tests for the class component_gradeitem.
 *
 * @package   core_grades
 * @category  test
 * @copyright 2022 Ilya Tregubov <ilya@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

namespace core_grades;

use assign;
use cm_info;
use core_grades\local\helpers\helpers;
use grade_item;

/**
 * Unit tests for grade helper functions
 *
 * @package   core_grades
 * @category  test
 * @copyright 2022 Ilya Tregubov <ilya@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class helpers_test extends \advanced_testcase {

    /**
     * Tests that ungraded_counts calculates count and sum of grades correctly when there are graded users.
     *
     * @covers \core_grades\local\helpers\helpers::ungraded_counts
     */
    public function test_ungraded_counts_count_sumgrades() {
        global $DB;

        $this->resetAfterTest(true);

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();

        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);

        // Custom roles (gradable and non gradable).
        $gradeblerole = create_role('New student role', 'gradable',
            'Gradable role', 'student');
        $nongradeblerole = create_role('New student role', 'nongradable',
            'Non gradable role', 'student');

        // Set up gradable roles.
        set_config('gradebookroles', $studentrole->id . ',' . $gradeblerole);

        // Create users.

        // These will be gradable users.
        $student1 = $this->getDataGenerator()->create_user(['username' => 'student1']);
        $student2 = $this->getDataGenerator()->create_user(['username' => 'student2']);
        $student3 = $this->getDataGenerator()->create_user(['username' => 'student3']);
        $student5 = $this->getDataGenerator()->create_user(['username' => 'student5']);

        // These will be non-gradable users.
        $student4 = $this->getDataGenerator()->create_user(['username' => 'student4']);
        $student6 = $this->getDataGenerator()->create_user(['username' => 'student6']);
        $teacher = $this->getDataGenerator()->create_user(['username' => 'teacher']);

        // Enrol students.
        $this->getDataGenerator()->enrol_user($student1->id, $course1->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($student2->id, $course1->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($student3->id, $course1->id, $gradeblerole);

        $this->getDataGenerator()->enrol_user($student5->id, $course1->id, $nongradeblerole);
        $this->getDataGenerator()->enrol_user($student6->id, $course1->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($teacher->id, $course1->id, $teacherrole->id);

        // User that is enrolled in a different course.
        $this->getDataGenerator()->enrol_user($student4->id, $course2->id, $studentrole->id);

        // Mark user as deleted.
        $student6->deleted = 1;
        $DB->update_record('user', $student6);

        // Create grade items in course 1.
        $assign1 = $this->getDataGenerator()->create_module('assign', ['course' => $course1->id]);
        $assign2 = $this->getDataGenerator()->create_module('assign', ['course' => $course1->id]);
        $quiz1 = $this->getDataGenerator()->create_module('quiz', ['course' => $course1->id]);

        $manuaitem = new \grade_item($this->getDataGenerator()->create_grade_item([
            'itemname'        => 'Grade item1',
            'idnumber'        => 'git1',
            'courseid'        => $course1->id,
        ]));

        // Create grade items in course 2.
        $assign3 = $this->getDataGenerator()->create_module('assign', ['course' => $course2->id]);

        // Grade users in first course.
        $cm = cm_info::create(get_coursemodule_from_instance('assign', $assign1->id));
        $assigninstance = new assign($cm->context, $cm, $course1);
        $grade = $assigninstance->get_user_grade($student1->id, true);
        $grade->grade = 40;
        $assigninstance->update_grade($grade);

        $cm = cm_info::create(get_coursemodule_from_instance('assign', $assign2->id));
        $assigninstance = new assign($cm->context, $cm, $course1);
        $grade = $assigninstance->get_user_grade($student3->id, true);
        $grade->grade = 50;
        $assigninstance->update_grade($grade);

        // Override grade for assignment in gradebook.
        $gi = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'iteminstance' => $cm->instance,
            'courseid' => $course1->id
        ]);
        $gi->update_final_grade($student3->id, 55);

        // Grade user in second course.
        $cm = cm_info::create(get_coursemodule_from_instance('assign', $assign3->id));
        $assigninstance = new assign($cm->context, $cm, $course2);
        $grade = $assigninstance->get_user_grade($student4->id, true);
        $grade->grade = 40;
        $assigninstance->update_grade($grade);

        $manuaitem->update_final_grade($student1->id, 1);
        $manuaitem->update_final_grade($student3->id, 2);

        // Trigger a regrade.
        grade_force_full_regrading($course1->id);
        grade_force_full_regrading($course2->id);
        grade_regrade_final_grades($course1->id);
        grade_regrade_final_grades($course2->id);

        $ungradedcounts = [];
        $ungradedcounts[$course1->id] = helpers::ungraded_counts($course1->id);
        $ungradedcounts[$course2->id] = helpers::ungraded_counts($course2->id);

        foreach ($ungradedcounts as $key => $ungradedcount) {
            $gradeitems = grade_item::fetch_all(['courseid' => $key]);
            if ($key == $course1->id) {
                $gradeitemkeys = array_keys($gradeitems);
                $ungradedcountskeys = array_keys($ungradedcount['ungradedcounts']);

                // For each grade item there is some student that is not graded yet in course 1.
                $this->assertEmpty(array_diff_key($gradeitemkeys, $ungradedcountskeys));

                // Only quiz does not have any grades, the remaning 4 grade items should have some.
                // We can do more and match by gradeitem id numbers. But feels like overengeneering.
                $this->assertEquals(4, sizeof($ungradedcount['sumarray']));
            } else {

                // In course 2 there is one student, and he is graded.
                $this->assertEmpty($ungradedcount['ungradedcounts']);

                // There are 2 grade items and they both have some grades.
                $this->assertEquals(2, sizeof($ungradedcount['sumarray']));
            }

            foreach ($gradeitems as $gradeitem) {
                $sumgrades = null;
                if (array_key_exists($gradeitem->id, $ungradedcount['ungradedcounts'])) {
                    $ungradeditem = $ungradedcount['ungradedcounts'][$gradeitem->id];
                    if ($gradeitem->itemtype === 'course') {
                        $this->assertEquals(1, $ungradeditem->count);
                    } else if ($gradeitem->itemmodule === 'assign') {
                        $this->assertEquals(2, $ungradeditem->count);
                    } else if ($gradeitem->itemmodule === 'quiz') {
                        $this->assertEquals(3, $ungradeditem->count);
                    } else if ($gradeitem->itemtype === 'manual') {
                        $this->assertEquals(1, $ungradeditem->count);
                    }
                }

                if (array_key_exists($gradeitem->id, $ungradedcount['sumarray'])) {
                    $sumgrades = $ungradedcount['sumarray'][$gradeitem->id];
                    if ($gradeitem->itemtype === 'course') {
                        if ($key == $course1->id) {
                            $this->assertEquals('98.00000', $sumgrades); // 40 + 55 + 1 + 2
                        } else {
                            $this->assertEquals('40.00000', $sumgrades);
                        }
                    } else if ($gradeitem->itemmodule === 'assign') {
                        if (($gradeitem->itemname === $assign1->name) || ($gradeitem->itemname === $assign3->name)) {
                            $this->assertEquals('40.00000', $sumgrades);
                        } else {
                            $this->assertEquals('55.00000', $sumgrades);
                        }
                    } else if ($gradeitem->itemtype === 'manual') {
                        $this->assertEquals('3.00000', $sumgrades);
                    }
                }
            }
        }
    }

    /**
     * Tests that ungraded_counts calculates count and sum of grades correctly for groups when there are graded users.
     *
     * @covers \core_grades\local\helpers\helpers::ungraded_counts
     */
    public function test_ungraded_count_sumgrades_groups() {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();

        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

        // Create users.

        $student1 = $this->getDataGenerator()->create_user(['username' => 'student1']);
        $student2 = $this->getDataGenerator()->create_user(['username' => 'student2']);
        $student3 = $this->getDataGenerator()->create_user(['username' => 'student3']);

        // Enrol students.
        $this->getDataGenerator()->enrol_user($student1->id, $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($student2->id, $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($student3->id, $course->id, $studentrole->id);

        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_group_member(['userid' => $student1->id, 'groupid' => $group1->id]);
        $this->getDataGenerator()->create_group_member(['userid' => $student2->id, 'groupid' => $group2->id]);
        $this->getDataGenerator()->create_group_member(['userid' => $student3->id, 'groupid' => $group2->id]);
        $DB->set_field('course', 'groupmode', SEPARATEGROUPS, ['id' => $course->id]);

        // Create grade items in course 1.
        $assign1 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $assign2 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $quiz1 = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $manuaitem = new \grade_item($this->getDataGenerator()->create_grade_item([
            'itemname'        => 'Grade item1',
            'idnumber'        => 'git1',
            'courseid'        => $course->id,
        ]));

        // Grade users in first course.
        $cm = cm_info::create(get_coursemodule_from_instance('assign', $assign1->id));
        $assigninstance = new assign($cm->context, $cm, $course);
        $grade = $assigninstance->get_user_grade($student1->id, true);
        $grade->grade = 40;
        $assigninstance->update_grade($grade);

        $cm = cm_info::create(get_coursemodule_from_instance('assign', $assign2->id));
        $assigninstance = new assign($cm->context, $cm, $course);
        $grade = $assigninstance->get_user_grade($student3->id, true);
        $grade->grade = 50;
        $assigninstance->update_grade($grade);

        $manuaitem->update_final_grade($student1->id, 1);
        $manuaitem->update_final_grade($student3->id, 2);

        // Trigger a regrade.
        grade_force_full_regrading($course->id);
        grade_regrade_final_grades($course->id);

        $ungradedcounts = [];
        $ungradedcounts[$group1->id] = helpers::ungraded_counts($course->id, $group1->id);
        $ungradedcounts[$group2->id] = helpers::ungraded_counts($course->id, $group2->id);

        $gradeitems = grade_item::fetch_all(['courseid' => $course->id]);

        // In group1 there is 1 student and assign1 and quiz1 are not graded for him.
        $this->assertEquals(2, sizeof($ungradedcounts[$group1->id]['ungradedcounts']));

        // In group1 manual grade item, assign1 and course total have some grades.
        $this->assertEquals(3, sizeof($ungradedcounts[$group1->id]['sumarray']));

        // In group2 student2 has no grades at all so all 5 grade items should present.
        $this->assertEquals(5, sizeof($ungradedcounts[$group2->id]['ungradedcounts']));

        // In group2 manual grade item, assign2 and course total have some grades.
        $this->assertEquals(3, sizeof($ungradedcounts[$group2->id]['sumarray']));

        foreach ($gradeitems as $gradeitem) {
            $sumgrades = null;

            foreach ($ungradedcounts as $key => $ungradedcount) {
                if (array_key_exists($gradeitem->id, $ungradedcount['ungradedcounts'])) {
                    $ungradeditem = $ungradedcount['ungradedcounts'][$gradeitem->id];
                    if ($key == $group1->id) {
                        // Both assign2 and quiz1 are not graded for student1.
                        $this->assertEquals(1, $ungradeditem->count);
                    } else {
                        if ($gradeitem->itemtype === 'course') {
                            $this->assertEquals(1, $ungradeditem->count);
                        } else if ($gradeitem->itemmodule === 'assign') {
                            if ($gradeitem->itemname === $assign1->name) {
                                // In group2 assign1 is not graded for anyone.
                                $this->assertEquals(2, $ungradeditem->count);
                            } else {
                                // In group2 assign2 is graded for student3.
                                $this->assertEquals(1, $ungradeditem->count);
                            }
                        } else if ($gradeitem->itemmodule === 'quiz') {
                            $this->assertEquals(2, $ungradeditem->count);
                        } else if ($gradeitem->itemtype === 'manual') {
                            $this->assertEquals(1, $ungradeditem->count);
                        }
                    }
                }

                if (array_key_exists($gradeitem->id, $ungradedcount['sumarray'])) {
                    $sumgrades = $ungradedcount['sumarray'][$gradeitem->id];
                    if ($key == $group1->id) {
                        if ($gradeitem->itemtype === 'course') {
                            $this->assertEquals('41.00000', $sumgrades);
                        } else if ($gradeitem->itemmodule === 'assign') {
                            $this->assertEquals('40.00000', $sumgrades);
                        } else if ($gradeitem->itemtype === 'manual') {
                            $this->assertEquals('1.00000', $sumgrades);
                        }
                    } else {
                        if ($gradeitem->itemtype === 'course') {
                            $this->assertEquals('52.00000', $sumgrades);
                        } else if ($gradeitem->itemmodule === 'assign') {
                            $this->assertEquals('50.00000', $sumgrades);
                        } else if ($gradeitem->itemtype === 'manual') {
                            $this->assertEquals('2.00000', $sumgrades);
                        }
                    }
                }
            }
        }
    }

    /**
     * Tests for calculate_average.
     * @dataProvider calculate_average_data()
     * @param int $meanselection Whether to inlcude all grades or non-empty grades in aggregation.
     * @param array $expectedmeancount expected meancount value
     * @param array $expectedaverage expceted average value
     *
     * @covers \core_grades\local\helpers\helpers::calculate_average
     */
    public function test_calculate_average(int $meanselection, array $expectedmeancount, array $expectedaverage) {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();

        $student1 = $this->getDataGenerator()->create_user(['username' => 'student1']);
        $student2 = $this->getDataGenerator()->create_user(['username' => 'student2']);
        $student3 = $this->getDataGenerator()->create_user(['username' => 'student3']);

        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

        // Enrol students.
        $this->getDataGenerator()->enrol_user($student1->id, $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($student2->id, $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($student3->id, $course->id, $studentrole->id);

        // Create activities.
        $assign1 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $assign2 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $quiz1 = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        // Grade users.
        $cm = cm_info::create(get_coursemodule_from_instance('assign', $assign1->id));
        $assigninstance = new assign($cm->context, $cm, $course);
        $grade = $assigninstance->get_user_grade($student1->id, true);
        $grade->grade = 40;
        $assigninstance->update_grade($grade);

        $grade = $assigninstance->get_user_grade($student2->id, true);
        $grade->grade = 30;
        $assigninstance->update_grade($grade);

        $cm = cm_info::create(get_coursemodule_from_instance('assign', $assign2->id));
        $assigninstance = new assign($cm->context, $cm, $course);
        $grade = $assigninstance->get_user_grade($student3->id, true);
        $grade->grade = 50;
        $assigninstance->update_grade($grade);

        $grade = $assigninstance->get_user_grade($student1->id, true);
        $grade->grade = 100;
        $assigninstance->update_grade($grade);

        // Make a manual grade items.
        $manuaitem = new \grade_item($this->getDataGenerator()->create_grade_item([
            'itemname'        => 'Grade item1',
            'idnumber'        => 'git1',
            'courseid'        => $course->id,
        ]));
        $manuaitem->update_final_grade($student1->id, 1);
        $manuaitem->update_final_grade($student3->id, 2);

        $ungradedcounts = helpers::ungraded_counts($course->id);
        $ungradedcounts['report']['meanselection'] = $meanselection;

        $gradeitems = grade_item::fetch_all(['courseid' => $course->id]);

        foreach ($gradeitems as $gradeitem) {
            $name = $gradeitem->itemname . ' ' . $gradeitem->itemtype;
            $aggr = helpers::calculate_average($gradeitem, $ungradedcounts);

            $this->assertEquals($expectedmeancount[$name], $aggr['meancount']);
            $this->assertEquals($expectedaverage[$name], $aggr['average']);
        }
    }

    /**
     * Data provider for test_calculate_average
     *
     * @return array of testing scenarios
     */
    public function calculate_average_data() : array {
        return [
            'Non-empty grades' => [
                'meanselection' => 1,
                'expectedmeancount' => [' course' => 3, 'Assignment 1 mod' => 2, 'Assignment 2 mod' => 2,
                    'Quiz 1 mod' => 0, 'Grade item1 manual' => 2],
                'expectedaverage' => [' course' => 73.333333333333, 'Assignment 1 mod' => 35.0,
                    'Assignment 2 mod' => 75.0, 'Quiz 1 mod' => null, 'Grade item1 manual' => 1.5],
            ],
            'All grades' => [
                'meanselection' => 0,
                'expectedmeancount' => [' course' => 3, 'Assignment 1 mod' => 3, 'Assignment 2 mod' => 3,
                    'Quiz 1 mod' => 3, 'Grade item1 manual' => 3],
                'expectedaverage' => [' course' => 73.333333333333, 'Assignment 1 mod' => 23.333333333333332,
                    'Assignment 2 mod' => 50.0, 'Quiz 1 mod' => null, 'Grade item1 manual' => 1.0],
            ],
        ];
    }

    /**
     * Tests for item types.
     *
     * @covers \core_grades\local\helpers\helpers::item_types
     */
    public function test_item_types() {
        $this->resetAfterTest(true);

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();

        // Create activities.
        $this->getDataGenerator()->create_module('assign', ['course' => $course1->id]);
        $this->getDataGenerator()->create_module('assign', ['course' => $course1->id]);
        $this->getDataGenerator()->create_module('quiz', ['course' => $course1->id]);

        $this->getDataGenerator()->create_module('assign', ['course' => $course2->id]);

        // Create manual grade items.
        new \grade_item($this->getDataGenerator()->create_grade_item([
            'itemname'        => 'Grade item1',
            'idnumber'        => 'git1',
            'courseid'        => $course1->id,
        ]));

        new \grade_item($this->getDataGenerator()->create_grade_item([
            'itemname'        => 'Grade item2',
            'idnumber'        => 'git2',
            'courseid'        => $course2->id,
        ]));

        // Create a grade category (it should not be fetched by item_types).
        new \grade_category($this->getDataGenerator()
            ->create_grade_category(['courseid' => $course1->id]), false);

        $gradeitems1 = helpers::item_types($course1->id);
        $gradeitems2 = helpers::item_types($course2->id);

        $this->assertEquals(3, sizeof($gradeitems1));
        $this->assertEquals(2, sizeof($gradeitems2));

        $this->assertArrayHasKey('assign', $gradeitems1);
        $this->assertArrayHasKey('quiz', $gradeitems1);
        $this->assertArrayHasKey('manual', $gradeitems1);

        $this->assertArrayHasKey('assign', $gradeitems2);
        $this->assertArrayHasKey('manual', $gradeitems2);
    }
}
