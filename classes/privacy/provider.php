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

namespace qtype_mojomatch\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem for qtype_mojomatch implementing user_preference_provider.
 *
 * The edit form remembers the options of the last question an author saved as user
 * preferences, so they become the defaults for the next new question.
 *
 * @package    qtype_mojomatch
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    // This component has data.
    // We need to return default options that have been set a user preferences.
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * Returns meta data about this system.
     *
     * @param   collection     $collection The initialised collection to add items to.
     * @return  collection     A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference('qtype_mojomatch_defaultmark', 'privacy:preference:defaultmark');
        $collection->add_user_preference('qtype_mojomatch_penalty', 'privacy:preference:penalty');
        $collection->add_user_preference('qtype_mojomatch_usecase', 'privacy:preference:usecase');
        $collection->add_user_preference('qtype_mojomatch_matchtype', 'privacy:preference:matchtype');
        $collection->add_user_preference('qtype_mojomatch_variant', 'privacy:preference:variant');
        $collection->add_user_preference('qtype_mojomatch_transforms', 'privacy:preference:transforms');
        $collection->add_user_preference('qtype_mojomatch_workspaceid', 'privacy:preference:workspaceid');
        return $collection;
    }

    /**
     * Export all user preferences for the plugin.
     *
     * @param int $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid) {
        $preference = get_user_preferences('qtype_mojomatch_defaultmark', null, $userid);
        if (null !== $preference) {
            $desc = get_string('privacy:preference:defaultmark', 'qtype_mojomatch');
            writer::export_user_preference('qtype_mojomatch', 'defaultmark', $preference, $desc);
        }

        $preference = get_user_preferences('qtype_mojomatch_penalty', null, $userid);
        if (null !== $preference) {
            $desc = get_string('privacy:preference:penalty', 'qtype_mojomatch');
            writer::export_user_preference('qtype_mojomatch', 'penalty', transform::percentage($preference), $desc);
        }

        $preference = get_user_preferences('qtype_mojomatch_usecase', null, $userid);
        if (null !== $preference) {
            $desc = get_string('privacy:preference:usecase', 'qtype_mojomatch');
            if ($preference) {
                $strvalue = get_string('caseyes', 'qtype_mojomatch');
            } else {
                $strvalue = get_string('caseno', 'qtype_mojomatch');
            }
            writer::export_user_preference('qtype_mojomatch', 'usecase', $strvalue, $desc);
        }

        $preference = get_user_preferences('qtype_mojomatch_matchtype', null, $userid);
        if (null !== $preference) {
            $desc = get_string('privacy:preference:matchtype', 'qtype_mojomatch');
            // The same keys the edit form's Match type menu stores.
            $labels = [
                '0' => 'matchalpha',
                '1' => 'matchall',
                '2' => 'matchany',
                '3' => 'match',
            ];
            if (isset($labels[$preference])) {
                $strvalue = get_string($labels[$preference], 'qtype_mojomatch');
            } else {
                $strvalue = $preference;
            }
            writer::export_user_preference('qtype_mojomatch', 'matchtype', $strvalue, $desc);
        }

        foreach (['variant', 'transforms', 'workspaceid'] as $name) {
            $preference = get_user_preferences('qtype_mojomatch_' . $name, null, $userid);
            if (null !== $preference) {
                $desc = get_string('privacy:preference:' . $name, 'qtype_mojomatch');
                writer::export_user_preference('qtype_mojomatch', $name, $preference, $desc);
            }
        }
    }
}
