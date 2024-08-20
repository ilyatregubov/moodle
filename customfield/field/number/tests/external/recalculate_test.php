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

namespace customfield_number\external;

use core_customfield_generator;
use core_external\external_api;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Unit tests for the customfield_number\external\recalculate.
 *
 * @package    customfield_number
 * @category   external
 * @copyright  2024 Ilya Tregubov <ilya.tregubov@proton.me>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \customfield_number\external\recalculate
 */
final class recalculate_test extends \externallib_advanced_testcase {

    /** @var stdClass  */
    private $course = null;
    /** @var \core_customfield\field_controller */
    private $field = null;

    /**
     * Tests set up.
     */
    public function setUp(): void {
        global $DB;
        parent::setUp();

        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();

        // Add teacher to a course.
        $roleids = $DB->get_records_menu('role', null, '', 'shortname, id');
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, $roleids['editingteacher']);

        $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id, 'name' => 'Assign1', 'visible' => 1]);
        $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id, 'name' => 'Assign2', 'visible' => 1]);
        $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id, 'name' => 'Assign3', 'visible' => 0]);

        $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id, 'name' => 'Quiz1', 'visible' => 1]);
        $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id, 'name' => 'Quiz2', 'visible' => 0]);

        $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id, 'name' => 'Forum1', 'visible' => 1]);

        /** @var core_customfield_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');

        $category = $generator->create_category();
        $this->field = $generator->create_field(
            [
                'categoryid' => $category->get('id'),
                'shortname' => 'myfield', 'type' => 'number',
                'configdata' => [
                    'fieldtype' => 'customfield_number\\local\\numberproviders\\nofactivities',
                    'activitytypes' => ['assign', 'forum'],
                ],
            ]
        );
        $this->setUser($teacher);
    }

    /**
     * Tests when teacher can not edit locked field.
     */
    public function test_execute_no_permission(): void {
        global $DB;

        $configdata = [
            'fieldtype' => 'customfield_number\\local\\numberproviders\\nofactivities',
            'activitytypes' => ['assign', 'forum'],
            'locked' => 1,
        ];
        $this->field->set('configdata', json_encode($configdata));
        $this->field->save();

        $context = \context_course::instance($this->course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $this->unassignUserCapability('moodle/course:changelockedcustomfields', $context->id, $roleid);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('update'));
        recalculate::execute($this->field->get('id'), (int)$this->course->id);
    }

    /**
     * Tests when all data is valid.
     */
    public function test_execute(): void {
        $result = recalculate::execute($this->field->get('id'), (int)$this->course->id);
        $result = external_api::clean_returnvalue(recalculate::execute_returns(), $result);
        $this->assertEquals(3.0, $result['value']);
    }

}
