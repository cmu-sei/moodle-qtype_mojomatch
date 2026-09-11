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

use qtype_mojomatch_question;
use question_answer;
use question_state;
use test_question_maker;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/mojomatch/question.php');

/**
 * Unit tests for the mojomatch question definition class.
 *
 * @package    qtype_mojomatch
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \qtype_mojomatch_question
 * @covers \mojomatch_grading_strategy
 */
final class question_test extends \advanced_testcase {
    /**
     * Gives compare_response_with_answer() a pagetype to read.
     *
     * It branches on $PAGE->pagetype, and a page with none set resolves one from the request, which
     * a CLI test does not have.
     *
     * @param string $pagetype the pagetype to set.
     */
    protected function set_pagetype(string $pagetype): void {
        global $PAGE;
        $PAGE->set_url('/');
        $PAGE->set_pagetype($pagetype);
    }

    public function test_get_expected_data(): void {
        $question = test_question_maker::make_question('mojomatch');
        $this->assertEquals(['answer' => PARAM_RAW_TRIMMED], $question->get_expected_data());
    }

    public function test_summarise_response(): void {
        $question = test_question_maker::make_question('mojomatch');
        $this->assertEquals('cp', $question->summarise_response(['answer' => 'cp']));
        $this->assertNull($question->summarise_response([]));
    }

    public function test_un_summarise_response(): void {
        $question = test_question_maker::make_question('mojomatch');
        $this->assertEquals(['answer' => 'cp'], $question->un_summarise_response('cp'));
        $this->assertEquals([], $question->un_summarise_response(''));
    }

    public function test_is_complete_response(): void {
        $question = test_question_maker::make_question('mojomatch');
        $this->assertFalse($question->is_complete_response([]));
        $this->assertFalse($question->is_complete_response(['answer' => '']));
        $this->assertTrue($question->is_complete_response(['answer' => 'cp']));
        // A literal zero is a real answer, even though PHP calls it falsy.
        $this->assertTrue($question->is_complete_response(['answer' => '0']));
    }

    public function test_get_validation_error(): void {
        $question = test_question_maker::make_question('mojomatch');
        $this->assertEquals('', $question->get_validation_error(['answer' => 'cp']));
        $this->assertEquals(
            get_string('pleaseenterananswer', 'qtype_mojomatch'),
            $question->get_validation_error(['answer' => ''])
        );
    }

    public function test_is_same_response(): void {
        $question = test_question_maker::make_question('mojomatch');

        $this->assertTrue($question->is_same_response([], ['answer' => '']));
        $this->assertTrue($question->is_same_response(['answer' => 'cp'], ['answer' => 'cp']));
        $this->assertFalse($question->is_same_response(['answer' => 'cp'], ['answer' => 'mv']));
        $this->assertFalse($question->is_same_response(['answer' => ''], ['answer' => 'cp']));
    }

    public function test_get_correct_response(): void {
        $question = test_question_maker::make_question('mojomatch');
        $this->assertEquals(['answer' => 'cp'], $question->get_correct_response());
    }

    public function test_get_right_answer_summary(): void {
        $question = test_question_maker::make_question('mojomatch');
        $this->assertEquals('cp', $question->get_right_answer_summary());
    }

    public function test_clean_response(): void {
        $question = test_question_maker::make_question('mojomatch');

        $this->assertEquals('cp', $question->clean_response('cp'));
        // Unescaped asterisks become spaces; an escaped \* is a literal one.
        $this->assertEquals('x*y', $question->clean_response('*x\*y*'));
        $this->assertEquals('a b', $question->clean_response('a*b'));
    }

    /**
     * Tests each matchtype's comparison, outside preview and with no transforms.
     *
     * @dataProvider compare_string_with_matchtype_provider
     *
     * @param bool $expected whether the response should match.
     * @param string $string the response.
     * @param string $pattern the answer.
     * @param bool $ignorecase whether to compare case-insensitively.
     * @param string $matchtype the matchtype, as stored in qtype_mojomatch_options.
     */
    public function test_compare_string_with_matchtype(
        bool $expected,
        string $string,
        string $pattern,
        bool $ignorecase,
        string $matchtype
    ): void {
        $matched = qtype_mojomatch_question::compare_string_with_matchtype($string, $pattern, $ignorecase, $matchtype, 0, 0, null);
        $this->assertEquals($expected, (bool)$matched);
    }

    /**
     * Cases for {@see test_compare_string_with_matchtype()}.
     *
     * @return array[] expected, string, pattern, ignorecase, matchtype.
     */
    public static function compare_string_with_matchtype_provider(): array {
        return [
            // Matchtype 0, matchalpha: equality after dropping every non-alphanumeric character.
            'matchalpha exact' => [true, 'cp', 'cp', false, '0'],
            'matchalpha ignores punctuation' => [true, 'c-p!', 'cp', false, '0'],
            'matchalpha case sensitive' => [false, 'CP', 'cp', false, '0'],
            'matchalpha case insensitive' => [true, 'CP', 'cp', true, '0'],
            'matchalpha wrong answer' => [false, 'mv', 'cp', true, '0'],

            // Matchtype 1, match: same comparison as matchalpha, reached by a different code path.
            'match exact' => [true, 'cp', 'cp', false, '1'],
            'match ignores whitespace' => [true, ' cp ', 'cp', false, '1'],
            'match case sensitive' => [false, 'CP', 'cp', false, '1'],
            'match case insensitive' => [true, 'CP', 'cp', true, '1'],
            'match wrong answer' => [false, 'copy', 'cp', true, '1'],

            // Matchtype 2, matchany: the response has to be a substring of the answer, not the
            // other way round.
            'matchany substring of answer' => [true, 'cp', 'use cp to copy a file', false, '2'],
            'matchany whole answer' => [true, 'cp', 'cp', false, '2'],
            'matchany response longer than answer' => [false, 'cp file', 'cp', false, '2'],
            'matchany case insensitive' => [true, 'CP', 'use cp to copy a file', true, '2'],

            // Matchtype 3, wildcard: * in the answer matches any run of characters.
            'wildcard leading and trailing' => [true, 'the cp command', '*cp*', false, '3'],
            'wildcard anchored' => [false, 'the cp command', 'cp*', false, '3'],
            'wildcard no match' => [false, 'the mv command', '*cp*', false, '3'],
            'wildcard case insensitive' => [true, 'The CP Command', '*cp*', true, '3'],
            'wildcard escaped asterisk' => [true, 'x*y', 'x\*y', false, '3'],
        ];
    }

    public function test_compare_string_with_matchtype_unknown_matchtype(): void {
        // Every branch is an explicit matchtype comparison, so an unrecognised one grades nothing.
        $matched = qtype_mojomatch_question::compare_string_with_matchtype('cp', 'cp', false, '9', 0, 0, null);
        $this->assertNull($matched);
    }

    public function test_compare_string_with_matchtype_transforms_in_preview(): void {
        $response = 'what is the ##hostname## address';
        $answer = 'the ##host## address';

        // In preview, ##token## placeholders are stripped from both sides before comparing, so an
        // answer template matches the transformed question text it came from.
        $matched = qtype_mojomatch_question::compare_string_with_matchtype($response, $answer, false, '1', 1, 0, 1);
        $this->assertTrue((bool)$matched);

        // Outside preview, or with transforms off, the placeholders are compared literally.
        $matched = qtype_mojomatch_question::compare_string_with_matchtype($response, $answer, false, '1', 0, 0, 1);
        $this->assertFalse((bool)$matched);

        $matched = qtype_mojomatch_question::compare_string_with_matchtype($response, $answer, false, '1', 1, 0, null);
        $this->assertFalse((bool)$matched);
    }

    public function test_compare_response_with_answer(): void {
        $this->set_pagetype('mod-topomojo-attempt');
        $question = test_question_maker::make_question('mojomatch');
        $answer = $question->answers[13];

        $this->assertTrue($question->compare_response_with_answer(['answer' => 'cp'], $answer));
        $this->assertTrue($question->compare_response_with_answer(['answer' => 'CP'], $answer));
        $this->assertFalse($question->compare_response_with_answer(['answer' => 'mv'], $answer));

        // No response at all is not a match, rather than an error.
        $this->assertFalse($question->compare_response_with_answer([], $answer));
        $this->assertFalse($question->compare_response_with_answer(['answer' => null], $answer));
    }

    public function test_compare_response_with_answer_preview_applies_transforms(): void {
        $question = test_question_maker::make_question('mojomatch');
        $question->transforms = 1;
        $answer = new question_answer(14, 'the ##host## address', 1.0, '', FORMAT_HTML);
        $response = ['answer' => 'what is the ##hostname## address'];

        $this->set_pagetype('question-bank-previewquestion-preview');
        $this->assertTrue($question->compare_response_with_answer($response, $answer));

        $this->set_pagetype('mod-topomojo-attempt');
        $this->assertFalse($question->compare_response_with_answer($response, $answer));
    }

    public function test_grade_attempt(): void {
        $this->set_pagetype('mod-topomojo-attempt');
        $question = test_question_maker::make_question('mojomatch');
        $answer = $question->answers[13];

        $this->assertSame($answer, $question->grade_attempt(['answer' => 'cp'], $answer));
        $this->assertNull($question->grade_attempt(['answer' => 'mv'], $answer));
    }

    public function test_grade_response(): void {
        $this->set_pagetype('mod-topomojo-attempt');
        $question = test_question_maker::make_question('mojomatch');

        $this->assertEquals([1, question_state::$gradedright], $question->grade_response(['answer' => 'cp']));
        $this->assertEquals([0, question_state::$gradedwrong], $question->grade_response(['answer' => 'mv']));
    }

    public function test_grade_response_qa_falls_back_to_the_static_answer(): void {
        $this->set_pagetype('mod-topomojo-attempt');
        $question = test_question_maker::make_question('mojomatch');
        $qa = test_question_maker::get_a_qa($question);

        // With no TopoMojo credentials configured there is no live answer to fetch, so the answer
        // stored on the question grades the response. setup() debugs its way to that conclusion.
        $this->assertEquals([1, question_state::$gradedright], $question->grade_response_qa(['answer' => 'cp'], $qa));
        $this->resetDebugging();

        $this->assertEquals([0, question_state::$gradedwrong], $question->grade_response_qa(['answer' => 'mv'], $qa));
        $this->resetDebugging();
    }

    public function test_make_behaviour(): void {
        $question = test_question_maker::make_question('mojomatch');
        $qa = test_question_maker::get_a_qa($question);

        // Since qbehaviour_mojomatch is a declared dependency, it is always the behaviour used.
        $this->assertInstanceOf('qbehaviour_mojomatch', $question->make_behaviour($qa, 'deferredfeedback'));
    }

    public function test_get_question_definition_for_external_rendering(): void {
        $question = test_question_maker::make_question('mojomatch');
        $qa = test_question_maker::get_a_qa($question);

        $options = new \question_display_options();
        $this->assertNull($question->get_question_definition_for_external_rendering($qa, $options));
    }
}
