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
 * Unit tests for customfield_omniselect field_controller.
 *
 * @package    customfield_omniselect
 * @copyright  2026 Your Name <you@example.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \customfield_omniselect\field_controller
 */

namespace customfield_omniselect;

use advanced_testcase;

/**
 * Tests for field_controller option parsing and configuration validation.
 */
class field_controller_test extends advanced_testcase {

    /**
     * Creates a real omniselect field via the core_customfield generator.
     *
     * @param string $options Newline-delimited option string.
     * @return field_controller
     */
    private function create_field(string $options = "Option A\nOption B\nOption C"): field_controller {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $category  = $generator->create_category([
            'component' => 'core_course',
            'area'      => 'course',
        ]);
        return $generator->create_field([
            'categoryid' => $category->get('id'),
            'type'       => 'omniselect',
            'shortname'  => 'testfield',
            'name'       => 'Test field',
            'configdata' => json_encode(['options' => $options]),
        ]);
    }

    /**
     * Options are parsed correctly from a Unix newline-delimited string.
     */
    public function test_get_options_unix_newlines(): void {
        $this->resetAfterTest();
        $field = $this->create_field("Red\nGreen\nBlue");

        $this->assertSame(['Red', 'Green', 'Blue'], $field->get_options());
    }

    /**
     * Windows-style CRLF line endings are handled correctly.
     */
    public function test_get_options_crlf_newlines(): void {
        $this->resetAfterTest();
        $field = $this->create_field("Red\r\nGreen\r\nBlue");

        $this->assertSame(['Red', 'Green', 'Blue'], $field->get_options());
    }

    /**
     * Blank lines in the options textarea are ignored.
     */
    public function test_get_options_ignores_blank_lines(): void {
        $this->resetAfterTest();
        $field = $this->create_field("Red\n\nGreen\n\nBlue\n");

        $this->assertSame(['Red', 'Green', 'Blue'], $field->get_options());
    }

    /**
     * Whitespace-only lines are stripped and not returned as options.
     */
    public function test_get_options_strips_whitespace_lines(): void {
        $this->resetAfterTest();
        $field = $this->create_field("  Red  \n   \nGreen");

        $this->assertSame(['Red', 'Green'], $field->get_options());
    }

    /**
     * An empty options string yields an empty array.
     */
    public function test_get_options_empty_string(): void {
        $this->resetAfterTest();
        $field = $this->create_field('');

        $this->assertSame([], $field->get_options());
    }

    /**
     * config_form_validation rejects an empty options string.
     */
    public function test_config_form_validation_requires_options(): void {
        $this->resetAfterTest();
        $field = $this->create_field('Red');

        $data   = ['configdata' => ['options' => '']];
        $errors = $field->config_form_validation($data);

        $this->assertArrayHasKey('configdata[options]', $errors);
    }

    /**
     * config_form_validation passes when at least one option is provided.
     */
    public function test_config_form_validation_passes_with_options(): void {
        $this->resetAfterTest();
        $field = $this->create_field('Red');

        $data   = ['configdata' => ['options' => 'Red']];
        $errors = $field->config_form_validation($data);

        $this->assertEmpty($errors);
    }
}
