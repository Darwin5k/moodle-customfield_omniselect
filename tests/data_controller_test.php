<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Unit tests for customfield_omniselect data_controller.
 *
 * @package    customfield_omniselect
 * @copyright  2026 Your Name <you@example.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \customfield_omniselect\data_controller
 */

namespace customfield_omniselect;

use advanced_testcase;
use core_customfield\data_controller as core_data_controller;

/**
 * Tests for data_controller save, load, delete, and export behaviour.
 */
class data_controller_test extends advanced_testcase {

    /** @var \core_customfield\field_controller */
    private \core_customfield\field_controller $field;

    /** @var \stdClass */
    private \stdClass $course;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator    = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $category     = $generator->create_category(['component' => 'core_course', 'area' => 'course']);
        $this->field  = $generator->create_field([
            'categoryid' => $category->get('id'),
            'type'       => 'omniselect',
            'shortname'  => 'states',
            'name'       => 'States',
            'configdata' => json_encode(['options' => "Alabama\nAlaska\nArizona"]),
        ]);

        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Saves two values and confirms they appear in the normalized table.
     */
    public function test_instance_form_save_writes_normalized_rows(): void {
        global $DB;

        $this->save_values(['Alabama', 'Alaska']);

        $rows = $DB->get_fieldset_select(
            'customfield_omniselect_vals',
            'value',
            'fieldid = ? AND instanceid = ?',
            [$this->field->get('id'), $this->course->id]
        );
        sort($rows);

        $this->assertSame(['Alabama', 'Alaska'], $rows);
    }

    /**
     * Saves values and confirms the display string in customfield_data.
     */
    public function test_instance_form_save_writes_display_string(): void {
        global $DB;

        $this->save_values(['Alabama', 'Alaska']);

        $display = $DB->get_field_select(
            'customfield_data',
            'value',
            'fieldid = ? AND instanceid = ?',
            [$this->field->get('id'), $this->course->id]
        );

        $this->assertStringContainsString('Alabama', $display);
        $this->assertStringContainsString('Alaska', $display);
    }

    /**
     * Values not in the defined option list are silently rejected on save.
     */
    public function test_instance_form_save_rejects_invalid_values(): void {
        global $DB;

        $this->save_values(['Alabama', 'NOTANOPTION']);

        $rows = $DB->get_fieldset_select(
            'customfield_omniselect_vals',
            'value',
            'fieldid = ? AND instanceid = ?',
            [$this->field->get('id'), $this->course->id]
        );

        $this->assertSame(['Alabama'], $rows);
    }

    /**
     * Saving an empty selection clears all existing rows.
     */
    public function test_instance_form_save_clears_on_empty_selection(): void {
        global $DB;

        $this->save_values(['Alabama']);
        $this->save_values([]);

        $count = $DB->count_records('customfield_omniselect_vals', [
            'fieldid'    => $this->field->get('id'),
            'instanceid' => $this->course->id,
        ]);

        $this->assertSame(0, $count);
    }

    /**
     * export_value returns null when no values are saved.
     */
    public function test_export_value_returns_null_when_empty(): void {
        $dc = $this->get_data_controller();
        $this->assertNull($dc->export_value());
    }

    /**
     * export_value returns a comma-separated string after values are saved.
     */
    public function test_export_value_returns_string(): void {
        $this->save_values(['Alabama', 'Alaska']);

        $dc     = $this->get_data_controller();
        $export = $dc->export_value();

        $this->assertIsString($export);
        $this->assertStringContainsString('Alabama', $export);
        $this->assertStringContainsString('Alaska', $export);
    }

    /**
     * delete() removes all rows from the normalized table for this instance.
     */
    public function test_delete_removes_normalized_rows(): void {
        global $DB;

        $this->save_values(['Alabama', 'Alaska']);

        $dc = $this->get_data_controller();
        $dc->delete();

        $count = $DB->count_records('customfield_omniselect_vals', [
            'fieldid'    => $this->field->get('id'),
            'instanceid' => $this->course->id,
        ]);

        $this->assertSame(0, $count);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Saves the given values against the test course via instance_form_save().
     *
     * @param string[] $values
     */
    private function save_values(array $values): void {
        global $DB;

        // Re-use any existing customfield_data record so the second save does UPDATE not INSERT.
        $existing = $DB->get_record('customfield_data', [
            'fieldid'    => $this->field->get('id'),
            'instanceid' => $this->course->id,
        ]);
        // Pass 0 for ID; the record object carries its own id for UPDATE paths.
        $dc = core_data_controller::create(0, $existing ?: null, $this->field);
        if (!$existing) {
            $dc->set('instanceid', $this->course->id);
            $dc->set('contextid', \context_course::instance($this->course->id)->id);
        }

        $elementname            = $dc->get_form_element_name();
        $datanew                = (object)[$elementname => $values];
        $dc->instance_form_save($datanew);
    }

    /**
     * Returns a data_controller loaded for the test course (after a save).
     *
     * @return data_controller
     */
    private function get_data_controller(): data_controller {
        global $DB;

        $record = $DB->get_record('customfield_data', [
            'fieldid'    => $this->field->get('id'),
            'instanceid' => $this->course->id,
        ]);

        // Pass 0 for ID; the record object carries its own id for UPDATE paths.
        $dc = core_data_controller::create(0, $record ?: null, $this->field);
        if (!$record) {
            $dc->set('instanceid', $this->course->id);
        }
        return $dc;
    }
}
