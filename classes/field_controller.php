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
 * @copyright  2026 Your Name <you@example.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customfield_omniselect;

/**
 * Manages field-level configuration for the omniselect custom field type.
 */
class field_controller extends \core_customfield\field_controller {
    /** @var string Plugin type identifier. */
    const TYPE = 'omniselect';

    /**
     * Adds the options textarea to the field configuration form.
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
    }

    /**
     * Validates the field configuration form data.
     *
     * @param array $data
     * @param array $files
     * @return array Validation errors keyed by element name.
     */
    public function config_form_validation(array $data, $files = []): array {
        $errors = [];
        $options = $this->parse_options($data['configdata']['options'] ?? '');
        if (count($options) < 1) {
            $errors['configdata[options]'] = get_string('err_required', 'form');
        }
        return $errors;
    }

    /**
     * Returns the parsed list of selectable options for this field.
     *
     * @return string[]
     */
    public function get_options(): array {
        return $this->parse_options($this->get_configdata_property('options') ?? '');
    }

    /**
     * Splits a newline-delimited string into a trimmed, non-empty option array.
     *
     * @param string $raw Raw textarea value.
     * @return string[]
     */
    private function parse_options(string $raw): array {
        $options = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $options[] = $line;
            }
        }
        return $options;
    }
}
