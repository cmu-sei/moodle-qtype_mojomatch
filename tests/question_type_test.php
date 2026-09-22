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

namespace qtype_mojomatch;

use qtype_mojomatch;
use question_answer;
use question_possible_response;
use test_question_maker;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/mojomatch/questiontype.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');

/**
 * Unit tests for the mojomatch question type class.
 *
 * @package    qtype_mojomatch
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \qtype_mojomatch
 */
final class question_type_test extends \advanced_testcase {
    /** @var qtype_mojomatch the question type under test. */
    protected $qtype;

    protected function setUp(): void {
        parent::setUp();
        $this->qtype = new qtype_mojomatch();
    }

    protected function tearDown(): void {
        $this->qtype = null;
        parent::tearDown();
    }

    public function test_name(): void {
        $this->assertEquals('mojomatch', $this->qtype->name());
    }

    public function test_can_analyse_responses(): void {
        $this->assertTrue($this->qtype->can_analyse_responses());
    }

    public function test_extra_question_fields(): void {
        // Column order matters: question_type builds its SELECT and its INSERT from this list, so a
        // new option has to be appended here and added to install.xml in the same shape.
        $this->assertEquals(
            ['qtype_mojomatch_options', 'usecase', 'matchtype', 'variant', 'transforms', 'workspaceid', 'qorder'],
            $this->qtype->extra_question_fields()
        );
    }

    public function test_get_random_guess_score_no_star(): void {
        $questiondata = test_question_maker::get_question_data('mojomatch');
        $this->assertEquals(0, $this->qtype->get_random_guess_score($questiondata));
    }

    public function test_get_random_guess_score(): void {
        $questiondata = test_question_maker::get_question_data('mojomatch');
        $questiondata->options->answers[14] = new question_answer(14, '*', 0.1, '', FORMAT_HTML);

        $this->assertEquals(0.1, $this->qtype->get_random_guess_score($questiondata));
    }

    public function test_get_possible_responses_no_star(): void {
        $questiondata = test_question_maker::get_question_data('mojomatch');

        $this->assertEquals([
            $questiondata->id => [
                13 => new question_possible_response('cp', 1.0),
                0 => new question_possible_response(get_string('didnotmatchanyanswer', 'question'), 0),
                null => question_possible_response::no_response(),
            ],
        ], $this->qtype->get_possible_responses($questiondata));
    }

    public function test_get_possible_responses(): void {
        $questiondata = test_question_maker::get_question_data('mojomatch');
        $questiondata->options->answers[14] = new question_answer(14, '*', 0.0, '', FORMAT_HTML);

        // A '*' answer catches everything, so there is no "did not match any answer" bucket.
        $this->assertEquals([
            $questiondata->id => [
                13 => new question_possible_response('cp', 1.0),
                14 => new question_possible_response('*', 0.0),
                null => question_possible_response::no_response(),
            ],
        ], $this->qtype->get_possible_responses($questiondata));
    }

    public function test_save_defaults_for_new_questions_remembers_the_options_the_form_sent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $fromform = new \stdClass();
        $fromform->defaultmark = '1';
        $fromform->penalty = '0.3333333';
        $fromform->usecase = '1';
        $fromform->matchtype = '2';
        $fromform->variant = '3';
        $fromform->transforms = '0';
        $fromform->workspaceid = 'a-workspace';

        $this->qtype->save_defaults_for_new_questions($fromform);

        $this->assertEquals('1', get_user_preferences('qtype_mojomatch_usecase'));
        $this->assertEquals('2', get_user_preferences('qtype_mojomatch_matchtype'));
        $this->assertEquals('3', get_user_preferences('qtype_mojomatch_variant'));
        $this->assertEquals('0', get_user_preferences('qtype_mojomatch_transforms'));
        $this->assertEquals('a-workspace', get_user_preferences('qtype_mojomatch_workspaceid'));
        // The generic elements are still handled by the parent.
        $this->assertEquals('1', get_user_preferences('qtype_mojomatch_defaultmark'));
    }

    public function test_save_defaults_for_new_questions_skips_fields_the_form_does_not_carry(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // The qorder option is in extra_question_fields() but has no form element: it records
        // where a question sits inside an imported TopoMojo challenge, so it arrives here unset.
        // Handing that null to the typed set_default_value() threw, and it threw after the
        // question had been written, which rolled the whole save back and left the author on
        // an exception page with no question created.
        $fromform = new \stdClass();
        $fromform->usecase = '0';

        $this->qtype->save_defaults_for_new_questions($fromform);

        $this->assertEquals('0', get_user_preferences('qtype_mojomatch_usecase'));
        $this->assertNull(get_user_preferences('qtype_mojomatch_qorder'));
        $this->assertNull(get_user_preferences('qtype_mojomatch_matchtype'));
    }

    public function test_save_question_options_rejects_no_full_credit_answer(): void {
        // The check runs before anything is written, so a rejected question leaves no partial row.
        $question = new \stdClass();
        $question->answer = ['cp', 'copy'];
        $question->fraction = ['0.5', '0.25'];

        $result = $this->qtype->save_question_options($question);

        $this->assertEquals(get_string('fractionsnomax', 'question', 50), $result->error);
    }
}
