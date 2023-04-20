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

namespace core_grades\form;

use context;
use context_course;
use core_form\dynamic_form;
use grade_category;
use grade_edit_tree;
use grade_helper;
use grade_item;
use grade_plugin_return;
use grade_scale;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/grade/edit/tree/lib.php');

/**
 * Prints the add category gradebook form
 *
 * @copyright 2023 Ilya Tregubov <ilya@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package core_grades
 */
class add_category extends dynamic_form {

    private function get_gradecategory(): array {
        $courseid = $this->optional_param('courseid', null, PARAM_INT);
        $id = $this->optional_param('category', null, PARAM_INT);

        if (!isset($id) || $id !== -1) {
            if (!$gradecategory = grade_category::fetch(['id' => $id, 'courseid' => $courseid])) {
                throw new \moodle_exception('invalidcategory');
            }
            $gradecategory->apply_forced_settings();
            $category = $gradecategory->get_record_data();
            // Set parent.
            $category->parentcategory = $gradecategory->parent;
            $gradeitem = $gradecategory->load_grade_item();
            // Normalize coef values if needed.
            $parentcategory = $gradecategory->get_parent_category();

            foreach ($gradeitem->get_record_data() as $key => $value) {
                $category->{"grade_item_$key"} = $value;
            }

            $decimalpoints = $gradeitem->get_decimals();

            $category->grade_item_grademax   = format_float($category->grade_item_grademax, $decimalpoints);
            $category->grade_item_grademin   = format_float($category->grade_item_grademin, $decimalpoints);
            $category->grade_item_gradepass  = format_float($category->grade_item_gradepass, $decimalpoints);
            $category->grade_item_multfactor = format_float($category->grade_item_multfactor, 4);
            $category->grade_item_plusfactor = format_float($category->grade_item_plusfactor, 4);
            $category->grade_item_aggregationcoef2 = format_float($category->grade_item_aggregationcoef2 * 100.0, 4);

            if (isset($parentcategory)) {
                if ($parentcategory->aggregation == GRADE_AGGREGATE_SUM ||
                    $parentcategory->aggregation == GRADE_AGGREGATE_WEIGHTED_MEAN2) {
                    $category->grade_item_aggregationcoef = $category->grade_item_aggregationcoef == 0 ? 0 : 1;
                } else {
                    $category->grade_item_aggregationcoef = format_float($category->grade_item_aggregationcoef, 4);
                }
            }
            // Check if the gradebook is frozen. This allows grades not be altered at all until a user verifies that they
            // wish to update the grades.
            $gradebookcalculationsfreeze = get_config('core', 'gradebook_calculations_freeze_' . $courseid);
            // Stick with the original code if the grade book is frozen.
            if ($gradebookcalculationsfreeze && (int)$gradebookcalculationsfreeze <= 20150627) {
                if ($category->aggregation == GRADE_AGGREGATE_SUM) {
                    // Input fields for grademin and grademax are disabled for the "Natural" category,
                    // this means they will be ignored if user does not change aggregation method.
                    // But if user does change aggregation method the default values should be used.
                    $category->grademax = 100;
                    $category->grade_item_grademax = 100;
                    $category->grademin = 0;
                    $category->grade_item_grademin = 0;
                }
            } else {
                if ($category->aggregation == GRADE_AGGREGATE_SUM && !$gradeitem->is_calculated()) {
                    // Input fields for grademin and grademax are disabled for the "Natural" category,
                    // this means they will be ignored if user does not change aggregation method.
                    // But if user does change aggregation method the default values should be used.
                    // This does not apply to calculated category totals.
                    $category->grademax = 100;
                    $category->grade_item_grademax = 100;
                    $category->grademin = 0;
                    $category->grade_item_grademin = 0;
                }
            }
        } else {
            $gradecategory = new grade_category(['courseid' => $courseid], false);
            $gradecategory->apply_default_settings();
            $gradecategory->apply_forced_settings();

            $category = $gradecategory->get_record_data();

            $gradeitem = new grade_item(['courseid' => $courseid, 'itemtype' => 'manual'], false);
            foreach ($gradeitem->get_record_data() as $key => $value) {
                $category->{"grade_item_$key"} = $value;
            }
        }

        return [
            'gradecategory' => $gradecategory,
            'item' => $category
        ];
    }

    protected function definition() {
        global $CFG;
        $courseid = $this->optional_param('courseid', null, PARAM_INT);
        $id = $this->optional_param('category', 0, PARAM_INT);
        $gpr = new grade_plugin_return();

        $mform =& $this->_form;

        $this->aggregation_options = grade_helper::get_aggregation_strings();

        $local = $this->get_gradecategory();
        $gradecategory = $local['gradecategory'];
        $category = $local['item'];

        // Hidden elements.
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT); // Do we need it? There is category?

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'category', $id);
        $mform->setType('category', PARAM_INT);

        $mform->addElement('hidden', 'itemtype', 'manual'); // All new items are manual only.
        $mform->setType('itemtype', PARAM_ALPHA);

        // Visible elements.
        $mform->addElement('header', 'headercategory', get_string('gradecategory', 'grades'));
        $mform->addElement('text', 'fullname', get_string('categoryname', 'grades'));
        $mform->setType('fullname', PARAM_TEXT);
        $mform->addRule('fullname', null, 'required', null, 'client');

        $mform->addElement('select', 'aggregation', get_string('aggregation', 'grades'), $this->aggregation_options);
        $mform->addHelpButton('aggregation', 'aggregation', 'grades');

        if ((int)$CFG->grade_aggregation_flag & 2) {
            $mform->setAdvanced('aggregation');
        }

        $mform->addElement('checkbox', 'aggregateonlygraded', get_string('aggregateonlygraded', 'grades'));
        $mform->addHelpButton('aggregateonlygraded', 'aggregateonlygraded', 'grades');

        if ((int)$CFG->grade_aggregateonlygraded_flag & 2) {
            $mform->setAdvanced('aggregateonlygraded');
        }

        if (empty($CFG->enableoutcomes)) {
            $mform->addElement('hidden', 'aggregateoutcomes');
            $mform->setType('aggregateoutcomes', PARAM_INT);
        } else {
            $mform->addElement('checkbox', 'aggregateoutcomes', get_string('aggregateoutcomes', 'grades'));
            $mform->addHelpButton('aggregateoutcomes', 'aggregateoutcomes', 'grades');
            if ((int)$CFG->grade_aggregateoutcomes_flag & 2) {
                $mform->setAdvanced('aggregateoutcomes');
            }
        }

        $mform->addElement('text', 'keephigh', get_string('keephigh', 'grades'), 'size="3"');
        $mform->setType('keephigh', PARAM_INT);
        $mform->addHelpButton('keephigh', 'keephigh', 'grades');
        if ((int)$CFG->grade_keephigh_flag & 2) {
            $mform->setAdvanced('keephigh');
        }

        $mform->addElement('text', 'droplow', get_string('droplow', 'grades'), 'size="3"');
        $mform->setType('droplow', PARAM_INT);
        $mform->addHelpButton('droplow', 'droplow', 'grades');
        $mform->hideIf('droplow', 'keephigh', 'noteq', 0);
        if ((int)$CFG->grade_droplow_flag & 2) {
            $mform->setAdvanced('droplow');
        }

        $mform->hideIf('keephigh', 'droplow', 'noteq', 0);
        $mform->hideIf('droplow', 'keephigh', 'noteq', 0);

        // Hiding.
        // advcheckbox is not compatible with disabledIf!
        $mform->addElement('checkbox', 'grade_item_hidden', get_string('hidden', 'grades'));
        $mform->addHelpButton('grade_item_hidden', 'hidden', 'grades');

        // Locking.
        $mform->addElement('checkbox', 'grade_item_locked', get_string('locked', 'grades'));
        $mform->addHelpButton('grade_item_locked', 'locked', 'grades');

        $mform->addElement('date_time_selector', 'grade_item_hiddenuntil',
            get_string('hiddenuntil', 'grades'), ['optional' => true]);
        $mform->hideIf('grade_item_hidden', 'grade_item_hiddenuntil[enabled]', 'checked');
        $mform->setAdvanced('grade_item_hiddenuntil');
        $mform->addElement('date_time_selector', 'grade_item_locktime',
            get_string('locktime', 'grades'), ['optional' => true]);
        $mform->hideIf('grade_item_locktime', 'grade_item_gradetype', 'eq', GRADE_TYPE_NONE);
        $mform->setAdvanced('grade_item_locktime');

        $mform->addElement('advcheckbox', 'grade_item_weightoverride', get_string('adjustedweight', 'grades'));
        $mform->addHelpButton('grade_item_weightoverride', 'weightoverride', 'grades');

        $mform->addElement('float', 'grade_item_aggregationcoef2', get_string('weight', 'grades'));
        $mform->addHelpButton('grade_item_aggregationcoef2', 'weight', 'grades');
        $mform->hideIf('grade_item_aggregationcoef2', 'grade_item_weightoverride');

        $options = [];
        $default = -1;
        $categories = grade_category::fetch_all(['courseid' => $courseid]);

        foreach ($categories as $cat) {
            $cat->apply_forced_settings();
            $options[$cat->id] = $cat->get_name();
            if ($cat->is_course_category()) {
                $default = $cat->id;
            }
        }

        if (count($categories) > 1) {
            $mform->addElement('select', 'parentcategory', get_string('parentcategory', 'grades'), $options);
            $mform->setDefault('parentcategory', $default);
            $mform->addElement('static', 'currentparentaggregation', get_string('currentparentaggregation', 'grades'));
        }

        // Grade item settings.
        // Displayed as Category total to avoid confusion between grade items requiring marking and category totals.
        $mform->addElement('header', 'categorytotal', get_string('categorytotal', 'grades'));
        $mform->setExpanded('categorytotal', false);

        $mform->addElement('text', 'grade_item_itemname', get_string('categorytotalname', 'grades'));
        $mform->setType('grade_item_itemname', PARAM_TEXT);
        $mform->setAdvanced('grade_item_itemname');

        $mform->addElement('text', 'grade_item_iteminfo', get_string('iteminfo', 'grades'));
        $mform->addHelpButton('grade_item_iteminfo', 'iteminfo', 'grades');
        $mform->setType('grade_item_iteminfo', PARAM_TEXT);

        $mform->addElement('text', 'grade_item_idnumber', get_string('idnumbermod'));
        $mform->addHelpButton('grade_item_idnumber', 'idnumbermod');
        $mform->setType('grade_item_idnumber', PARAM_RAW);

        if (!empty($category->id)) {
            $gradeitem = $gradecategory->load_grade_item();

            // If grades exist set a message so the user knows why they can not alter the grade type or scale.
            // We could never change the grade type for external items, so only need to show this for manual grade items.
            if ($gradeitem->has_overridden_grades()) {
                // Set a message so the user knows why the can not alter the grade type or scale.
                if ($gradeitem->gradetype == GRADE_TYPE_SCALE) {
                    $gradesexistmsg = get_string('modgradecategorycantchangegradetyporscalemsg', 'grades');
                } else {
                    $gradesexistmsg = get_string('modgradecategorycantchangegradetypemsg', 'grades');
                }
                $gradesexisthtml = '<div class=\'alert alert-warning\'>' . $gradesexistmsg . '</div>';
                $mform->addElement('static', 'gradesexistmsg', '', $gradesexisthtml);
            }
        }

        $options = [
            GRADE_TYPE_NONE => get_string('typenone', 'grades'),
            GRADE_TYPE_VALUE => get_string('typevalue', 'grades'),
            GRADE_TYPE_SCALE => get_string('typescale', 'grades'),
            GRADE_TYPE_TEXT => get_string('typetext', 'grades')
        ];

        $mform->addElement('select', 'grade_item_gradetype', get_string('gradetype', 'grades'), $options);
        $mform->addHelpButton('grade_item_gradetype', 'gradetype', 'grades');
        $mform->setDefault('grade_item_gradetype', GRADE_TYPE_VALUE);
        $mform->hideIf('grade_item_gradetype', 'aggregation', 'eq', GRADE_AGGREGATE_SUM);

        $options = [0 => get_string('usenoscale', 'grades')];
        if ($scales = grade_scale::fetch_all_local($courseid)) {
            foreach ($scales as $scale) {
                $options[$scale->id] = $scale->get_name();
            }
        }
        if ($scales = grade_scale::fetch_all_global()) {
            foreach ($scales as $scale) {
                $options[$scale->id] = $scale->get_name();
            }
        }
        // Ugly BC hack - it was possible to use custom scale from other courses :-(.
        if (!empty($category->grade_item_scaleid) && !isset($options[$category->grade_item_scaleid])) {
            if ($scale = grade_scale::fetch(['id' => $category->grade_item_scaleid])) {
                $options[$scale->id] = $scale->get_name().' '.get_string('incorrectcustomscale', 'grades');
            }
        }
        $mform->addElement('select', 'grade_item_scaleid', get_string('scale'), $options);
        $mform->addHelpButton('grade_item_scaleid', 'typescale', 'grades');
        $mform->hideIf('grade_item_scaleid', 'grade_item_gradetype', 'noteq', GRADE_TYPE_SCALE);
        $mform->hideIf('grade_item_scaleid', 'aggregation', 'eq', GRADE_AGGREGATE_SUM);

        $choices = [];
        $choices[''] = get_string('choose');
        $choices['no'] = get_string('no');
        $choices['yes'] = get_string('yes');
        $mform->addElement('select', 'grade_item_rescalegrades', get_string('modgradecategoryrescalegrades', 'grades'), $choices);
        $mform->addHelpButton('grade_item_rescalegrades', 'modgradecategoryrescalegrades', 'grades');
        $mform->hideIf('grade_item_rescalegrades', 'grade_item_gradetype', 'noteq', GRADE_TYPE_VALUE);

        $mform->addElement('float', 'grade_item_grademax', get_string('grademax', 'grades'));
        $mform->addHelpButton('grade_item_grademax', 'grademax', 'grades');
        $mform->hideIf('grade_item_grademax', 'grade_item_gradetype', 'noteq', GRADE_TYPE_VALUE);
        $mform->hideIf('grade_item_grademax', 'aggregation', 'eq', GRADE_AGGREGATE_SUM);

        if ((bool) get_config('moodle', 'grade_report_showmin')) {
            $mform->addElement('float', 'grade_item_grademin', get_string('grademin', 'grades'));
            $mform->addHelpButton('grade_item_grademin', 'grademin', 'grades');
            $mform->hideIf('grade_item_grademin', 'grade_item_gradetype', 'noteq', GRADE_TYPE_VALUE);
            $mform->hideIf('grade_item_grademin', 'aggregation', 'eq', GRADE_AGGREGATE_SUM);
        }

        $mform->addElement('float', 'grade_item_gradepass', get_string('gradepass', 'grades'));
        $mform->addHelpButton('grade_item_gradepass', 'gradepass', 'grades');
        $mform->hideIf('grade_item_gradepass', 'grade_item_gradetype', 'eq', GRADE_TYPE_NONE);
        $mform->hideIf('grade_item_gradepass', 'grade_item_gradetype', 'eq', GRADE_TYPE_TEXT);

        // Grade display prefs.
        $defaultgradedisplaytype = grade_get_setting($courseid, 'displaytype', $CFG->grade_displaytype);
        $options = [
            GRADE_DISPLAY_TYPE_DEFAULT            => get_string('default', 'grades'),
            GRADE_DISPLAY_TYPE_REAL               => get_string('real', 'grades'),
            GRADE_DISPLAY_TYPE_PERCENTAGE         => get_string('percentage', 'grades'),
            GRADE_DISPLAY_TYPE_LETTER             => get_string('letter', 'grades'),
            GRADE_DISPLAY_TYPE_REAL_PERCENTAGE    => get_string('realpercentage', 'grades'),
            GRADE_DISPLAY_TYPE_REAL_LETTER        => get_string('realletter', 'grades'),
            GRADE_DISPLAY_TYPE_LETTER_REAL        => get_string('letterreal', 'grades'),
            GRADE_DISPLAY_TYPE_LETTER_PERCENTAGE  => get_string('letterpercentage', 'grades'),
            GRADE_DISPLAY_TYPE_PERCENTAGE_LETTER  => get_string('percentageletter', 'grades'),
            GRADE_DISPLAY_TYPE_PERCENTAGE_REAL    => get_string('percentagereal', 'grades')
        ];

        asort($options);

        foreach ($options as $key => $option) {
            if ($key == $defaultgradedisplaytype) {
                $options[GRADE_DISPLAY_TYPE_DEFAULT] = get_string('defaultprev', 'grades', $option);
                break;
            }
        }
        $mform->addElement('select', 'grade_item_display', get_string('gradedisplaytype', 'grades'), $options);
        $mform->addHelpButton('grade_item_display', 'gradedisplaytype', 'grades');
        $mform->hideIf('grade_item_display', 'grade_item_gradetype', 'in', [GRADE_TYPE_TEXT, GRADE_TYPE_NONE]);

        $default_gradedecimals = grade_get_setting($courseid, 'decimalpoints', $CFG->grade_decimalpoints);
        $options = [-1 => get_string('defaultprev', 'grades', $default_gradedecimals),
            0 => 0, 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5];
        $mform->addElement('select', 'grade_item_decimals', get_string('decimalpoints', 'grades'), $options);
        $mform->addHelpButton('grade_item_decimals', 'decimalpoints', 'grades');
        $mform->setDefault('grade_item_decimals', -1);
        $mform->hideIf('grade_item_decimals', 'grade_item_display', 'eq', GRADE_DISPLAY_TYPE_LETTER);
        $mform->hideIf('grade_item_decimals', 'grade_item_gradetype', 'in', [GRADE_TYPE_TEXT, GRADE_TYPE_NONE]);

        if ($defaultgradedisplaytype == GRADE_DISPLAY_TYPE_LETTER) {
            $mform->hideIf('grade_item_decimals', 'grade_item_display', 'eq', GRADE_DISPLAY_TYPE_DEFAULT);
        }

        // Mark advanced according to site settings.
/*        if (isset($CFG->grade_item_advanced)) {
            $advanced = explode(',', $CFG->grade_item_advanced);
            foreach ($advanced as $el) {
                $el = 'grade_item_' . $el;
                if ($mform->elementExists($el)) {
                    $mform->setAdvanced($el);
                }
            }
        }*/

        // Add return tracking info.
        $gpr->add_mform_elements($mform);

        $this->set_data($category);
    }

    // Tweak the form - depending on existing data.
    public function definition_after_data() {
        global $CFG;

        $mform =& $this->_form;

        $somecat = new grade_category();

        foreach ($somecat->forceable as $property) {
            if ((int)$CFG->{"grade_{$property}_flag"} & 1) {
                if ($mform->elementExists($property)) {
                    if (empty($CFG->grade_hideforcedsettings)) {
                        $mform->hardFreeze($property);
                    } else {
                        if ($mform->elementExists($property)) {
                            $mform->removeElement($property);
                        }
                    }
                }
            }
        }

        if ($CFG->grade_droplow > 0) {
            if ($mform->elementExists('keephigh')) {
                $mform->removeElement('keephigh');
            }
        } else if ($CFG->grade_keephigh > 0) {
            if ($mform->elementExists('droplow')) {
                $mform->removeElement('droplow');
            }
        }

        if ($id = $mform->getElementValue('id')) {
            $gradecategory = grade_category::fetch(['id' => $id]);
            $gradeitem = $gradecategory->load_grade_item();

            // Remove agg coef if not used.
            if ($gradecategory->is_course_category()) {
                if ($mform->elementExists('parentcategory')) {
                    $mform->removeElement('parentcategory');
                }
                if ($mform->elementExists('currentparentaggregation')) {
                    $mform->removeElement('currentparentaggregation');
                }
            } else {
                // If we wanted to change parent of existing category
                // we would have to verify there are no circular references in parents!!!
                if ($mform->elementExists('parentcategory')) {
                    $mform->hardFreeze('parentcategory');
                }
                $parentcat = $gradecategory->get_parent_category();
                $mform->setDefault('currentparentaggregation', $this->aggregation_options[$parentcat->aggregation]);
            }

            // Prevent the user from using drop lowest/keep highest when the aggregation method cannot handle it.
            if (!$gradecategory->can_apply_limit_rules()) {
                if ($mform->elementExists('keephigh')) {
                    $mform->setConstant('keephigh', 0);
                    $mform->hardFreeze('keephigh');
                }
                if ($mform->elementExists('droplow')) {
                    $mform->setConstant('droplow', 0);
                    $mform->hardFreeze('droplow');
                }
            }

            if ($gradeitem->is_calculated()) {
                // Following elements are ignored when calculation formula used.
                if ($mform->elementExists('aggregation')) {
                    $mform->removeElement('aggregation');
                }
                if ($mform->elementExists('keephigh')) {
                    $mform->removeElement('keephigh');
                }
                if ($mform->elementExists('droplow')) {
                    $mform->removeElement('droplow');
                }
                if ($mform->elementExists('aggregateonlygraded')) {
                    $mform->removeElement('aggregateonlygraded');
                }
                if ($mform->elementExists('aggregateoutcomes')) {
                    $mform->removeElement('aggregateoutcomes');
                }
            }

            // If it is a course category, remove the "required" rule from the "fullname" element.
            if ($gradecategory->is_course_category()) {
                unset($mform->_rules['fullname']);
                $key = array_search('fullname', $mform->_required);
                unset($mform->_required[$key]);
            }

            // If it is a course category and its fullname is ?, show an empty field.
            if ($gradecategory->is_course_category() && $mform->getElementValue('fullname') == '?') {// THIS Doesn't work!!!!!!!!!
                $mform->setDefault('fullname', '');
            }
            // Remove unwanted aggregation options.
            if ($mform->elementExists('aggregation')) {
                $allaggoptions = array_keys($this->aggregation_options);
                $aggel =& $mform->getElement('aggregation');
                $visible = explode(',', $CFG->grade_aggregations_visible);
                if (!is_null($gradecategory->aggregation)) {
                    // Current type is always visible.
                    $visible[] = $gradecategory->aggregation;
                }
                foreach ($allaggoptions as $type) {
                    if (!in_array($type, $visible)) {
                        $aggel->removeOption($type);
                    }
                }
            }

        } else {
            // Adding new category.
            if ($mform->elementExists('currentparentaggregation')) {
                $mform->removeElement('currentparentaggregation');
            }
            // Remove unwanted aggregation options.
            if ($mform->elementExists('aggregation')) {
                $allaggoptions = array_keys($this->aggregation_options);
                $aggel =& $mform->getElement('aggregation');
                $visible = explode(',', $CFG->grade_aggregations_visible);
                foreach ($allaggoptions as $type) {
                    if (!in_array($type, $visible)) {
                        $aggel->removeOption($type);
                    }
                }
            }

            $mform->removeElement('grade_item_rescalegrades');
        }

        // Grade item.
        if ($id = $mform->getElementValue('id')) {
            $gradecategory = grade_category::fetch(['id' => $id]);
            $gradeitem = $gradecategory->load_grade_item();

            // Load appropriate "hidden"/"hidden until" defaults.
            if ($gradeitem->is_hiddenuntil()) {
                $mform->setDefault('grade_item_hiddenuntil', $gradeitem->get_hidden());
            } else {
                $mform->setDefault('grade_item_hidden', $gradeitem->get_hidden());
            }

            if ($gradeitem->is_outcome_item()) {
                // We have to prevent incompatible modifications of outcomes if outcomes disabled.
                $mform->removeElement('grade_item_grademax');
                if ($mform->elementExists('grade_item_grademin')) {
                    $mform->removeElement('grade_item_grademin');
                }
                $mform->removeElement('grade_item_gradetype');
                $mform->removeElement('grade_item_display');
                $mform->removeElement('grade_item_decimals');
                $mform->hardFreeze('grade_item_scaleid');
                // Only show the option to rescale grades on a category if its corresponding grade_item has overridden grade_grades.
            } else if ($gradeitem->has_overridden_grades()) {
                // Can't change the grade type or the scale if there are grades.
                $mform->hardFreeze('grade_item_gradetype, grade_item_scaleid');

                // If we are using scles then remove the unnecessary rescale and grade fields.
                if ($gradeitem->gradetype == GRADE_TYPE_SCALE) {
                    $mform->removeElement('grade_item_rescalegrades');
                    $mform->removeElement('grade_item_grademax');
                    if ($mform->elementExists('grade_item_grademin')) {
                        $mform->removeElement('grade_item_grademin');
                    }
                } else {
                    // Not using scale, so remove it.
                    $mform->removeElement('grade_item_scaleid');
                    $mform->hideIf('grade_item_grademax', 'grade_item_rescalegrades', 'eq', '');
                    $mform->hideIf('grade_item_grademin', 'grade_item_rescalegrades', 'eq', '');
                }
            } else {
                // Remove the rescale element if there are no grades.
                $mform->removeElement('grade_item_rescalegrades');
            }

            // Remove the aggregation coef element if not needed.
            if ($gradeitem->is_course_item()) {
                if ($mform->elementExists('grade_item_aggregationcoef')) {
                    $mform->removeElement('grade_item_aggregationcoef');
                }

                if ($mform->elementExists('grade_item_weightoverride')) {
                    $mform->removeElement('grade_item_weightoverride');
                }
                if ($mform->elementExists('grade_item_aggregationcoef2')) {
                    $mform->removeElement('grade_item_aggregationcoef2');
                }
            } else {
                if ($gradeitem->is_category_item()) {
                    $category = $gradeitem->get_item_category();
                    $parentcategory = $category->get_parent_category();
                } else {
                    $parentcategory = $gradeitem->get_parent_category();
                }

                $parentcategory->apply_forced_settings();

                if (!$parentcategory->is_aggregationcoef_used()) {
                    if ($mform->elementExists('grade_item_aggregationcoef')) {
                        $mform->removeElement('grade_item_aggregationcoef');
                    }
                } else {
                    $coefstring = $gradeitem->get_coefstring();

                    if ($coefstring == 'aggregationcoefextrasum' || $coefstring == 'aggregationcoefextraweightsum') {
                        // Advcheckbox is not compatible with disabledIf!
                        $coefstring = 'aggregationcoefextrasum';
                        $element =& $mform->createElement('checkbox', 'grade_item_aggregationcoef',
                            get_string($coefstring, 'grades'));
                    } else {
                        $element =& $mform->createElement('text', 'grade_item_aggregationcoef',
                            get_string($coefstring, 'grades'));
                    }
                    $mform->insertElementBefore($element, 'parentcategory');
                    $mform->addHelpButton('grade_item_aggregationcoef', $coefstring, 'grades');
                }

                // Remove fields used by natural weighting if the parent category is not using natural weighting.
                // Or if the item is a scale and scales are not used in aggregation.
                if ($parentcategory->aggregation != GRADE_AGGREGATE_SUM
                    || (empty($CFG->grade_includescalesinaggregation) && $gradeitem->gradetype == GRADE_TYPE_SCALE)) {
                    if ($mform->elementExists('grade_item_weightoverride')) {
                        $mform->removeElement('grade_item_weightoverride');
                    }
                    if ($mform->elementExists('grade_item_aggregationcoef2')) {
                        $mform->removeElement('grade_item_aggregationcoef2');
                    }
                }
            }
        }
    }

    protected function get_context_for_dynamic_submission(): context {
        $courseid = $this->optional_param('courseid', null, PARAM_INT);
        return context_course::instance($courseid);
    }

    protected function check_access_for_dynamic_submission(): void {
        $courseid = $this->optional_param('courseid', null, PARAM_INT);
        require_capability('moodle/grade:manage', context_course::instance($courseid));
    }

    public function set_data_for_dynamic_submission(): void {
        $this->set_data((object)[
            'courseid' => $this->optional_param('courseid', null, PARAM_INT),
            'category' => $this->optional_param('category', null, PARAM_INT)
        ]);
    }

    protected function get_page_url_for_dynamic_submission(): moodle_url {
        $params = [
            'id' => $this->optional_param('courseid', null, PARAM_INT),
            'category' => $this->optional_param('category', null, PARAM_INT),
        ];
        return new moodle_url('/grade/edit/tree/index.php', $params);
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();

        $gpr = new grade_plugin_return();
        $url = $gpr->get_return_url('index.php?id=' . $data->courseid);
        $local = $this->get_gradecategory();
        $gradecategory = $local['gradecategory'];

        grade_edit_tree::update_gradecategory($gradecategory, $data);

        return [
            'result' => true,
            'url' => $url,
            'errors' => [],
        ];
    }

    /**
     * Form validation.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files): array {
        $gradeitem = false;
        if ($data['id']) {
            $gradecategory = grade_category::fetch(['id' => $data['id']]);
            $gradeitem = $gradecategory->load_grade_item();
        }

        $errors = parent::validation($data, $files);

        if (array_key_exists('grade_item_gradetype', $data) && $data['grade_item_gradetype'] == GRADE_TYPE_SCALE) {
            if (empty($data['grade_item_scaleid'])) {
                $errors['grade_item_scaleid'] = get_string('missingscale', 'grades');
            }
        }

        // We need to make all the validations related with grademax and grademin
        // with them being correct floats, keeping the originals unmodified for
        // later validations / showing the form back...
        // TODO: Note that once MDL-73994 is fixed we'll have to re-visit this and
        // adapt the code below to the new values arriving here, without forgetting
        // the special case of empties and nulls.
        $grademax = isset($data['grade_item_grademax']) ? unformat_float($data['grade_item_grademax']) : null;
        $grademin = isset($data['grade_item_grademin']) ? unformat_float($data['grade_item_grademin']) : null;

        if (!is_null($grademin) && !is_null($grademax)) {
            if (($grademax != 0 || $grademin != 0) && ($grademax == $grademin || $grademax < $grademin)) {
                $errors['grade_item_grademin'] = get_string('incorrectminmax', 'grades');
                $errors['grade_item_grademax'] = get_string('incorrectminmax', 'grades');
            }
        }

        if ($data['id'] && $gradeitem->has_overridden_grades()) {
            if ($gradeitem->gradetype == GRADE_TYPE_VALUE) {
                if (grade_floats_different($grademin, $gradeitem->grademin) ||
                    grade_floats_different($grademax, $gradeitem->grademax)) {
                    if (empty($data['grade_item_rescalegrades'])) {
                        $errors['grade_item_rescalegrades'] = get_string('mustchooserescaleyesorno', 'grades');
                    }
                }
            }
        }
        return $errors;
    }
}
