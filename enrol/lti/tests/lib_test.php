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
 * Tests for the enrol_lti_plugin class.
 *
 * @package enrol_lti
 * @copyright 2016 Jun Pataleta <jun@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_lti;

use auth_plugin_lti;
use core\plugininfo\enrol;
use core_date;
use course_enrolment_manager;
use enrol_lti_plugin;
use IMSGlobal\LTI\ToolProvider\ResourceLink;
use IMSGlobal\LTI\ToolProvider\ToolConsumer;
use IMSGlobal\LTI\ToolProvider\ToolProvider;
use IMSGlobal\LTI\ToolProvider\User;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/local/ltiadvantage/lti_advantage_testcase.php');

/**
 * Tests for the enrol_lti_plugin class.
 *
 * @package enrol_lti
 * @copyright 2016 Jun Pataleta <jun@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lib_test extends \lti_advantage_testcase {

    /**
     * Test set up.
     *
     * This is executed before running any tests in this file.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Test for enrol_lti_plugin::delete_instance().
     */
    public function test_delete_instance() {
        global $DB;

        // Create tool enrolment instance.
        $data = new \stdClass();
        $data->enrolstartdate = time();
        $data->secret = 'secret';
        $tool = $this->getDataGenerator()->create_lti_tool($data);

        // Create consumer and related data.
        $dataconnector = new data_connector();
        $consumer = new ToolConsumer('testkey', $dataconnector);
        $consumer->secret = $tool->secret;
        $consumer->ltiVersion = ToolProvider::LTI_VERSION1;
        $consumer->name = 'TEST CONSUMER NAME';
        $consumer->consumerName = 'TEST CONSUMER INSTANCE NAME';
        $consumer->consumerGuid = 'TEST CONSUMER INSTANCE GUID';
        $consumer->consumerVersion = 'TEST CONSUMER INFO VERSION';
        $consumer->enabled = true;
        $consumer->protected = true;
        $consumer->save();

        $resourcelink = ResourceLink::fromConsumer($consumer, 'testresourcelinkid');
        $resourcelink->save();

        $ltiuser = User::fromResourceLink($resourcelink, '');
        $ltiuser->ltiResultSourcedId = 'testLtiResultSourcedId';
        $ltiuser->ltiUserId = 'testuserid';
        $ltiuser->email = 'user1@example.com';
        $ltiuser->save();

        $tp = new tool_provider($tool->id);
        $tp->user = $ltiuser;
        $tp->resourceLink = $resourcelink;
        $tp->consumer = $consumer;
        $tp->map_tool_to_consumer();

        $mappingparams = [
            'toolid' => $tool->id,
            'consumerid' => $tp->consumer->getRecordId()
        ];

        // Check first that the related records exist.
        $this->assertTrue($DB->record_exists('enrol_lti_tool_consumer_map', $mappingparams));
        $this->assertTrue($DB->record_exists('enrol_lti_lti2_consumer', [ 'id' => $consumer->getRecordId() ]));
        $this->assertTrue($DB->record_exists('enrol_lti_lti2_resource_link', [ 'id' => $resourcelink->getRecordId() ]));
        $this->assertTrue($DB->record_exists('enrol_lti_lti2_user_result', [ 'id' => $ltiuser->getRecordId() ]));

        // Perform deletion.
        $enrollti = new enrol_lti_plugin();
        $instance = $DB->get_record('enrol', ['id' => $tool->enrolid]);
        $enrollti->delete_instance($instance);

        // Check that the related records have been deleted.
        $this->assertFalse($DB->record_exists('enrol_lti_tool_consumer_map', $mappingparams));
        $this->assertFalse($DB->record_exists('enrol_lti_lti2_consumer', [ 'id' => $consumer->getRecordId() ]));
        $this->assertFalse($DB->record_exists('enrol_lti_lti2_resource_link', [ 'id' => $resourcelink->getRecordId() ]));
        $this->assertFalse($DB->record_exists('enrol_lti_lti2_user_result', [ 'id' => $ltiuser->getRecordId() ]));

        // Check that the enrolled users and the tool instance has been deleted.
        $this->assertFalse($DB->record_exists('enrol_lti_users', [ 'toolid' => $tool->id ]));
        $this->assertFalse($DB->record_exists('enrol_lti_tools', [ 'id' => $tool->id ]));
        $this->assertFalse($DB->record_exists('enrol', [ 'id' => $instance->id ]));
    }

    /**
     * Test confirming that relevant data is removed after enrol instance removal.
     *
     * @covers \enrol_lti_plugin::delete_instance
     */
    public function test_delete_instance_lti_advantage() {
        global $DB;
        // Setup.
        [
            $course,
            $modresource,
            $modresource2,
            $courseresource,
            $registration,
            $deployment
        ] = $this->create_test_environment();

        // Launch the tool.
        $mockuser = $this->get_mock_launch_users_with_ids(['1p3_1'])[0];
        $mocklaunch = $this->get_mock_launch($modresource, $mockuser);
        $instructoruser = $this->getDataGenerator()->create_user();
        $launchservice = $this->get_tool_launch_service();
        $launchservice->user_launches_tool($instructoruser, $mocklaunch);

        // Verify data exists.
        $this->assertEquals(1, $DB->count_records('enrol_lti_user_resource_link'));
        $this->assertEquals(1, $DB->count_records('enrol_lti_resource_link'));
        $this->assertEquals(1, $DB->count_records('enrol_lti_app_registration'));
        $this->assertEquals(1, $DB->count_records('enrol_lti_deployment'));
        $this->assertEquals(1, $DB->count_records('enrol_lti_context'));
        $this->assertEquals(1, $DB->count_records('enrol_lti_users'));

        // Now delete the enrol instance.
        $enrollti = new enrol_lti_plugin();
        $instance = $DB->get_record('enrol', ['id' => $modresource->enrolid]);
        $enrollti->delete_instance($instance);

        $this->assertEquals(0, $DB->count_records('enrol_lti_user_resource_link'));
        $this->assertEquals(0, $DB->count_records('enrol_lti_resource_link'));
        $this->assertEquals(0, $DB->count_records('enrol_lti_users'));

        // App registration, Deployment and Context tables are not affected by instance removal.
        $this->assertEquals(1, $DB->count_records('enrol_lti_app_registration'));
        $this->assertEquals(1, $DB->count_records('enrol_lti_deployment'));
        $this->assertEquals(1, $DB->count_records('enrol_lti_context'));
    }

    /**
     * Test for getting user enrolment actions.
     */
    public function test_get_user_enrolment_actions() {
        global $CFG, $DB, $PAGE;
        $this->resetAfterTest();

        // Set page URL to prevent debugging messages.
        $PAGE->set_url('/enrol/editinstance.php');

        $pluginname = 'lti';

        // Only enable the lti enrol plugin.
        $CFG->enrol_plugins_enabled = $pluginname;

        $generator = $this->getDataGenerator();

        // Get the enrol plugin.
        $plugin = enrol_get_plugin($pluginname);

        // Create a course.
        $course = $generator->create_course();
        $context = \context_course::instance($course->id);
        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);

        // Enable this enrol plugin for the course.
        $fields = ['contextid' => $context->id, 'roleinstructor' => $teacherroleid, 'rolelearner' => $studentroleid];
        $plugin->add_instance($course, $fields);

        // Create a student.
        $student = $generator->create_user();
        // Enrol the student to the course.
        $generator->enrol_user($student->id, $course->id, 'student', $pluginname);

        // Teachers don't have enrol/lti:unenrol capability by default. Login as admin for simplicity.
        $this->setAdminUser();

        require_once($CFG->dirroot . '/enrol/locallib.php');
        $manager = new course_enrolment_manager($PAGE, $course);
        $userenrolments = $manager->get_user_enrolments($student->id);
        $this->assertCount(1, $userenrolments);

        $ue = reset($userenrolments);
        $actions = $plugin->get_user_enrolment_actions($manager, $ue);
        // LTI enrolment has 1 enrol actions for active users -- unenrol.
        $this->assertCount(1, $actions);
    }

    /**
     * Test the behaviour of an enrolment method when the activity to which it provides access is deleted.
     *
     * @covers \enrol_lti_pre_course_module_delete
     */
    public function test_course_module_deletion() {
        // Create two modules and publish them.
        $course = $this->getDataGenerator()->create_course();
        $mod = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $mod2 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $tooldata = [
            'cmid' => $mod->cmid,
            'courseid' => $course->id,
        ];
        $tool = $this->getDataGenerator()->create_lti_tool((object)$tooldata);
        $tooldata['cmid'] = $mod2->cmid;
        $tool2 = $this->getDataGenerator()->create_lti_tool((object)$tooldata);

        // Verify the instances are both enabled.
        $modinstance = helper::get_lti_tool($tool->id);
        $mod2instance = helper::get_lti_tool($tool2->id);
        $this->assertEquals(ENROL_INSTANCE_ENABLED, $modinstance->status);
        $this->assertEquals(ENROL_INSTANCE_ENABLED, $mod2instance->status);

        // Delete a module and verify the associated instance is disabled.
        course_delete_module($mod->cmid);
        $modinstance = helper::get_lti_tool($tool->id);
        $mod2instance = helper::get_lti_tool($tool2->id);
        $this->assertEquals(ENROL_INSTANCE_DISABLED, $modinstance->status);
        $this->assertEquals(ENROL_INSTANCE_ENABLED, $mod2instance->status);
    }

    /**
     * Test the behaviour of validate_enrol_plugin_data().
     *
     * @covers ::validate_enrol_plugin_data
     */
    public function test_validate_enrol_plugin_data() {
        $this->resetAfterTest();

        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id, 'shortname' => 'ANON']);

        enrol::enable_plugin('lti', false);

        $ltiplugin = enrol_get_plugin('lti');

        // Plugin is disabled in system.
        $enrolmentdata = [];
        $errors = $ltiplugin->validate_enrol_plugin_data($enrolmentdata);
        $this->assertArrayHasKey('plugindisabled', $errors);

        enrol::enable_plugin('lti', true);

        $enrolmentdata['ltiversion'] = 'test';
        $enrolmentdata['startdate'] = 'abc';
        $enrolmentdata['enddate'] = 'cde';
        $enrolmentdata['enrolperiod'] = 'fgh';
        $enrolmentdata['maxenrolled'] = 'abc';
        $enrolmentdata['provisioningmodeinstructor'] = false;
        $enrolmentdata['provisioningmodelearner'] = false;
        $enrolmentdata['membersyncmode'] = false;
        $enrolmentdata['maildisplay'] = false;
        $enrolmentdata['country'] = 'abc';
        $enrolmentdata['timezone'] = false; /// may be we need to do fields in validate, they are not realy custom

        $errors = $ltiplugin->validate_enrol_plugin_data($enrolmentdata);

        $this->assertArrayHasKey('unsupportedltiversion', $errors);
        $this->assertArrayHasKey('errorenrolstartdateformat', $errors);
        $this->assertArrayHasKey('errorenrolenddateformat', $errors);
        $this->assertArrayHasKey('errorenrolperiodformat', $errors);
        $this->assertArrayHasKey('errormaxenrolledformat', $errors);
        $this->assertArrayHasKey('errorprovisioningmodeteacherlaunch', $errors);
        $this->assertArrayHasKey('errorprovisioningmodelearner', $errors);
        $this->assertArrayHasKey('errormembersyncmode', $errors);
        $this->assertArrayHasKey('errormaildisplay', $errors);
        $this->assertArrayHasKey('errorcountry', $errors);
        $this->assertArrayHasKey('errortimezone', $errors);

        // Enrol start date is after enrol end date.
        $enrolmentdata['startdate'] = '17 July 2023';
        $enrolmentdata['enddate'] = '16 July 2023';
        $errors = $ltiplugin->validate_enrol_plugin_data($enrolmentdata, $course->id);
        $this->assertArrayHasKey('errorenrolenddate', $errors);

        // Valid data.
        $enrolmentdata['ltiversion'] = 'LTI-1p3';
        $enrolmentdata['enddate'] = '20 July 2023';
        $enrolmentdata['enrolperiod'] = '1 day';
        $enrolmentdata['maxenrolled'] = 20;
        $enrolmentdata['provisioningmodeinstructor'] = get_string('provisioningmodenewexisting', 'auth_lti');
        $enrolmentdata['provisioningmodelearner'] = get_string('provisioningmodeexistingonly', 'auth_lti');
        $enrolmentdata['membersyncmode'] = get_string('membersyncmodeenrolandunenrol', 'enrol_lti');
        $enrolmentdata['maildisplay'] = get_string('emaildisplayyes');
        $countries = get_string_manager()->get_list_of_countries();
        $enrolmentdata['country'] = reset($countries);
        $timezones = core_date::get_list_of_timezones(null, true);
        $enrolmentdata['timezone'] = reset($timezones);
        $errors = $ltiplugin->validate_enrol_plugin_data($enrolmentdata, $course->id);
        $this->assertEmpty($errors);
    }

    /**
     * Test the behaviour of fill_enrol_custom_fields().
     *
     * @covers ::fill_enrol_custom_fields
     */
    public function test_fill_enrol_custom_fields() {
        global $DB;

        $this->resetAfterTest();

        [
            $course,
            $modresource,
            $modresource2,
            $courseresource,
            $registration,
            $deployment
        ] = $this->create_test_environment();

        $ltiplugin = enrol_get_plugin('lti');

        // Check defaults are filled.
        $enrolmentdata = [];
        $coursecontext = \context_course::instance($course->id);
        $assignableroles = get_assignable_roles($coursecontext, ROLENAME_SHORT);
        $enrolmentdata = $ltiplugin->fill_enrol_custom_fields($enrolmentdata, $course->id, $assignableroles, []);
        $this->assertEquals('LTI-1p3', $enrolmentdata['ltiversion']);

        $context = \context_course::instance($course->id);
        $this->assertEquals($context->id, $enrolmentdata['contextid']);
        $this->assertEquals(3, $enrolmentdata['roleinstructor']);
        $this->assertEquals(5, $enrolmentdata['rolelearner']);
        $this->assertEquals(auth_plugin_lti::PROVISIONING_MODE_PROMPT_NEW_EXISTING, $enrolmentdata['provisioningmodeinstructor']);
        $this->assertEquals(auth_plugin_lti::PROVISIONING_MODE_AUTO_ONLY, $enrolmentdata['provisioningmodelearner']);
        $this->assertEquals(1, $enrolmentdata['gradesync']);
        $this->assertEquals(0, $enrolmentdata['gradesynccompletion']);
        $this->assertEquals(1, $enrolmentdata['membersync']);
        $this->assertEquals(helper::MEMBER_SYNC_ENROL_AND_UNENROL, $enrolmentdata['membersyncmode']);
        $this->assertEquals(get_config('enrol_lti', 'emaildisplay'), $enrolmentdata['maildisplay']);
        $this->assertEquals(get_config('enrol_lti', 'country'), $enrolmentdata['country']);
        $this->assertEquals(get_config('enrol_lti', 'timezone'), $enrolmentdata['timezone']);
        $this->assertEquals(get_config('enrol_lti', 'lang'), $enrolmentdata['lang']);
        $this->assertArrayNotHasKey('toolid', $enrolmentdata);
        $this->assertArrayNotHasKey('uuid', $enrolmentdata);

        $instance = $DB->get_record('enrol', ['id' => $modresource->enrolid]);

        $expected = [
            'ltiversion' => 'LTI-1p3',
            'tooltobeprovided' => 'Assignment 1',
            'roleinstructor' => 'teacher',
            'rolelearner' => 'teacher',
            'provisioningmodeteacherlaunch' => auth_plugin_lti::PROVISIONING_MODE_PROMPT_EXISTING_ONLY,
            'provisioningmodestudentlaunch' => auth_plugin_lti::PROVISIONING_MODE_PROMPT_NEW_EXISTING,
            'gradesync' => 0,
            'gradesynccompletion' => 1,
            'membersync' => 0,
            'membersyncmode' => helper::MEMBER_SYNC_ENROL_NEW,
            'maildisplay' => 1,
            'country' => 'GB',
            'timezone' => 'Europe/London',
            'lang' => 'fr',
        ];
        $enrolmentdata = $expected;
        $expected['roleinstructor'] = 4;
        $expected['rolelearner'] = 4;

        $enrolmentdata = $ltiplugin->fill_enrol_custom_fields($enrolmentdata, $course->id, $assignableroles, []);
        $this->assertEquals($expected, $enrolmentdata);

        $enrolmentdata['instanceid'] = $instance->id;
        $enrolmentdata = $ltiplugin->fill_enrol_custom_fields($enrolmentdata, $course->id, $assignableroles, []);
        $ltitool = $DB->get_record('enrol_lti_tools', ['enrolid' => $instance->id], '*', MUST_EXIST);
        $this->assertEquals($ltitool->id, $enrolmentdata['toolid']);
        $this->assertEquals($ltitool->uuid, $enrolmentdata['uuid']);
        $this->assertArrayNotHasKey('instanceid', $enrolmentdata);
    }

    /**
     * Test the behaviour of validate_plugin_data_context().
     *
     * @covers ::validate_plugin_data_context
     */
    public function test_validate_plugin_data_context() {
        $this->resetAfterTest();

        $cohortplugin = enrol_get_plugin('lti');

        [
            $course,
            $modresource,
            $modresource2,
            $courseresource,
            $registration,
            $deployment
        ] = $this->create_test_environment();

        // Create module with completion disabled.
        $data = $this->getDataGenerator()->create_module('data', ['course' => $course->id], ['completion' => 0]);

        $enrolmentdata = [];
        $enrolmentdata['tooltobeprovided'] = 'test';
        $enrolmentdata['requirecompletion'] = 1;
        $enrolmentdata['roleinstructor'] = false;
        $enrolmentdata['rolelearner'] = false;

        $errors = $cohortplugin->validate_plugin_data_context($enrolmentdata, $course->id);
        $this->assertArrayHasKey('errortooltobeprovided', $errors);
        $this->assertArrayNotHasKey('errorrequirecompletion', $errors);
        $this->assertArrayHasKey('errorroleinstructor', $errors);
        $this->assertArrayHasKey('errorrolelearner', $errors);

        $enrolmentdata['tooltobeprovided'] = $data->name;
        $enrolmentdata['roleinstructor'] = 3;
        $enrolmentdata['rolelearner'] = 5;

        $errors = $cohortplugin->validate_plugin_data_context($enrolmentdata, $course->id);
        $this->assertArrayNotHasKey('errortooltobeprovided', $errors);
        $this->assertArrayHasKey('errorrequirecompletion', $errors);
        $this->assertArrayNotHasKey('errorroleinstructor', $errors);
        $this->assertArrayNotHasKey('errorrolelearner', $errors);

        $enrolmentdata['tooltobeprovided'] = 'Assignment 1';
        $errors = $cohortplugin->validate_plugin_data_context($enrolmentdata, $course->id);
        $this->assertEmpty($errors);
    }

}
