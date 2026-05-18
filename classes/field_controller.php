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
 * Field controller for the omniselect custom field type.
 *
 * @package    customfield_omniselect
 * @copyright  2026 Robert Bellamy <darwin5k@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customfield_omniselect;

/**
 * Manages field-level configuration for the omniselect custom field type.
 *
 * Options are persisted in customfield_omniselect_opts (one row per selectable
 * option, with a stable integer ID). The admin edits them via a plain textarea;
 * save() syncs the opts table from the textarea text.
 *
 * @package    customfield_omniselect
 * @copyright  2026 Robert Bellamy <darwin5k@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_controller extends \core_customfield\field_controller {
    /** @var string Plugin type identifier. */
    const TYPE = 'omniselect';

    /**
     * Returns all defined options for this field, ordered by sortorder.
     *
     * @return array<int,string> Keyed by option ID, values are display strings.
     */
    public function get_options(): array {
        global $DB;

        if (!$this->get('id')) {
            return [];
        }

        $records = $DB->get_records(
            'customfield_omniselect_opts',
            ['fieldid' => $this->get('id')],
            'sortorder ASC, id ASC',
            'id, value'
        );

        $options = [];
        foreach ($records as $rec) {
            $options[(int)$rec->id] = $rec->value;
        }
        return $options;
    }

    /**
     * Adds the options textarea to the field configuration form.
     *
     * The textarea is pre-populated from the opts table (one option per line).
     * On save, the opts table is synced from the submitted text.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form_definition(\MoodleQuickForm $mform): void {
        $mform->addElement(
            'header',
            'header_specificsettings',
            get_string('specificsettings', 'customfield_omniselect')
        );
        $mform->setExpanded('header_specificsettings', true);

        $mform->addElement(
            'textarea',
            'configdata[options]',
            get_string('options', 'customfield_omniselect'),
            ['rows' => 10, 'cols' => 50]
        );
        $mform->setType('configdata[options]', PARAM_TEXT);
        $mform->addHelpButton('configdata[options]', 'options', 'customfield_omniselect');

        // Pre-populate the textarea from the opts table when editing an existing field.
        if ($this->get('id')) {
            $currentoptions = array_values($this->get_options());
            $mform->setDefault('configdata[options]', implode("\n", $currentoptions));
        }
    }

    /**
     * Validates the field configuration form data.
     *
     * @param array $data
     * @param array $files
     * @return array Validation errors keyed by element name.
     */
    public function config_form_validation(array $data, $files = []): array {
        $errors  = [];
        $options = $this->parse_options($data['configdata']['options'] ?? '');
        if (count($options) < 1) {
            $errors['configdata[options]'] = get_string('err_required', 'form');
        }
        return $errors;
    }

    /**
     * Saves the field and syncs the opts table from the textarea content.
     *
     * Matching is done by exact string value: same string means same option ID
     * (sortorder may update), new string creates a new row, removed string
     * deletes the row and cascades to any vals that referenced it.
     */
    public function save(): void {
        global $DB;

        if (!$this->get('id')) {
            $this->field->save();
        }

        $fieldid    = $this->get('id');
        $newvalues  = $this->parse_options($this->get_configdata_property('options') ?? '');
        $newbyvalue = array_flip($newvalues);

        $existing        = $DB->get_records('customfield_omniselect_opts', ['fieldid' => $fieldid], 'sortorder', 'id, value');
        $existingbyvalue = [];
        foreach ($existing as $opt) {
            $existingbyvalue[$opt->value] = (int)$opt->id;
        }

        // Add new options; update sortorder for retained ones.
        foreach ($newvalues as $sortorder => $value) {
            if (isset($existingbyvalue[$value])) {
                $DB->set_field(
                    'customfield_omniselect_opts',
                    'sortorder',
                    $sortorder,
                    ['id' => $existingbyvalue[$value]]
                );
            } else {
                $DB->insert_record('customfield_omniselect_opts', (object)[
                    'fieldid'   => $fieldid,
                    'value'     => $value,
                    'sortorder' => $sortorder,
                ]);
            }
        }

        // Delete options removed from the textarea and cascade to vals.
        foreach ($existing as $opt) {
            if (!isset($newbyvalue[$opt->value])) {
                $DB->delete_records('customfield_omniselect_vals', ['fieldid' => $fieldid, 'optionid' => $opt->id]);
                $DB->delete_records('customfield_omniselect_opts', ['id' => $opt->id]);
            }
        }

        $this->field->save();
    }

    /**
     * Deletes this field and all associated option definitions and selection data.
     *
     * @return bool
     */
    public function delete(): bool {
        global $DB;
        $DB->delete_records('customfield_omniselect_vals', ['fieldid' => $this->get('id')]);
        $DB->delete_records('customfield_omniselect_opts', ['fieldid' => $this->get('id')]);
        return parent::delete();
    }

    /**
     * Splits a newline-delimited string into a trimmed, deduplicated option array.
     *
     * @param string $raw Raw textarea value.
     * @return string[]
     */
    private function parse_options(string $raw): array {
        $options = [];
        $seen    = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line !== '' && !isset($seen[$line])) {
                $options[] = $line;
                $seen[$line] = true;
            }
        }
        return $options;
    }
}
