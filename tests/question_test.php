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
            // Every case below is the behaviour of the TopoMojo grader the matchtype stands for,
            // in QuestionSpec::IsMatch() (GamespaceExtensions.cs). An answer is a '|' separated
            // list of alternatives with its spaces dropped, whichever grader reads it.

            // Matchtype 0, matchAlpha: the first alternative only, compared after dropping every
            // character that is not an ASCII letter or digit.
            'matchalpha exact' => [true, 'cp', 'cp', false, '0'],
            'matchalpha ignores punctuation' => [true, 'c-p!', 'cp', false, '0'],
            'matchalpha keeps digits' => [true, 'flag{1234}', 'flag1234', false, '0'],
            'matchalpha case sensitive' => [false, 'CP', 'cp', false, '0'],
            'matchalpha case insensitive' => [true, 'CP', 'cp', true, '0'],
            'matchalpha wrong answer' => [false, 'mv', 'cp', true, '0'],
            'matchalpha first alternative' => [true, 'cp', 'cp|copy', false, '0'],
            'matchalpha ignores later alternatives' => [false, 'copy', 'cp|copy', false, '0'],

            // Matchtype 1, matchAll: every alternative has to appear among the words of the
            // response, which are split on space, comma, semicolon, colon, pipe and tab.
            'matchall every alternative present' => [true, 'use cp then mv', 'cp|mv', false, '1'],
            'matchall order is free' => [true, 'mv, cp', 'cp|mv', false, '1'],
            'matchall other separators' => [true, 'cp;mv', 'cp|mv', false, '1'],
            'matchall one alternative missing' => [false, 'use cp', 'cp|mv', false, '1'],
            'matchall single alternative' => [true, 'copy a file with cp', 'cp', false, '1'],
            'matchall needs a whole word' => [false, 'cpio', 'cp', false, '1'],
            'matchall case sensitive' => [false, 'CP MV', 'cp|mv', false, '1'],
            'matchall case insensitive' => [true, 'CP MV', 'cp|mv', true, '1'],

            // Matchtype 2, matchAny: the response, with its spaces dropped, has to equal one of
            // the alternatives.
            'matchany first alternative' => [true, 'cp', 'cp|copy', false, '2'],
            'matchany later alternative' => [true, 'copy', 'cp|copy', false, '2'],
            'matchany ignores spaces in the response' => [true, 'c p', 'cp', false, '2'],
            'matchany response carrying the answer' => [false, 'cp file', 'cp', false, '2'],
            'matchany answer carrying the response' => [false, 'cp', 'use cp to copy a file', false, '2'],
            'matchany case insensitive' => [true, 'CP', 'cp|copy', true, '2'],
            'matchany wrong answer' => [false, 'mv', 'cp|copy', false, '2'],

            // Matchtype 3, match: the first alternative, compared whole.
            'match exact' => [true, 'cp', 'cp', false, '3'],
            'match ignores spaces' => [true, ' c p ', 'cp', false, '3'],
            'match ignores spaces in the answer' => [true, 'cpfile', 'cp file', false, '3'],
            'match wrong answer' => [false, 'copy', 'cp', false, '3'],
            'match case insensitive' => [true, 'CP', 'cp', true, '3'],
            'match first alternative' => [false, 'copy', 'cp|copy', false, '3'],

            // Matchtype 3 also honours '*' as a wildcard, which TopoMojo does not: the edit
            // form's help has always documented it, and an answer without one is compared
            // exactly as TopoMojo compares it.
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

        // In preview, ##token## placeholders are stripped from both sides, and a response that
        // carries what is left of the answer is accepted whatever the matchtype: only the lab
        // can substitute the placeholders, so there is nothing to grade properly here.
        foreach (['0', '1', '2', '3'] as $matchtype) {
            $matched = qtype_mojomatch_question::compare_string_with_matchtype(
                $response, $answer, false, $matchtype, 1, 0, 1);
            $this->assertTrue((bool)$matched, "matchtype {$matchtype} should accept the answer template");
        }

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

    /**
     * Builds a gamespace challenge in the shape get_gamespace_challenge() returns it.
     *
     * @param string $workspaceid The gamespace's workspace.
     * @param int $variant The 0 based variant index, as TopoMojo's GameState reports it.
     * @param array $sections One array of [text, answer] pairs per section.
     * @return \stdClass
     */
    protected function make_challenge(string $workspaceid, int $variant, array $sections): \stdClass {
        $challenge = new \stdClass();
        $challenge->workspaceId = $workspaceid;
        $challenge->variant = $variant;
        $challenge->challenge = new \stdClass();
        $challenge->challenge->sections = [];

        foreach ($sections as $questions) {
            $section = new \stdClass();
            $section->questions = [];
            foreach ($questions as [$text, $answer]) {
                $question = new \stdClass();
                $question->text = $text;
                $question->answer = $answer;
                $section->questions[] = $question;
            }
            $challenge->challenge->sections[] = $section;
        }

        return $challenge;
    }

    /**
     * Calls the protected resolver on a question.
     *
     * @param qtype_mojomatch_question $question the question doing the resolving.
     * @param \stdClass|null $challenge the challenge to resolve against.
     * @return \stdClass|null the matched TopoMojo question.
     */
    protected function find_gamespace_question($question, $challenge) {
        return (function ($challenge) {
            return $this->find_gamespace_question($challenge);
        })->call($question, $challenge);
    }

    /**
     * Makes a question that claims a known place in a known challenge.
     *
     * @param string $workspaceid the workspace the question was imported from.
     * @param int $variant the 1 based variant, as qtype_mojomatch_options stores it.
     * @param int|null $qorder the 1 based position, as qtype_mojomatch_options stores it.
     * @return qtype_mojomatch_question
     */
    protected function make_imported_question(string $workspaceid, int $variant, ?int $qorder) {
        $question = test_question_maker::make_question('mojomatch');
        $question->workspaceid = $workspaceid;
        $question->variant = $variant;
        $question->qorder = $qorder;
        return $question;
    }

    public function test_find_gamespace_question_resolves_on_workspace_variant_and_qorder(): void {
        // The qorder counts the variant's questions flattened across its sections, so the
        // third question is the first one of the second section.
        $question = $this->make_imported_question('ws-1', 2, 3);
        $challenge = $this->make_challenge('ws-1', 1, [
            [['first', 'a'], ['second', 'b']],
            [['third', 'c'], ['fourth', 'd']],
        ]);

        $found = $this->find_gamespace_question($question, $challenge);
        $this->assertEquals('c', $found->answer);
    }

    public function test_find_gamespace_question_ignores_matching_question_text(): void {
        // The regression this replaced: resolution used to be by question text, so the
        // same text appearing at another position - across variants of one workspace, or
        // in an unrelated challenge - handed back that question's answer. Position is
        // what identifies the question now, and the text is not consulted at all.
        $question = $this->make_imported_question('ws-1', 1, 1);
        $challenge = $this->make_challenge('ws-1', 0, [
            [['Which command copies a file?', 'cp'], ['Which command copies a file?', 'scp']],
        ]);

        $found = $this->find_gamespace_question($question, $challenge);
        $this->assertEquals('cp', $found->answer);
    }

    public function test_find_gamespace_question_refuses_to_guess(): void {
        $challenge = $this->make_challenge('ws-1', 0, [[['first', 'a'], ['second', 'b']]]);

        // No qorder: imported before it was recorded, so the question cannot be placed.
        $noqorder = $this->make_imported_question('ws-1', 1, null);
        $this->assertNull($this->find_gamespace_question($noqorder, $challenge));
        $this->assertDebuggingCalled();

        // A gamespace for some other workspace.
        $otherworkspace = $this->make_imported_question('ws-2', 1, 1);
        $this->assertNull($this->find_gamespace_question($otherworkspace, $challenge));
        $this->assertDebuggingCalled();

        // A gamespace running a different variant. The variant is stored 1 based and
        // TopoMojo reports it 0 based, so variant 1 here means the challenge's variant 0.
        $othervariant = $this->make_imported_question('ws-1', 2, 1);
        $this->assertNull($this->find_gamespace_question($othervariant, $challenge));
        $this->assertDebuggingCalled();

        // A position the challenge does not have.
        $pasttheend = $this->make_imported_question('ws-1', 1, 3);
        $this->assertNull($this->find_gamespace_question($pasttheend, $challenge));
        $this->assertDebuggingCalled();

        // No challenge at all, which is what an expired gamespace leaves.
        $placeable = $this->make_imported_question('ws-1', 1, 1);
        $this->assertNull($this->find_gamespace_question($placeable, null));
    }

    public function test_grade_response_qa_grades_without_a_live_answer_when_answers_are_not_singular(): void {
        $this->set_pagetype('mod-topomojo-attempt');
        $question = test_question_maker::make_question('mojomatch');

        // Two answers is ordinary authoring: the edit form offers more answer rows. There
        // is then no single answer for a live TopoMojo answer to replace, and reading an
        // unassigned $rightanswer used to throw a TypeError out of grade_attempt(), so
        // the submission failed instead of being graded. Grade through the strategy.
        $question->answers = [
            13 => new question_answer(13, 'cp', 1.0, '', FORMAT_HTML),
            14 => new question_answer(14, 'copy', 0.5, '', FORMAT_HTML),
        ];
        $qa = test_question_maker::get_a_qa($question);

        $this->assertEquals([1, question_state::$gradedright], $question->grade_response_qa(['answer' => 'cp'], $qa));
        $this->assertDebuggingCalled();

        $this->assertEquals(
            [0.5, question_state::graded_state_for_fraction(0.5)],
            $question->grade_response_qa(['answer' => 'copy'], $qa)
        );
        $this->assertDebuggingCalled();

        $this->assertEquals([0, question_state::$gradedwrong], $question->grade_response_qa(['answer' => 'mv'], $qa));
        $this->assertDebuggingCalled();

        // No answers at all is wrong, rather than fatal.
        $question->answers = [];
        $this->assertEquals([0, question_state::$gradedwrong], $question->grade_response_qa(['answer' => 'cp'], $qa));
        $this->assertDebuggingCalled();
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

    /**
     * Read the private options of a \curl instance.
     *
     * @param \curl $client Client to inspect.
     * @return array The cURL options in force.
     */
    private function curl_options(\curl $client): array {
        $options = \Closure::bind(
            static function (\curl $client): array {
                return (array) $client->options;
            },
            null,
            \curl::class
        );
        return $options($client);
    }

    /**
     * The client that fetches the correct answer decides the grade, so it must not accept
     * an unverified certificate or wait on a stalled API for the length of a page request.
     */
    public function test_the_api_client_verifies_the_certificate_and_bounds_the_request(): void {
        $this->resetAfterTest();

        // What \curl hands out by default, and what configure_api_client() is for.
        $client = new \curl();
        $this->assertSame(0, $this->curl_options($client)['CURLOPT_SSL_VERIFYPEER']);
        $this->assertArrayNotHasKey('CURLOPT_TIMEOUT', $this->curl_options($client));

        qtype_mojomatch_question::configure_api_client($client);

        $options = $this->curl_options($client);
        $this->assertSame(1, $options['CURLOPT_SSL_VERIFYPEER']);
        $this->assertSame(2, $options['CURLOPT_SSL_VERIFYHOST']);
        $this->assertSame(5, $options['CURLOPT_CONNECTTIMEOUT']);
        $this->assertSame(15, $options['CURLOPT_TIMEOUT']);
    }

    /**
     * Core strips an Authorization header on a cross-host redirect but not x-api-key, so
     * the key would otherwise go to whichever host a redirect named.
     */
    public function test_an_api_key_client_does_not_follow_redirects(): void {
        $this->resetAfterTest();

        $bearer = qtype_mojomatch_question::configure_api_client(new \curl());
        $this->assertSame(1, $this->curl_options($bearer)['CURLOPT_FOLLOWLOCATION']);

        $apikey = qtype_mojomatch_question::configure_api_client(new \curl(), true);
        $this->assertSame(0, $this->curl_options($apikey)['CURLOPT_FOLLOWLOCATION']);
    }

    /**
     * And setup() is where that happens, so grading never reaches an unconfigured client.
     */
    public function test_setup_returns_a_configured_api_key_client(): void {
        $this->resetAfterTest();
        set_config('enableapikey', 1, 'topomojo');
        set_config('apikey', 'test-api-key-12345', 'topomojo');

        $question = test_question_maker::make_question('mojomatch');
        $options = $this->curl_options($question->setup());

        $this->assertSame(1, $options['CURLOPT_SSL_VERIFYPEER']);
        $this->assertSame(2, $options['CURLOPT_SSL_VERIFYHOST']);
        $this->assertSame(5, $options['CURLOPT_CONNECTTIMEOUT']);
        $this->assertSame(15, $options['CURLOPT_TIMEOUT']);
        $this->assertSame(0, $options['CURLOPT_FOLLOWLOCATION']);
    }

    /**
     * setup() returns null when nothing is configured, and configuring that must not fatal.
     */
    public function test_configuring_a_missing_client_is_harmless(): void {
        $this->resetAfterTest();

        $this->assertNull(qtype_mojomatch_question::configure_api_client(null));
        $this->assertFalse(qtype_mojomatch_question::configure_api_client(false));
    }
}
