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
 * @copyright  2026 Robert Bellamy <darwin5k@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customfield_omniselect;

/**
 * Handles per-instance data for the omniselect custom field type.
 *
 * Selected values are stored in two places:
 *   1. customfield_omniselect_vals — one row per selected option ID, for fast
 *      indexed filtering and retrieval.
 *   2. customfield_data.value (TEXT) — a comma-separated display string written
 *      for Moodle backup and export compatibility.
 *
 * @package    customfield_omniselect
 * @copyright  2026 Robert Bellamy <darwin5k@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     * Returns the default value (empty multi-selection).
     *
     * @return array
     */
    public function get_default_value(): array {
        return [];
    }

    /**
     * Adds a multi-select element to the course editing form.
     *
     * The select element's option values are option IDs (integers), so submitted
     * data is a clean array of integers ready for validation.
     *
     * @param \MoodleQuickForm $mform
     */
    public function instance_form_definition(\MoodleQuickForm $mform): void {
        $field   = $this->get_field();
        $options = $field->get_options(); // Returns an array keyed by option ID.

        $elementname = $this->get_form_element_name();
        $mform->addElement(
            'select',
            $elementname,
            $field->get_formatted_name(),
            $options,
            ['multiple' => 'multiple', 'size' => min(8, max(3, count($options)))]
        );
        $mform->setType($elementname, PARAM_INT);

        if ($field->get_configdata_property('required')) {
            $mform->addRule($elementname, null, 'required', null, 'client');
        }
    }

    /**
     * Populates the form element from the normalized vals table before the form renders.
     *
     * @param \stdClass $instance
     */
    public function instance_form_before_set_data(\stdClass $instance): void {
        global $DB;

        $elementname = $this->get_form_element_name();
        if (!$this->get('id')) {
            $instance->{$elementname} = [];
            return;
        }

        $rows = $DB->get_records(
            'customfield_omniselect_vals',
            ['fieldid' => $this->get_field()->get('id'), 'instanceid' => $this->get('instanceid')],
            '',
            'id, optionid'
        );

        $optionids = [];
        foreach ($rows as $row) {
            $optionids[] = (int)$row->optionid;
        }
        $instance->{$elementname} = $optionids;
    }

    /**
     * Saves selected option IDs to the normalized table and writes a display
     * summary to customfield_data for backup and export compatibility.
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
        $submitted  = array_map('intval', (array)($datanew->{$elementname} ?? []));

        // Keep only IDs that exist in the current option set.
        $validids  = array_keys($this->get_field()->get_options());
        $optionids = array_values(array_filter($submitted, fn($id) => in_array($id, $validids, true)));

        // Overwrite normalized rows.
        $DB->delete_records('customfield_omniselect_vals', ['fieldid' => $fieldid, 'instanceid' => $instanceid]);
        foreach ($optionids as $optionid) {
            $DB->insert_record('customfield_omniselect_vals', (object)[
                'fieldid'    => $fieldid,
                'instanceid' => $instanceid,
                'optionid'   => $optionid,
            ]);
        }

        // Write display summary to customfield_data for backup/export.
        $alloptions = $this->get_field()->get_options();
        $labels     = array_map(fn($id) => $alloptions[$id] ?? '', $optionids);
        $datanew->{$elementname} = implode(', ', array_filter($labels));
        parent::instance_form_save($datanew);
    }

    /**
     * Deletes all normalized rows for this instance before the parent record is removed.
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
     * Returns a comma-separated string of selected option labels for export, or null if empty.
     *
     * Reads labels from the opts table via get_options(), so exported text always
     * reflects the current option label even if it was renamed after saving.
     *
     * @return string|null
     */
    public function export_value(): ?string {
        global $DB;

        if (!$this->get('id')) {
            return null;
        }

        $rows = $DB->get_records(
            'customfield_omniselect_vals',
            ['fieldid' => $this->get_field()->get('id'), 'instanceid' => $this->get('instanceid')],
            '',
            'id, optionid'
        );

        if (empty($rows)) {
            return null;
        }

        $alloptions = $this->get_field()->get_options();
        $labels     = [];
        foreach ($rows as $row) {
            if (isset($alloptions[$row->optionid])) {
                $labels[] = $alloptions[$row->optionid];
            }
        }

        return empty($labels) ? null : implode(', ', $labels);
    }
}
