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

namespace core_grades\local\entities;

use core_reportbuilder\local\filters\select;
use lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_grades\local\helpers\average;

/**
 * Grade summary entity class implementation
 *
 * @package    core_grades
 * @copyright  2022 Ilya Tregubov <ilya@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_items extends base {

    public $ungradedcounts;

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['grade_items' => 'gi'];
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('gradeitem', 'grades');
    }

    /**
     * The default machine-readable name for this entity that will be used in the internal names of the columns/filters
     *
     * @return string
     */
    protected function get_default_entity_name(): string {
        return 'grade_items';
    }

    /**
     * Initialise the entity
     *
     * @return base
     */
    public function initialise(): base {
        global $COURSE;

        $this->ungradedcounts = average::ungraded_counts($COURSE->id);

        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        $filters = $this->get_all_filters();
        foreach ($filters as $filter) {
            $this->add_filter($filter);
        }

        return $this;
    }

    /**
     * Returns list of all available columns
     *
     * @return column[]
     */
    protected function get_all_columns(): array {

        $tablealias = $this->get_table_alias('grade_items');

        // Grade item name column.
        $columns[] = (new column(
            'name',
            null,
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("$tablealias.itemname, $tablealias.iteminstance, $tablealias.itemmodule, $tablealias.courseid")
            ->add_callback(static function($value, $row): string {
                global $PAGE;

                $modinfo = get_fast_modinfo($row->courseid);
                $instances = $modinfo->get_instances();
                $cm = $instances[$row->itemmodule][$row->iteminstance];
                $url = new \moodle_url('/mod/' . $row->itemmodule . '/view.php', array('id' => $cm->id)); //Not for manual grade items!!!!!

                $renderer =  new \core_renderer($PAGE, RENDERER_TARGET_GENERAL);

                $imagedata = '';
                if ($row->itemmodule) {
                    $imagedata = $renderer->pix_icon('monologo', '', $row->itemmodule, ['class' => 'activityicon']);
                    $purposeclass = plugin_supports('mod', $row->itemmodule, FEATURE_MOD_PURPOSE);
                    $purposeclass .= ' activityiconcontainer';
                    $purposeclass .= ' modicon_' . $row->itemmodule;
                    $imagedata = \html_writer::tag('div', $imagedata, ['class' => $purposeclass]);
                }

                // All the html stuff goes here.
                $html = \html_writer::start_div('page-context-header');

                // Image data.
                $html .= \html_writer::div($imagedata, 'page-header-image mr-2');
                if (isset($row->itemmodule)) {
                    $prefix = \html_writer::div($row->itemmodule, 'text-muted text-uppercase small line-height-3');
                    $name = $prefix . \html_writer::link($url, format_string($cm->name, true));
                } else {
                    $name = $row->itemname;
                }
                $html .= \html_writer::tag('div', $name, array('class' => 'page-header-headings'));
                return $html;

            });

        $ungradedcounts = $this->ungradedcounts;
        // Average column.
        $columns[] = (new column(
            'average',
            new lang_string('average', 'grades'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("$tablealias.id")
            ->set_is_sortable(true)
            ->add_callback(static function($value) use ($ungradedcounts): string {
                return average::calculate_average($value, $ungradedcounts);
            });

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $filters = [];

        $modnames = get_module_types_names();
        $tablealias = $this->get_table_alias('grade_items');

        // Activity type filter.
        $filters[] = (new filter(
            select::class,
            'name',
            new lang_string('activitytype', 'format_singleactivity'),
            $this->get_entity_name(),
            "{$tablealias}.itemmodule"
        ))
            ->add_joins($this->get_joins())
            ->set_options($modnames); // Hmm doesn't make sense to list all activity types if they are not in course

        return $filters;
    }
}
