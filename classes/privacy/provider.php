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
 * Privacy API provider for customfield_omniselect.
 *
 * @package    customfield_omniselect
 * @copyright  2026 Robert Bellamy <darwin5k@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customfield_omniselect\privacy;

use core_customfield\privacy\customfield_provider;
use core_customfield\data_controller;
use core_privacy\local\metadata\null_provider;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the omniselect custom field type.
 *
 * The omniselect field stores values against course instances (not users),
 * so it holds no personal data. It participates in the customfield export
 * API so course export includes selected field values.
 */
class provider implements customfield_provider, null_provider {
    /**
     * Returns the language string key explaining that no personal data is stored.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }

    /**
     * Exports custom field data for a given instance as part of a course export.
     *
     * @param data_controller $data
     * @param \stdClass $exportdata
     * @param array $subcontext
     */
    public static function export_customfield_data(data_controller $data, \stdClass $exportdata, array $subcontext): void {
        $context = $data->get_context();
        $exportdata->value = $data->export_value();
        writer::with_context($context)->export_data($subcontext, $exportdata);
    }

    /**
     * No personal data rows to delete before instance data removal.
     *
     * @param string $dataidstest SQL fragment
     * @param array $params
     * @param array $contextids
     */
    public static function before_delete_data(string $dataidstest, array $params, array $contextids): void {
    }

    /**
     * No personal data rows to delete before field removal.
     *
     * @param string $fieldidstest SQL fragment
     * @param array $params
     * @param array $contextids
     */
    public static function before_delete_fields(string $fieldidstest, array $params, array $contextids): void {
    }
}
