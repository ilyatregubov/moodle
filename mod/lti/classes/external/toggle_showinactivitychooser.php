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

namespace mod_lti\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use mod_lti\local\ltiopenid\registration_helper;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/lti/locallib.php');

/**
 * External function to toggle showinactivitychooser setting.
 *
 * @package    mod_lti
 * @copyright  2023 Ilya Tregubov <ilya.a.tregubov@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class toggle_showinactivitychooser extends external_api {

    /**
     * Get parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'tooltypeid' => new external_value(PARAM_INT, 'Tool type ID'),
            'coursevisible' => new external_value(PARAM_BOOL, 'Show in activity chooser'),
        ]);
    }

    /**
     * Toggles showinactivitychooser setting.
     *
     * @param int $tooltypeid the id of the course external tool type.
     * @param bool $coursevisible Show in activity chooser setting.
     * @return bool true
     */
    public static function execute(int $tooltypeid, bool $coursevisible): bool {

        [
            'tooltypeid' => $tooltypeid,
            'coursevisible' => $coursevisible,
        ] = self::validate_parameters(self::execute_parameters(), [
            'tooltypeid' => $tooltypeid,
            'coursevisible' => $coursevisible,
            ]);

        global $DB;
        $course = (int) $DB->get_field('lti_types', 'course', ['id' => $tooltypeid]);
        $context = \context_course::instance($course);
        self::validate_context($context);
        require_capability('mod/lti:addcoursetool', $context);

        if ($coursevisible) {
            $coursevisible = LTI_COURSEVISIBLE_ACTIVITYCHOOSER;
        } else {
            $coursevisible = LTI_COURSEVISIBLE_PRECONFIGURED;
        }
        $record = $DB->get_record('lti_types', ['id' => $tooltypeid]);
        $record->coursevisible = $coursevisible;

        $config = new \stdClass();
        $config->lti_coursevisible = $coursevisible;

        if (intval($record->course) !== 1) {
            // It is course tool - just update it.
            lti_update_type($record, $config);
        } else {
            // This is site tool, but we would like to have course level setting for it.
            $record = $DB->get_record('lti_coursevisible', ['typeid' => $tooltypeid, 'courseid' => $course]);
            if (!$record) {
                $record = new \stdClass();
                $record->typeid = $tooltypeid;
                $record->courseid = $course;
                $record->coursevisible = $coursevisible;
                $DB->insert_record('lti_coursevisible', $record);
            } else {
                $record->coursevisible = $coursevisible;
                $DB->update_record('lti_coursevisible', $record);
            }
        }

        return true;
    }

    /**
     * Get service returns definition.
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_BOOL, 'Success');
    }
}
