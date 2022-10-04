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
 * Base lib class for singleview functionality.
 *
 * @package   gradereport_singleview
 * @copyright 2014 Moodle Pty Ltd (http://moodle.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/grade/report/lib.php');

/**
 * This class is the main class that must be implemented by a grade report plugin.
 *
 * @package   gradereport_singleview
 * @copyright 2014 Moodle Pty Ltd (http://moodle.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradereport_singleview extends grade_report {

    /**
     * Return the list of valid screens, used to validate the input.
     *
     * @return array List of screens.
     */
    public static function valid_screens() {
        // This is a list of all the known classes representing a screen in this plugin.
        return array('user', 'select', 'grade');
    }

    /**
     * Process data from a form submission. Delegated to the current screen.
     *
     * @param array $data The data from the form
     * @return array List of warnings
     */
    public function process_data($data) {
        if (has_capability('moodle/grade:edit', $this->context)) {
            return $this->screen->process($data);
        }
    }

    /**
     * Unused - abstract function declared in the parent class.
     *
     * @param string $target
     * @param string $action
     */
    public function process_action($target, $action) {
    }

    /**
     * Constructor for this report. Creates the appropriate screen class based on itemtype.
     *
     * @param int $courseid The course id.
     * @param object $gpr grade plugin return tracking object
     * @param context_course $context
     * @param string $itemtype Should be user, select or grade
     * @param int $itemid The id of the user or grade item
     * @param string $unused Used to be group id but that was removed and this is now unused.
     */
    public function __construct($courseid, $gpr, $context, $itemtype, $itemid, $unused = null) {
        parent::__construct($courseid, $gpr, $context);

        $base = '/grade/report/singleview/index.php';

        $idparams = array('id' => $courseid);

        $this->baseurl = new moodle_url($base, $idparams);

        $this->pbarurl = new moodle_url($base, $idparams + array(
                'item' => $itemtype,
                'itemid' => $itemid
            ));

        //  The setup_group method is used to validate group mode and permissions and define the currentgroup value.
        $this->setup_groups();

        $screenclass = "\\gradereport_singleview\\local\\screen\\${itemtype}";

        $this->screen = new $screenclass($courseid, $itemid, $this->currentgroup);

        // Load custom or predifined js.
        $this->screen->js();
    }

    /**
     * Build the html for the screen.
     * @return string HTML to display
     */
    public function output() {
        global $OUTPUT;
        return $OUTPUT->container($this->screen->html(), 'reporttable');
    }

    protected function setup_groups() {
        parent::setup_groups();
        $this->group_selector = static::groups_course_menu($this->course, $this->pbarurl);
    }

    /**
     * Ideally we should move this function to the base class and call it from the setup_groups in the base class,
     * so all reports would automatically use it.
     *
     * @param stdClass $course
     * @param moodle_url $urlroot
     * @return string
     */
    protected static function groups_course_menu(stdClass $course, moodle_url $urlroot) {
        global $USER, $OUTPUT;

        $groupmode = $course->groupmode;
        if (!$groupmode) {
            return '';
        }

        $context = context_course::instance($course->id);
        $aag = has_capability('moodle/site:accessallgroups', $context);

        $usergroups = [];
        if ($groupmode == VISIBLEGROUPS or $aag) {
            $allowedgroups = groups_get_all_groups($course->id, 0, $course->defaultgroupingid);
            // Get user's own groups and put to the top.
            $usergroups = groups_get_all_groups($course->id, $USER->id, $course->defaultgroupingid);
        } else {
            $allowedgroups = groups_get_all_groups($course->id, $USER->id, $course->defaultgroupingid);
        }

        $activegroup = groups_get_course_group($course, true, $allowedgroups);

        $groupsmenu = [];
        if (!$allowedgroups or $groupmode == VISIBLEGROUPS or $aag) {
            $groupsmenu[0] = get_string('allparticipants');
        }

        $groupsmenu += groups_sort_menu_options($allowedgroups, $usergroups);

        if ($groupmode == VISIBLEGROUPS) {
            $grouplabel = get_string('groupsvisible');
        } else {
            $grouplabel = get_string('groupsseparate');
        }

        if ($aag and $course->defaultgroupingid) {
            if ($grouping = groups_get_grouping($course->defaultgroupingid)) {
                $grouplabel = $grouplabel . ' (' . format_string($grouping->name) . ')';
            }
        }

        if (count($groupsmenu) == 1) {
            $groupname = reset($groupsmenu);
            $output = $grouplabel.': '.$groupname;
        } else {
            $select = new \core\output\select_menu('group', $groupsmenu, $activegroup);
            $select->set_label($grouplabel);
            $output = $OUTPUT->render($select);
        }

        return $output;
    }

    /**
     * Adds bulk actions menu.
     *
     * @param renderer_base $output
     * @return string HTML to display
     */
    public function bulk_actions_menu(renderer_base $output) : string {
        $options = [
            'overrideallgrades' => get_string('overrideallgrades', 'gradereport_singleview'),
            'overridenonegrades' => get_string('overridenonegrades', 'gradereport_singleview'),
            'excludeallgrades' => get_string('excludeallgrades', 'gradereport_singleview'),
            'excludenonegrades' => get_string('excludenonegrades', 'gradereport_singleview'),
            'bulklegend' => get_string('bulklegend', 'gradereport_singleview')
        ];

        $menu = new \action_menu();
        $menu->set_menu_trigger(get_string('actions'));

        foreach ($options as $type => $option) {
            $action = new \action_menu_link_secondary(new \moodle_url('#'), null, $option,
                ['data-role' => $type]);
            $menu->add($action);
        }
        $menu->attributes['class'] .= ' float-left mr-1';

        return $output->render($menu);
    }

}

