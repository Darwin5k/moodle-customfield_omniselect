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
 * Uninstall steps for customfield_omniselect.
 *
 * @package    customfield_omniselect
 * @copyright  2026 Your Name <you@example.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Removes all data created by this plugin from the database.
 *
 * Moodle drops the plugin's own tables automatically after this function runs,
 * so we only need to clean up rows in core tables here.
 *
 * @return bool
 */
function xmldb_customfield_omniselect_uninstall(): bool {
    global $DB;

    $fieldids = $DB->get_fieldset_select('customfield_field', 'id', "type = 'omniselect'");
    if (!empty($fieldids)) {
        [$insql, $params] = $DB->get_in_or_equal($fieldids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('customfield_omniselect_vals', "fieldid {$insql}", $params);
        $DB->delete_records_select('customfield_omniselect_opts', "fieldid {$insql}", $params);
        $DB->delete_records_select('customfield_data', "fieldid {$insql}", $params);
        $DB->delete_records_select('customfield_field', "id {$insql}", $params);
    }

    return true;
}
