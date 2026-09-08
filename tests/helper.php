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

DM24-1319
*/

defined('MOODLE_INTERNAL') || die();

/**
 * Test helper for the mojomatch question type.
 *
 * Provides ready-made {@see qtype_mojomatch_question} instances so that
 * behaviour walkthroughs (see qbehaviour_mojomatch) can exercise grading and
 * penalty logic without a live TopoMojo gamespace. The questions here define a
 * STATIC correct answer; grade_response_qa() only overrides it with a live
 * answer when a challenge is attached to the attempt (get_rightanswer_topomojo),
 * which never happens in a unit test, so the static answer is used.
 *
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_mojomatch_test_helper extends question_test_helper {

    public function get_test_questions() {
        return ['exactmatch'];
    }

    /**
     * Makes a mojomatch question with a single exact-match ('match') answer.
     *
     * Answer: 'cp' (fraction 1.0). Default penalty 0.1 (0-1 fraction of the
     * mark, deducted per prior wrong try). Tests that need a different penalty
     * override $q->penalty after construction.
     *
     * @return qtype_mojomatch_question
     */
    public function make_mojomatch_question_exactmatch() {
        question_bank::load_question_definition_classes('mojomatch');
        $q = new qtype_mojomatch_question();
        test_question_maker::initialise_a_question($q);
        $q->name = 'Mojomatch exact match';
        $q->questiontext = 'Which command copies a file?';
        $q->questiontextformat = FORMAT_HTML;
        $q->generalfeedback = 'The copy command is cp.';
        $q->generalfeedbackformat = FORMAT_HTML;
        $q->usecase = false;
        $q->matchtype = '1'; // '1' = match (exact), per compare_string_with_matchtype().
        $q->variant = 0;
        $q->transforms = null;
        $q->qorder = 0;
        $q->penalty = 0.1;
        $q->answers = [
            13 => new question_answer(13, 'cp', 1.0, '', FORMAT_HTML),
        ];
        $q->qtype = question_bank::get_qtype('mojomatch');
        return $q;
    }
}
