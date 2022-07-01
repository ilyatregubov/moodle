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
 * Unit tests for the enrol_fee's payment subsystem callback implementation.
 *
 * @package    enrol_fee
 * @category   test
 * @copyright  2021 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_fee\payment;
use core\plugininfo\enrol;

/**
 * Unit tests for the enrol_fee's payment subsystem callback implementation.
 *
 * @coversDefaultClass \enrol_fee\payment\service_provider
 */
class service_provider_test extends \advanced_testcase {

    /**
     * Test for service_provider::get_payable().
     *
     * @covers ::get_payable
     */
    public function test_get_payable() {
        global $DB;
        $this->resetAfterTest();

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $feeplugin = enrol_get_plugin('fee');
        $generator = $this->getDataGenerator();
        $account = $generator->get_plugin_generator('core_payment')->create_payment_account(['gateways' => 'paypal']);
        $course = $generator->create_course();

        $data = [
            'courseid' => $course->id,
            'customint1' => $account->get('id'),
            'cost' => 250,
            'currency' => 'USD',
            'roleid' => $studentrole->id,
        ];
        $id = $feeplugin->add_instance($course, $data);

        $payable = service_provider::get_payable('fee', $id);

        $this->assertEquals($account->get('id'), $payable->get_account_id());
        $this->assertEquals(250, $payable->get_amount());
        $this->assertEquals('USD', $payable->get_currency());
    }

    /**
     * Test for service_provider::get_success_url().
     *
     * @covers ::get_success_url
     */
    public function test_get_success_url() {
        global $CFG, $DB;
        $this->resetAfterTest();

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $feeplugin = enrol_get_plugin('fee');
        $generator = $this->getDataGenerator();
        $account = $generator->get_plugin_generator('core_payment')->create_payment_account(['gateways' => 'paypal']);
        $course = $generator->create_course();

        $data = [
            'courseid' => $course->id,
            'customint1' => $account->get('id'),
            'cost' => 250,
            'currency' => 'USD',
            'roleid' => $studentrole->id,
        ];
        $id = $feeplugin->add_instance($course, $data);

        $successurl = service_provider::get_success_url('fee', $id);
        $this->assertEquals(
            $CFG->wwwroot . '/course/view.php?id=' . $course->id,
            $successurl->out(false)
        );
    }

    /**
     * Test for service_provider::deliver_order().
     *
     * @covers ::deliver_order
     */
    public function test_deliver_order() {
        global $DB;
        $this->resetAfterTest();

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $feeplugin = enrol_get_plugin('fee');
        $generator = $this->getDataGenerator();
        $account = $generator->get_plugin_generator('core_payment')->create_payment_account(['gateways' => 'paypal']);
        $course = $generator->create_course();
        $context = \context_course::instance($course->id);
        $user = $generator->create_user();

        $data = [
            'courseid' => $course->id,
            'customint1' => $account->get('id'),
            'cost' => 250,
            'currency' => 'USD',
            'roleid' => $studentrole->id,
        ];
        $id = $feeplugin->add_instance($course, $data);

        $paymentid = $generator->get_plugin_generator('core_payment')->create_payment([
            'accountid' => $account->get('id'),
            'amount' => 10,
            'userid' => $user->id
        ]);

        service_provider::deliver_order('fee', $id, $paymentid, $user->id);
        $this->assertTrue(is_enrolled($context, $user));
        $this->assertTrue(user_has_role_assignment($user->id, $studentrole->id, $context->id));
    }

    /**
     * Test the behaviour of fill_enrol_custom_fields().
     *
     * @covers ::fill_enrol_custom_fields
     */
    public function test_fill_enrol_custom_fields() {
        $this->resetAfterTest();

        $feeplugin = enrol_get_plugin('fee');

        $cat = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id, 'shortname' => 'ANON']);

        $generator = $this->getDataGenerator();
        $account = $generator->get_plugin_generator('core_payment')->create_payment_account(['gateways' => 'paypal']);

        $enrolmentdata['paymentaccount'] = $account->get('name');
        $enrolmentdata = $feeplugin->fill_enrol_custom_fields($enrolmentdata, $course->id);
        $this->assertEquals($account->get('id'), $enrolmentdata['customint1']);

        $enrolmentdata = [];
        $enrolmentdata['paymentaccount'] = 'notexist';
        $enrolmentdata = $feeplugin->fill_enrol_custom_fields($enrolmentdata, $course->id);
        $this->assertArrayNotHasKey('customint1', $enrolmentdata);

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

        $generator = $this->getDataGenerator();
        $account = $generator->get_plugin_generator('core_payment')->create_payment_account(['gateways' => 'paypal']);

        enrol::enable_plugin('fee', false);

        $feeplugin = enrol_get_plugin('fee');

        // Plugin is disabled in system and payment account name is missing in csv.
        $enrolmentdata = [];
        $errors = $feeplugin->validate_enrol_plugin_data($enrolmentdata);
        $this->assertArrayHasKey('plugindisabled', $errors);
        $this->assertArrayHasKey('missingmandatoryfields', $errors);

        enrol::enable_plugin('fee', true);

        // Unknown account name and missing currency.
        $enrolmentdata['paymentaccount'] = 'test';
        $enrolmentdata['cost'] = 9000;
        $errors = $feeplugin->validate_enrol_plugin_data($enrolmentdata);
        $this->assertArrayHasKey('errorpaymentaccount', $errors);
        $this->assertArrayHasKey('missingmandatoryfields', $errors);

        // Missing cost.
        $enrolmentdata['paymentaccount'] = $account->get('name');
        $enrolmentdata['currency'] = 'AU';
        unset($enrolmentdata['cost']);
        $errors = $feeplugin->validate_enrol_plugin_data($enrolmentdata);
        $this->assertArrayHasKey('missingmandatoryfields', $errors);

        // Wrong currency or fee.
        $enrolmentdata['currency'] = 'tugrik';
        $enrolmentdata['cost'] = 'text';
        $errors = $feeplugin->validate_enrol_plugin_data($enrolmentdata, $course->id);
        $this->assertArrayHasKey('costerror', $errors);
        $this->assertArrayHasKey('errorcurrency', $errors);

        // Wrong enrol period, start or end date format.
        $enrolmentdata['startdate'] = 'abc';
        $enrolmentdata['enddate'] = 'cde';
        $enrolmentdata['enrolperiod'] = 'fgh';
        $errors = $feeplugin->validate_enrol_plugin_data($enrolmentdata, $course->id);
        $this->assertArrayHasKey('errorenrolstartdateformat', $errors);
        $this->assertArrayHasKey('errorenrolenddateformat', $errors);
        $this->assertArrayHasKey('errorenrolperiodformat', $errors);

        // Enrol start date is after enrol end date.
        $enrolmentdata['startdate'] = '17 July 2023';
        $enrolmentdata['enddate'] = '16 July 2023';
        $errors = $feeplugin->validate_enrol_plugin_data($enrolmentdata, $course->id);
        $this->assertArrayHasKey('errorenrolenddate', $errors);

        // Valid data.
        $enrolmentdata['currency'] = 'AUD';
        $enrolmentdata['cost'] = 9000;
        $enrolmentdata['enddate'] = '19 July 2023';
        $enrolmentdata['enrolperiod'] = '1 day';
        $errors = $feeplugin->validate_enrol_plugin_data($enrolmentdata, $course->id);
        $this->assertEmpty($errors);
    }

}
