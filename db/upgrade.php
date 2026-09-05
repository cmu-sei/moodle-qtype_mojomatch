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

/*
TopoMojo Question Type Plugin for Moodle

Copyright 2024 Carnegie Mellon University.

NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. 
CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, 
WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. 
CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Licensed under a GNU GENERAL PUBLIC LICENSE - Version 3, 29 June 2007-style license, please see license.txt or contact permission@sei.cmu.edu for full 
terms.

[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution. Please see Copyright notice for non-US Government use and distribution.

This Software includes and/or makes use of Third-Party Software each subject to its own license.

DM24-1315
*/

/**
 * Short-answer question type upgrade code.
 *
 * @package    qtype
 * @subpackage mojomatch
 * @copyright  2024 Carnegie Mellon University
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
    if ($oldversion < 2022081501) {

        // Define field order to be added to qtype_mojomatch_options.
        $table = new xmldb_table('qtype_mojomatch_options');
        $field = new xmldb_field('order', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'transforms');

        // Conditionally launch add field order.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Mojomatch savepoint reached.
        upgrade_plugin_savepoint(true, 2022081501, 'qtype', 'mojomatch');
    }
    if ($oldversion < 2022081600) {

        // Rename field order on table qtype_mojomatch_options to qorder.
        $table = new xmldb_table('qtype_mojomatch_options');
        $field = new xmldb_field('order', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'transforms');

        // Launch rename field order.
        $dbman->rename_field($table, $field, 'qorder');

        // Mojomatch savepoint reached.
        upgrade_plugin_savepoint(true, 2022081600, 'qtype', 'mojomatch');
    }
    if ($oldversion < 2026090500) {

        // Imports made before mod_topomojo 2026052601 recorded a 0-based variant on
        // imported challenge questions. The matcher compares the 1-based gamespace
        // variant against this column, so such a question can never match: the attempt
        // is created with no question usage at all and the student is shown no
        // questions and no error. Normalise those rows to 1.
        //
        // Only rows linked to a TopoMojo activity are touched, and only where every
        // linked row for that activity is 0, which makes the shift unambiguous.
        // Unlinked question bank residue is left as it is.
        if ($dbman->table_exists('topomojo_questions')) {

            // An activity whose linked rows mix 0 and 1 or more cannot be resolved
            // without knowing which variant it serves. Report those and skip them.
            $mixed = $DB->get_fieldset_sql(
                "SELECT tq.topomojoid
                   FROM {topomojo_questions} tq
                   JOIN {qtype_mojomatch_options} o ON o.questionid = tq.questionid
               GROUP BY tq.topomojoid
                 HAVING MIN(o.variant) = 0 AND MAX(o.variant) >= 1"
            );
            foreach ($mixed as $topomojoid) {
                mtrace("qtype_mojomatch: SKIPPED TopoMojo activity {$topomojoid} - "
                    . 'mixed question variants, needs manual review');
            }

            // Resolve the affected question ids first. Selecting from
            // qtype_mojomatch_options inside an UPDATE of the same table is rejected by
            // MySQL and MariaDB, so the ids are gathered here and written by id.
            $questionids = $DB->get_fieldset_sql(
                "SELECT DISTINCT tq.questionid
                   FROM {topomojo_questions} tq
                  WHERE tq.topomojoid IN (
                        SELECT tq2.topomojoid
                          FROM {topomojo_questions} tq2
                          JOIN {qtype_mojomatch_options} o2 ON o2.questionid = tq2.questionid
                      GROUP BY tq2.topomojoid
                        HAVING MAX(o2.variant) = 0)"
            );

            // A question shared with a skipped activity must not be shifted behind its
            // back, otherwise "SKIPPED" would not be true.
            if ($mixed && $questionids) {
                [$mixedsql, $mixedparams] = $DB->get_in_or_equal($mixed, SQL_PARAMS_NAMED, 'mid');
                $shared = $DB->get_fieldset_sql(
                    "SELECT DISTINCT tq.questionid
                       FROM {topomojo_questions} tq
                      WHERE tq.topomojoid {$mixedsql}",
                    $mixedparams
                );
                $questionids = array_values(array_diff($questionids, $shared));
            }

            $updated = 0;
            foreach (array_chunk($questionids, 500) as $chunk) {
                [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'qid');
                $select = "variant = 0 AND questionid {$insql}";
                $updated += $DB->count_records_select('qtype_mojomatch_options', $select, $params);
                $DB->set_field_select('qtype_mojomatch_options', 'variant', 1, $select, $params);
            }

            mtrace("qtype_mojomatch: normalised {$updated} pre-fix question "
                . 'variants from 0 to 1');
        } else {
            mtrace('qtype_mojomatch: mod_topomojo is not installed, '
                . 'skipping variant normalisation');
        }

        // Mojomatch savepoint reached.
        upgrade_plugin_savepoint(true, 2026090500, 'qtype', 'mojomatch');
    }
    return true;
}
