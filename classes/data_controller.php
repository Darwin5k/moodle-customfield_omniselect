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
 * Data controller for the omniselect custom field type.
 *
 * @package    customfield_omniselect
 * @copyright  2026 Your Name <you@example.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customfield_omniselect;

defined('MOODLE_INTERNAL') || die();

/**
 * Handles per-instance data for the omniselect custom field type.
 *
 * Selected values are stored in two places:
 *   1. `customfield_omniselect_vals` — one row per value, for fast indexed queries.
 *   2. `customfield_data.value` (TEXT) — comma-separated display string for Moodle
 *      backup and export compatibility.
 */
class data_controller extends \core_customfield\data_controller {

    /**
     * Returns the column in customfield_data used for the display summary.
     *
     * @return string
     */
    public function datafield(): string {
        return 'value';
    }

    /**
     * Returns the default value (empty selection).
     *
     * @return array
     */
    public function get_default_value(): array {
        return [];
    }

    /**
     * Adds a multi-select element to the course editing form.
     *
     * @param \MoodleQuickForm $mform
     */
    public function instance_form_definition(\MoodleQuickForm $mform): void {
        $field   = $this->get_field();
        $options = $field->get_options();

        $elementname = $this->get_form_element_name();
        $mform->addElement(
            'select',
            $elementname,
            $field->get_formatted_name(),
            array_combine($options, $options),
            ['multiple' => 'multiple', 'size' => min(8, max(3, count($options)))]
        );
        $mform->setType($elementname, PARAM_TEXT);

        if ($field->get_configdata_property('required')) {
            $mform->addRule($elementname, null, 'required', null, 'client');
        }
    }

    /**
     * Populates the form element from the normalized values table before the form renders.
     *
     * @param \stdClass $instance
     */
    public function instance_form_before_set_data(\stdClass $instance): void {
        global $DB;

        if (!$this->get('id')) {
            $instance->{$this->get_form_element_name()} = [];
            return;
        }

        $values = $DB->get_fieldset_select(
            'customfield_omniselect_vals',
            'value',
            'fieldid = ? AND instanceid = ?',
            [$this->get_field()->get('id'), $this->get('instanceid')]
        );

        $instance->{$this->get_form_element_name()} = $values;
    }

    /**
     * Saves selected values to the normalized table and writes a display summary to
     * customfield_data for backup/export compatibility.
     *
     * @param \stdClass $datanew
     */
    public function instance_form_save(\stdClass $datanew): void {
        global $DB;

        $elementname = $this->get_form_element_name();
        if (!property_exists($datanew, $elementname)) {
            return;
        }

        $fieldid    = $this->get_field()->get('id');
        $instanceid = $this->get('instanceid');
        $submitted  = (array)($datanew->{$elementname} ?? []);

        // Reject any values not in the defined option list.
        $valid  = $this->get_field()->get_options();
        $values = array_values(array_filter($submitted, fn($v) => in_array($v, $valid, true)));

        // Overwrite normalized rows.
        $DB->delete_records('customfield_omniselect_vals', ['fieldid' => $fieldid, 'instanceid' => $instanceid]);
        foreach ($values as $value) {
            $DB->insert_record('customfield_omniselect_vals', (object)[
                'fieldid'    => $fieldid,
                'instanceid' => $instanceid,
                'value'      => $value,
            ]);
        }

        // Write display summary to customfield_data for backup/export compatibility.
        $datanew->{$elementname} = implode(', ', $values);
        parent::instance_form_save($datanew);
    }

    /**
     * Deletes all normalized rows for this instance and removes the parent record.
     *
     * @return bool
     */
    public function delete(): bool {
        global $DB;
        $DB->delete_records('customfield_omniselect_vals', [
            'fieldid'    => $this->get_field()->get('id'),
            'instanceid' => $this->get('instanceid'),
        ]);
        return parent::delete();
    }

    /**
     * Returns a comma-separated string of selected values for export, or null if empty.
     *
     * @return string|null
     */
    public function export_value(): ?string {
        global $DB;

        if (!$this->get('id')) {
            return null;
        }

        $values = $DB->get_fieldset_select(
            'customfield_omniselect_vals',
            'value',
            'fieldid = ? AND instanceid = ?',
            [$this->get_field()->get('id'), $this->get('instanceid')]
        );

        return empty($values) ? null : implode(', ', $values);
    }
}
