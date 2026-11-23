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

NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS"
BASIS.  CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER
INCLUDING, BUT NOT LIMITED TO,  WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED
FROM USE OF THE MATERIAL.  CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM
FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT. Licensed under a GNU GENERAL PUBLIC LICENSE - Version 3, 29 June
2007-style license, please see license.txt or contact permission@sei.cmu.edu for full  terms.

[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution. Please see
Copyright notice for non-US Government use and distribution.

This Software includes and/or makes use of Third-Party Software each subject to its own license.

DM24-1315
*/

defined('MOODLE_INTERNAL') || die();

/**
 * Class for converting attempt data for mojomatch questions when upgrading
 * attempts to the new question engine.
 *
 * This class is used by the code in question/engine/upgrade/upgradelib.php.
 *
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package qtype_mojomatch
 */

/**
 * Converts the old-engine attempt data of mojomatch questions to the new question engine.
 */
class qtype_mojomatch_qe2_attempt_updater extends question_qtype_attempt_updater {
    /**
     * Returns the text of the answer that earns full credit.
     *
     * @return string|null The answer text, or null when none does.
     */
    public function right_answer() {
        foreach ($this->question->options->answers as $ans) {
            if ($ans->fraction > 0.999) {
                return $ans->answer;
            }
        }
    }

    /**
     * Tells whether the old state holds an answer.
     *
     * @param stdClass $state The old question state.
     * @return bool Whether an answer was given.
     */
    public function was_answered($state) {
        return !empty($state->answer);
    }

    /**
     * Returns the answer the old state holds.
     *
     * @param stdClass $state The old question state.
     * @return string|null The answer, or null when none was given.
     */
    public function response_summary($state) {
        if (!empty($state->answer)) {
            return $state->answer;
        } else {
            return null;
        }
    }

    /**
     * Sets the data of the first step; mojomatch has none.
     *
     * @param stdClass $state The old question state.
     * @param array $data The step data, left unchanged.
     */
    public function set_first_step_data_elements($state, &$data) {
    }

    /**
     * Supplies missing first step data; mojomatch has none.
     *
     * @param array $data The step data, left unchanged.
     */
    public function supply_missing_first_step_data(&$data) {
    }

    /**
     * Sets the answer in the data of a step.
     *
     * @param stdClass $state The old question state.
     * @param array $data The step data, to which the answer is added.
     */
    public function set_data_elements_for_step($state, &$data) {
        if (!empty($state->answer)) {
            $data['answer'] = $state->answer;
        }
    }
}
