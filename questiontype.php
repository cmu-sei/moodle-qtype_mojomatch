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

[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.
Please see Copyright notice for non-US Government use and distribution.

This Software includes and/or makes use of Third-Party Software each subject to its own license.

DM24-1315
*/

/**
 * Question type class for the mojomatch question type.
 *
 * @package    qtype_mojomatch
 * @subpackage mojomatch
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/mojomatch/question.php');


/**
 * The mojomatch question type.
 *
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_mojomatch extends question_type {
    /**
     * Returns the options table and the extra fields stored in it.
     *
     * @return array The table name followed by its field names.
     */
    public function extra_question_fields() {
        return ['qtype_mojomatch_options', 'usecase', 'matchtype', 'variant', 'transforms', 'workspaceid', 'qorder'];
    }

    /**
     * Moves the question's files, including those in answers and hints, to another context.
     *
     * @param int $questionid The question id.
     * @param int $oldcontextid The context the files are in.
     * @param int $newcontextid The context to move them to.
     */
    public function move_files($questionid, $oldcontextid, $newcontextid) {
        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_answers($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_hints($questionid, $oldcontextid, $newcontextid);
    }

    /**
     * Deletes the question's files, including those in answers and hints.
     *
     * @param int $questionid The question id.
     * @param int $contextid The context the files are in.
     */
    protected function delete_files($questionid, $contextid) {
        parent::delete_files($questionid, $contextid);
        $this->delete_files_in_answers($questionid, $contextid);
        $this->delete_files_in_hints($questionid, $contextid);
    }

    /**
     * Saves the authoring options as the defaults for new questions.
     *
     * @param stdClass $fromform The data submitted by the edit form.
     */
    public function save_defaults_for_new_questions(stdClass $fromform): void {
        parent::save_defaults_for_new_questions($fromform);

        // Only the options the edit form actually carries, and only when it carried
        // them. qorder is not one of them: it records where a question sits inside an
        // imported TopoMojo challenge, so it is never typed by an author and arrives
        // here unset. set_default_value() is typed string, so handing it that null
        // threw, and the throw happened after the question had been written - which
        // rolled the whole save back and left the author on an exception page with no
        // question created.
        foreach (['usecase', 'matchtype', 'variant', 'transforms', 'workspaceid'] as $field) {
            if (isset($fromform->$field)) {
                $this->set_default_value($field, (string) $fromform->$field);
            }
        }
    }

    /**
     * Saves the question's options, answers and hints.
     *
     * @param stdClass $question The question data from the edit form.
     * @return stdClass|null An object with an error property when no answer has full credit.
     */
    public function save_question_options($question) {
        global $DB;
        $result = new stdClass();
        // Perform sanity checks on fractional grades.
        $maxfraction = -1;
        foreach ($question->answer as $key => $answerdata) {
            if ($question->fraction[$key] > $maxfraction) {
                $maxfraction = $question->fraction[$key];
            }
        }

        if ($maxfraction != 1) {
            $result->error = get_string('fractionsnomax', 'question', $maxfraction * 100);
            return $result;
        }

        parent::save_question_options($question);

        $this->save_question_answers($question);

        $this->save_hints($question);
    }

    /**
     * Prepares an answer record for saving, trimming its text.
     *
     * @param stdClass $answer The answer record.
     * @param stdClass $questiondata The question data from the edit form.
     * @param int $key The index of the answer.
     * @param context $context The question's context.
     * @return stdClass The answer record.
     */
    protected function fill_answer_fields($answer, $questiondata, $key, $context) {
        $answer = parent::fill_answer_fields($answer, $questiondata, $key, $context);
        $answer->answer = trim($answer->answer);
        return $answer;
    }

    /**
     * Fills a question object from its stored data, including its answers.
     *
     * @param question_definition $question The question object to fill.
     * @param stdClass $questiondata The stored question data.
     */
    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);
        $this->initialise_question_answers($question, $questiondata);
    }

    /**
     * Returns the score a random guess would get.
     *
     * @param stdClass $questiondata The stored question data.
     * @return float The fraction of the answer that is a lone asterisk, or 0 when there is none.
     */
    public function get_random_guess_score($questiondata) {
        foreach ($questiondata->options->answers as $aid => $answer) {
            if ('*' == trim($answer->answer)) {
                return $answer->fraction;
            }
        }
        return 0;
    }

    /**
     * Lists the responses a student could give, for the quiz statistics.
     *
     * @param stdClass $questiondata The stored question data.
     * @return array The possible responses, keyed by question id.
     */
    public function get_possible_responses($questiondata) {
        $responses = [];

        $starfound = false;
        foreach ($questiondata->options->answers as $aid => $answer) {
            $responses[$aid] = new question_possible_response(
                $answer->answer,
                $answer->fraction
            );
            if ($answer->answer === '*') {
                $starfound = true;
            }
        }

        if (!$starfound) {
            $responses[0] = new question_possible_response(
                get_string('didnotmatchanyanswer', 'question'),
                0
            );
        }

        $responses[null] = question_possible_response::no_response();

        return [$questiondata->id => $responses];
    }
}
