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
 * Short-answer question type upgrade code.
 *
 * @package    qtype
 * @subpackage mojomatch
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade code for the essay question type.
 * @param int $oldversion the version we are upgrading from.
 */
function xmldb_qtype_mojomatch_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2022072201) {

        // Define field variant to be added to qtype_mojomatch_options.
        $table = new xmldb_table('qtype_mojomatch_options');
        $field = new xmldb_field('variant', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'matchtype');

        // Conditionally launch add field variant.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Mojomatch savepoint reached.
        upgrade_plugin_savepoint(true, 2022072201, 'qtype', 'mojomatch');
    }
    if ($oldversion < 2022072202) {

        // Define field workspaceid to be added to qtype_mojomatch_options.
        $table = new xmldb_table('qtype_mojomatch_options');
        $field = new xmldb_field('workspaceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '', 'variant');

        // Conditionally launch add field workspaceid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Define field transforms to be added to qtype_mojomatch_options.
        $table = new xmldb_table('qtype_mojomatch_options');
        $field = new xmldb_field('transforms', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'workspaceid');

        // Conditionally launch add field transforms.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Mojomatch savepoint reached.
        upgrade_plugin_savepoint(true, 2022072202, 'qtype', 'mojomatch');
    }
    if ($oldversion < 2022072203) {

        // Changing type of field workspaceid on table qtype_mojomatch_options to int.
        $table = new xmldb_table('qtype_mojomatch_options');
        $field = new xmldb_field('workspaceid', XMLDB_TYPE_TEXT, '255', null, XMLDB_NOTNULL, null, '', 'variant');

        // Launch change of type for field workspaceid.
        $dbman->change_field_type($table, $field);

        // Mojomatch savepoint reached.
        upgrade_plugin_savepoint(true, 2022072203, 'qtype', 'mojomatch');
    }

    return true;
}
