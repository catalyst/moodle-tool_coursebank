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

/**
 * Course bank upgrades
 *
 * @package    tool
 * @subpackage coursebank
 * @author     Tim Price <timprice@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_tool_coursebank_upgrade($oldversion) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager();

    if ($oldversion < 2015031900) {

        // Define field isbackedup to be added to tool_coursebank.
        $table = new xmldb_table('tool_coursebank');
        $field = new xmldb_field('isbackedup', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'status');

        // Conditionally launch add field isbackedup.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursebank savepoint reached.
        upgrade_plugin_savepoint(true, 2015031900, 'tool', 'coursebank');
    }

    if ($oldversion < 2015032000) {

        // Define field contenthash to be added to tool_coursebank.
        $table = new xmldb_table('tool_coursebank');
        $field = new xmldb_field('contenthash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null, 'isbackedup');

        // Conditionally launch add field contenthash.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field pathnamehash to be added to tool_coursebank.
        $field = new xmldb_field('pathnamehash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null, 'contenthash');

        // Conditionally launch add field pathnamehash.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field userid to be added to tool_coursebank.
        $field = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '20', null, null, null, null, 'pathnamehash');

        // Conditionally launch add field userid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field filesize to be added to tool_coursebank.
        $field = new xmldb_field('filesize', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null, 'userid');

        // Conditionally launch add field filesize.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field filetimecreated to be added to tool_coursebank.
        $field = new xmldb_field('filetimecreated', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null, 'filesize');

        // Conditionally launch add field filetimecreated.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field filetimemodified to be added to tool_coursebank.
        $field = new xmldb_field('filetimemodified', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null, 'filetimecreated');

        // Conditionally launch add field filetimemodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field courseid to be added to tool_coursebank.
        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null, 'filetimemodified');

        // Conditionally launch add field courseid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field coursefullname to be added to tool_coursebank.
        $field = new xmldb_field('coursefullname', XMLDB_TYPE_CHAR, '254', null, XMLDB_NOTNULL, null, null, 'courseid');

        // Conditionally launch add field coursefullname.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field courseshortname to be added to tool_coursebank.
        $field = new xmldb_field('courseshortname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'coursefullname');

        // Conditionally launch add field courseshortname.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field coursestartdate to be added to tool_coursebank.
        $field = new xmldb_field('coursestartdate', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, '0', 'courseshortname');

        // Conditionally launch add field coursestartdate.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field categoryid to be added to tool_coursebank.
        $field = new xmldb_field('categoryid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, '0', 'coursestartdate');

        // Conditionally launch add field categoryid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field categoryname to be added to tool_coursebank.
        $field = new xmldb_field('categoryname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'categoryid');

        // Conditionally launch add field categoryname.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursebank savepoint reached.
        upgrade_plugin_savepoint(true, 2015032000, 'tool', 'coursebank');
    }

    if ($oldversion < 2015041300) {
        $table = new xmldb_table('tool_coursebank');
        // Define field timetransferstarted to be added to tool_coursebank.
        $field = new xmldb_field('timetransferstarted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, 'timecreated');

        // Conditionally launch add field filetimecreated.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Coursebank savepoint reached.
        upgrade_plugin_savepoint(true, 2015041300, 'tool', 'coursebank');
    }

    if ($oldversion < 2015041601) {
        $table = new xmldb_table('tool_coursebank');
        // Define field uniqueid to be added to tool_coursebank.
        $field = new xmldb_field('uniqueid', XMLDB_TYPE_CHAR, '36', null, XMLDB_NOTNULL, null, null, 'id');

        // Conditionally launch add field uniqueid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Drop the coursebank log table.
        $table = new xmldb_table('tool_coursebank_log');

        // Conditionally launch drop table.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Coursebank savepoint reached.
        upgrade_plugin_savepoint(true, 2015041601, 'tool', 'coursebank');
    }
    return true;
}
