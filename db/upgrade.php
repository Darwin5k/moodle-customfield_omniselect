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
 * Upgrade steps for customfield_omniselect.
 *
 * @package    customfield_omniselect
 * @copyright  2026 Robert Bellamy <darwin5k@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrades the customfield_omniselect plugin.
 *
 * @param int $oldversion Previous installed version number.
 * @return bool
 */
function xmldb_customfield_omniselect_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026051401) {
        // Add a standalone instanceid index to speed up course-deletion cleanup.
        $table = new xmldb_table('customfield_omniselect_vals');
        $index = new xmldb_index('instanceid', XMLDB_INDEX_NOTUNIQUE, ['instanceid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026051401, 'customfield', 'omniselect');
    }

    if ($oldversion < 2026051402) {
        // Step 1: Create the customfield_omniselect_opts table.
        //
        // Options were previously stored as a newline-delimited string in
        // customfield_field.configdata. Moving them to a dedicated table gives
        // each option a stable integer ID, so renaming an option no longer
        // orphans existing selection data.
        $optstable = new xmldb_table('customfield_omniselect_opts');
        $optstable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $optstable->add_field('fieldid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $optstable->add_field('value', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $optstable->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $optstable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $optstable->add_key('fieldid', XMLDB_KEY_FOREIGN, ['fieldid'], 'customfield_field', ['id']);
        $optstable->add_index('fieldid_sortorder', XMLDB_INDEX_NOTUNIQUE, ['fieldid', 'sortorder']);
        $optstable->add_index('fieldid_value', XMLDB_INDEX_UNIQUE, ['fieldid', 'value']);

        if (!$dbman->table_exists($optstable)) {
            $dbman->create_table($optstable);
        }

        // Step 2: Seed the opts table from each field's configdata.
        //
        // Build a map of fieldid => option_string => new_option_id for use in
        // the vals migration below.
        $optionmap = []; // Keyed by fieldid and option label; values are option IDs.
        $fields    = $DB->get_records('customfield_field', ['type' => 'omniselect']);

        foreach ($fields as $field) {
            $configdata = json_decode($field->configdata, true);
            $raw        = $configdata['options'] ?? '';
            $sortorder  = 0;
            $optionmap[$field->id] = [];

            foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                // Guard against duplicate option strings (unique index).
                if (isset($optionmap[$field->id][$line])) {
                    continue;
                }
                $optid = $DB->insert_record('customfield_omniselect_opts', (object)[
                    'fieldid'   => $field->id,
                    'value'     => $line,
                    'sortorder' => $sortorder++,
                ]);
                $optionmap[$field->id][$line] = (int)$optid;
            }
        }

        // Step 3: Add the optionid column to customfield_omniselect_vals.
        $valstable = new xmldb_table('customfield_omniselect_vals');
        $optidfield = new xmldb_field('optionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($valstable, $optidfield)) {
            $dbman->add_field($valstable, $optidfield);
        }

        // Step 4: Populate optionid from the old value string.
        //
        // Any val whose string no longer matches a known option is left with
        // optionid = 0 (treated as an empty selection and harmless to leave).
        $vals = $DB->get_records('customfield_omniselect_vals', null, '', 'id, fieldid, value');
        foreach ($vals as $val) {
            $optid = $optionmap[$val->fieldid][$val->value] ?? 0;
            if ($optid > 0) {
                $DB->set_field('customfield_omniselect_vals', 'optionid', $optid, ['id' => $val->id]);
            }
        }

        // Step 5: Remove the old value-based index, drop the value column, and
        // add the new optionid index.
        $valueidx = new xmldb_index('fieldid_value', XMLDB_INDEX_NOTUNIQUE, ['fieldid', 'value']);
        if ($dbman->index_exists($valstable, $valueidx)) {
            $dbman->drop_index($valstable, $valueidx);
        }

        $valuefield = new xmldb_field('value');
        if ($dbman->field_exists($valstable, $valuefield)) {
            $dbman->drop_field($valstable, $valuefield);
        }

        $optididx = new xmldb_index('fieldid_optionid', XMLDB_INDEX_NOTUNIQUE, ['fieldid', 'optionid']);
        if (!$dbman->index_exists($valstable, $optididx)) {
            $dbman->add_index($valstable, $optididx);
        }

        upgrade_plugin_savepoint(true, 2026051402, 'customfield', 'omniselect');
    }

    return true;
}
