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
 * mojomatch question definition class.
 *
 * @package    qtype
 * @subpackage mojomatch
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/questionbase.php');

/**
 * Represents a mojomatch question.
 *
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_mojomatch_question extends question_graded_by_strategy
    implements question_response_answer_comparer {
    /** @var boolean whether answers should be graded case-sensitively. */
    public $usecase;
    public $workspaceid;
    public $matchtype;
    public $variant;
    public $transforms;
    public $qorder;

    /** @var array of question_answer. */
    public $answers = array();

    public function __construct() {
        parent::__construct(new mojomatch_grading_strategy($this));
    }

    public function get_expected_data() {
        return array('answer' => PARAM_RAW_TRIMMED);
    }

    public function summarise_response(array $response) {
        if (isset($response['answer'])) {
            return $response['answer'];
        } else {
            return null;
        }
    }

    public function un_summarise_response(string $summary) {
        if (!empty($summary)) {
            return ['answer' => $summary];
        } else {
            return [];
        }
    }

    public function is_complete_response(array $response) {
        return array_key_exists('answer', $response) &&
                ($response['answer'] || $response['answer'] === '0');
    }

    public function get_validation_error(array $response) {
        if ($this->is_gradable_response($response)) {
            return '';
        }
        return get_string('pleaseenterananswer', 'qtype_mojomatch');
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        return question_utils::arrays_same_at_key_missing_is_blank(
                $prevresponse, $newresponse, 'answer');
    }

    public function get_answers() {
        return $this->answers;
    }

    /**
     * Applies certificate verification and bounded request times to a TopoMojo API client.
     *
     * \curl - which \core\oauth2\client also extends - sets CURLOPT_SSL_VERIFYPEER to 0,
     * so without this the API key or the bearer token goes out over a TLS connection
     * nobody has authenticated. The answer this client fetches decides whether a student's
     * response is graded correct, so a host that can answer for the API URL both gets the
     * credential and picks the grade.
     *
     * \curl has no default CURLOPT_TIMEOUT either, and this call happens while a quiz page
     * is being submitted, so an API that accepts the connection and then stalls holds the
     * student's session lock until PHP-FPM gives up.
     *
     * mod_topomojo's locallib.php carries the same settings in
     * topomojo_configure_api_client(). They are repeated here rather than shared because a
     * question is graded without that file loaded, and pulling a 2,000-line locallib into
     * question rendering to reach four cURL options would cost more than it saves.
     *
     * @param curl|\core\oauth2\client|null $client Client to configure, or null from a failed setup.
     * @param bool $bearsapikey Whether the client carries the API key in a request header.
     * @return curl|\core\oauth2\client|null The same client.
     */
    public static function configure_api_client($client, $bearsapikey = false) {
        if (!$client) {
            return $client;
        }

        $options = [
            'CURLOPT_SSL_VERIFYPEER' => 1,
            'CURLOPT_SSL_VERIFYHOST' => 2,
            'CURLOPT_CONNECTTIMEOUT' => 5,
            'CURLOPT_TIMEOUT' => 15,
        ];

        if ($bearsapikey) {
            // Core follows up to ten redirects, and strips the request headers on a
            // cross-host one only if they are named Authorization (lib/filelib.php). That
            // filter does not match x-api-key, so a redirect hands the key to whichever
            // host the response chose. The API has no reason to redirect, so a 3xx is
            // reported to the caller as a 3xx rather than followed.
            $options['CURLOPT_FOLLOWLOCATION'] = 0;
        }

        $client->setopt($options);

        return $client;
    }

    function setup() {
        if (get_config('topomojo', 'enableapikey')) {
            // Use external API key
            $xapikey = get_config('topomojo', 'apikey');

            if (empty($xapikey)) {
                debugging("TopoMojo API key is enabled but not set in config.", DEBUG_DEVELOPER);
                return null;
            }

            $client = new curl();
            $headers = [
                'x-api-key: ' . $xapikey,
                'Content-Type: application/json'
            ];
            $client->setHeader($headers);

            return self::configure_api_client($client, true);

        } else {
            // Use OAuth2 system client
            if (get_config('topomojo', 'enableoauth')) {
                $issuerid = get_config('topomojo', 'issuerid');
            }
            if (empty($issuerid)) {
                debugging("OAuth2 issuer not set and API key is disabled.", DEBUG_DEVELOPER);
                return null;
            }

            $issuer = \core\oauth2\api::get_issuer($issuerid);
            if (!$issuer) {
                debugging("Unable to load OAuth2 issuer with ID {$issuerid}", DEBUG_DEVELOPER);
                return null;
            }

            try {
                $client = \core\oauth2\api::get_system_oauth_client($issuer);
                if (!$client) {
                    debugging("Failed to initialize system OAuth2 client.", DEBUG_DEVELOPER);
                    return null;
                }
            } catch (Exception $e) {
                debugging("OAuth2 client error: " . $e->getMessage(), DEBUG_DEVELOPER);
                return null;
            }

            return self::configure_api_client($client);
        }
    }

    public function compare_response_with_answer(array $response, question_answer $answer) {
        if (!array_key_exists('answer', $response) || is_null($response['answer'])) {
            return false;
        }
        //echo "comparing response " . $response['answer'] . " to $answer->answer<br>";
        $preview = 0;
        $viewattempt = 0;
        global $PAGE;
        if ($PAGE->pagetype == 'question-bank-previewquestion-preview') {
            $preview = 1;
        } else if ($PAGE->pagetype == 'mod-topomojo-viewattempt') {
            $viewattempt = 1;
        }

        return self::compare_string_with_matchtype(
                $response['answer'], $answer->answer, !$this->usecase, $this->matchtype, $preview, $viewattempt, $this->transforms);
    }

    public static function compare_string_with_matchtype($string, $pattern, $ignorecase, $matchtype, $preview, $viewattempt, $transforms) {

        if (!function_exists('str_contains')) {
            function str_contains( $haystack, $needle) {
                return $needle !== '' && mb_strpos($haystack, $needle) !== false;
            }
        }

        //echo "compare_string_with_matchtype $string $pattern $matchtype<br>";
        $pattern = self::safe_normalize($pattern);
        $string = self::safe_normalize($string);
        if ($transforms && $preview) {
            // The answer holds ##token## placeholders that only the lab substitutes, so a
            // preview cannot grade it properly. Accept a response that carries what is left of
            // the answer once the placeholders are dropped, whatever the matchtype: replacing
            // the response with the answer here, as this used to, only happened to pass because
            // every comparison below then compared the answer with itself.
            $regexp = "/##[a-zA-Z0-9]*##/";
            $string = preg_replace($regexp, '', $string);
            $pattern = preg_replace($regexp, '', $pattern);
            if (str_contains($string, $pattern)) {
                return true;
            }
        } else if ($viewattempt == 1) {
            //echo "how can compare during view attempt<br>";
            //echo "we shouldnt even be here if its already been graded<br>";
            //echo "viewattempt variable does not get used<br>";
            // TODO should this throw an error or a debug message?
        }
        // Each branch below is a port of one arm of TopoMojo's QuestionSpec::IsMatch(), in
        // GamespaceExtensions.cs: the lab is graded by that code when the student submits to
        // TopoMojo, and by this code when Moodle grades the attempt, so the two have to agree
        // or the same response scores differently in the two places. The matchtype numbers are
        // the plugin's own encoding of AnswerGrader, assigned by mod_topomojo's questionmanager
        // when it imports a challenge: 0 matchAlpha, 1 matchAll, 2 matchAny, 3 match.
        //
        // TopoMojo drops every space from the answer and splits it on '|' into the alternatives
        // it will accept, so that is shared by all four.
        $answers = self::answer_alternatives($pattern);
        $response = trim($string);

        if ($matchtype == '0') {
            // matchAlpha: the first alternative only, compared after dropping every character
            // that is not an ASCII letter or digit from both sides.
            $expected = preg_replace('/[^A-Za-z0-9]/', '', reset($answers));
            $given = preg_replace('/[^A-Za-z0-9]/', '', $response);
            return self::strings_equal($expected, $given, $ignorecase);
        } else if ($matchtype == '1') {
            // matchAll: every alternative has to appear among the words of the response, which
            // TopoMojo splits on space, comma, semicolon, colon, pipe and tab. Extra words in
            // the response are allowed. Comparing distinct alternatives against the response's
            // words mirrors Intersect(), which drops duplicates: an answer that lists the same
            // word twice cannot be satisfied there either.
            $words = preg_split('/[ ,;:|\t]+/', $response, -1, PREG_SPLIT_NO_EMPTY);
            if ($ignorecase) {
                $answers = array_map([self::class, 'fold_case'], $answers);
                $words = array_map([self::class, 'fold_case'], $words);
            }
            return count(array_intersect(array_unique($answers), $words)) === count($answers);
        } else if ($matchtype == '2') {
            // matchAny: the response, with its spaces dropped, has to equal one of the
            // alternatives. It is not a substring test - a response that merely contains an
            // alternative is wrong here, as it is in TopoMojo.
            $given = str_replace(' ', '', $response);
            if ($ignorecase) {
                $answers = array_map([self::class, 'fold_case'], $answers);
                $given = self::fold_case($given);
            }
            return in_array($given, $answers, true);
        } else if ($matchtype == '3') {
            // match: the first alternative only, compared whole. TopoMojo compares it for
            // equality; Moodle has always also honoured '*' as a wildcard here, which the edit
            // form's help documents, so that is kept - an answer without a '*' behaves exactly
            // as TopoMojo grades it.
            $expected = reset($answers);
            $given = str_replace(' ', '', $response);

            if (!str_contains($expected, '*')) {
                return self::strings_equal($expected, $given, $ignorecase);
            }

            // Break the string on non-escaped runs of asterisks.
            // ** is equivalent to *, but people were doing that, and with many *s it breaks preg.
            $bits = preg_split('/(?<!\\\\)\*+/', $expected);

            // Escape regexp special characters in the bits.
            $escapedbits = array();
            foreach ($bits as $bit) {
                $escapedbits[] = preg_quote(str_replace('\*', '*', $bit), '|');
            }
            // Put it back together to make the regexp.
            $regexp = '|^' . implode('.*', $escapedbits) . '$|u';

            // Make the match insensitive if requested to.
            if ($ignorecase) {
                $regexp .= 'i';
            }
            return preg_match($regexp, $given);
        }
    }

    /**
     * Split a stored answer the way TopoMojo does before grading against it.
     *
     * @param string $answer the answer as authored or imported.
     * @return array the alternatives it accepts, in order, with spaces dropped.
     */
    protected static function answer_alternatives($answer) {
        return explode('|', str_replace(' ', '', $answer));
    }

    /**
     * Lower-case a string for a case-insensitive comparison.
     *
     * @param string $string the string.
     * @return string the string, lower-cased.
     */
    protected static function fold_case($string) {
        return \core_text::strtolower($string);
    }

    /**
     * Compare two strings, optionally ignoring case.
     *
     * @param string $expected the answer.
     * @param string $given the response.
     * @param bool $ignorecase whether case is unimportant.
     * @return bool whether they are the same string.
     */
    protected static function strings_equal($expected, $given, $ignorecase) {
        if ($ignorecase) {
            return self::fold_case($expected) === self::fold_case($given);
        }
        return $expected === $given;
    }

    /**
     * Normalise a UTf-8 string to FORM_C, avoiding the pitfalls in PHP's
     * normalizer_normalize function.
     * @param string $string the input string.
     * @return string the normalised string.
     */
    protected static function safe_normalize($string) {
        if ($string === '') {
            return '';
        }

        if (!function_exists('normalizer_normalize')) {
            return $string;
        }

        $normalised = normalizer_normalize($string, Normalizer::FORM_C);
        if (is_null($normalised)) {
            // An error occurred in normalizer_normalize, but we have no idea what.
            debugging('Failed to normalise string: ' . $string, DEBUG_DEVELOPER);
            return $string; // Return the original string, since it is the best we have.
        }

        return $normalised;
    }

    public function get_correct_response() {
        $response = parent::get_correct_response();
        if ($response) {
            $response['answer'] = $this->clean_response($response['answer']);
        }
        return $response;
    }

    public function clean_response($answer) {
        // Break the string on non-escaped asterisks.
        $bits = preg_split('/(?<!\\\\)\*/', $answer);

        // Unescape *s in the bits.
        $cleanbits = array();
        foreach ($bits as $bit) {
            $cleanbits[] = str_replace('\*', '*', $bit);
        }

        // Put it back together with spaces to look nice.
        return trim(implode(' ', $cleanbits));
    }

    public function check_file_access($qa, $options, $component, $filearea,
            $args, $forcedownload) {
        if ($component == 'question' && $filearea == 'answerfeedback') {
            $currentanswer = $qa->get_last_qt_var('answer');
            $answer = $this->get_matching_answer(array('answer' => $currentanswer));
            $answerid = reset($args); // Itemid is answer id.
            return $options->feedback && $answer && $answerid == $answer->id;

        } else if ($component == 'question' && $filearea == 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);

        } else {
            return parent::check_file_access($qa, $options, $component, $filearea,
                    $args, $forcedownload);
        }
    }

    /**
     * Return the question settings that define this question as structured data.
     *
     * @param question_attempt $qa the current attempt for which we are exporting the settings.
     * @param question_display_options $options the question display options which say which aspects of the question
     * should be visible.
     * @return mixed structure representing the question settings. In web services, this will be JSON-encoded.
     */
    public function get_question_definition_for_external_rendering(question_attempt $qa, question_display_options $options) {
        // No need to return anything, external clients do not need additional information for rendering this question type.
        return null;
    }

    // We need mojomatch
    public function make_behaviour(question_attempt $qa, $preferredbehaviour) {
        global $CFG;

        if (file_exists($CFG->dirroot.'/question/behaviour/mojomatch/')) {
             question_engine::load_behaviour_class('mojomatch');
             return new qbehaviour_mojomatch($qa, $preferredbehaviour);
        }

        return parent::make_behaviour($qa, $preferredbehaviour);
    }

    public function get_right_answer_summary() {
        $answer = parent::get_right_answer_summary();
        
        // parent calls
        /*
            $correctresponse = $this->get_correct_response();
        if (empty($correctresponse)) {
            return null;
        }
        return $this->summarise_response($correctresponse);
        */
        //which calls
        //$answer = $this->get_correct_answer();
        //which calls
        //return $this->gradingstrategy->get_correct_answer();

        return $answer;
    }

    /**
     * Look up the event ID for this question attempt from the topomojo_attempts table.
     */
    protected function get_eventid_for_attempt(question_attempt $qa) {
        global $DB;
        $qubaid = $qa->get_usage_id();
        if (!is_numeric($qubaid)) {
            return null;
        }
        return $DB->get_field('topomojo_attempts', 'eventid', ['questionusageid' => $qubaid]);
    }

    /**
     * Fetch the gamespace challenge for this specific attempt's event.
     * Falls back to searching all active events if the attempt lookup fails.
     */
    protected function get_challenge_for_attempt(question_attempt $qa) {
        global $CFG;
        require_once("$CFG->dirroot/mod/topomojo/locallib.php");

        $client = $this->setup();
        if (!$client) {
            debugging("Failed to set up TopoMojo client", DEBUG_DEVELOPER);
            return null;
        }

        $eventid = $this->get_eventid_for_attempt($qa);
        if ($eventid) {
            debugging("Using gamespace ID from attempt record: $eventid", DEBUG_DEVELOPER);
            return get_gamespace_challenge($client, $eventid);
        }

        debugging("No gamespace ID on attempt record, falling back to active gamespace search", DEBUG_DEVELOPER);
        $all_events = list_all_active_events($client);
        if (!$all_events) {
            debugging("no events", DEBUG_DEVELOPER);
            return null;
        }

        $moodle_events = moodle_events($client, $all_events);
        if (!$moodle_events) {
            debugging("no moodle events", DEBUG_DEVELOPER);
            return null;
        }

        $history = user_events($client, $moodle_events);
        if (!$history) {
            debugging("no user events", DEBUG_DEVELOPER);
            return null;
        }

        $gamespace = get_active_event($history);
        if (!$gamespace) {
            debugging("no active gamespace found", DEBUG_DEVELOPER);
            return null;
        }

        return get_gamespace_challenge($client, $gamespace->id);
    }

    /**
     * Finds this question in the gamespace challenge the attempt is running against.
     *
     * Keyed on the three things mod_topomojo's questionmanager records when it imports
     * a challenge question: the workspace, the variant, and the question's position in
     * that variant. Anything else is a guess, and a guess here grades the response
     * against another question's answer.
     *
     * This used to resolve by question text, then fall through to the question at the
     * attempt's slot position. Both legs were wrong. The text comparison could not
     * distinguish the same text repeated across variants with different answers, and
     * it could not match a transform question at all, because
     * normalize_text_for_comparison() substitutes the literal string '.*' and the
     * comparison was ===. The positional fallback then resolved something regardless:
     * the text of the question at that position was itself one of the two operands, so
     * the comparison was guaranteed true when the loop reached it. That is how an
     * answer from a different activity ended up grading a response.
     *
     * @param stdClass $challenge The gamespace challenge, as get_gamespace_challenge() returns it.
     * @return stdClass|null The matching TopoMojo question, or null when it cannot be identified.
     */
    protected function find_gamespace_question($challenge) {
        if (!$challenge || !isset($challenge->challenge->sections)) {
            return null;
        }

        if (!$this->qorder) {
            // Questions imported before qorder was recorded cannot be placed. The
            // question bank's own answer is the only defensible one.
            debugging('No qorder recorded for this question, so it cannot be matched to the ' .
                'gamespace challenge; grading against the stored answer.', DEBUG_DEVELOPER);
            return null;
        }

        $wrongworkspace = !empty($this->workspaceid) && isset($challenge->workspaceId)
            && $this->workspaceid !== $challenge->workspaceId;
        if ($wrongworkspace) {
            debugging("Gamespace workspace {$challenge->workspaceId} is not this question's " .
                "workspace {$this->workspaceid}.", DEBUG_DEVELOPER);
            return null;
        }

        // The variant is stored 1 based (questionmanager.php does $variant + 1); TopoMojo's
        // GameState.variant is the 0 based index.
        $wrongvariant = $this->variant !== null && isset($challenge->variant)
            && (int)$this->variant !== (int)$challenge->variant + 1;
        if ($wrongvariant) {
            debugging("Gamespace is running variant " . ((int)$challenge->variant + 1) .
                ", this question belongs to variant {$this->variant}.", DEBUG_DEVELOPER);
            return null;
        }

        // The qorder counts every question of the variant, flattened across its sections
        // and 1 based, which is how questionmanager.php assigns it. Indexing a single
        // section would reintroduce the positional bug one level down.
        $questions = $this->flatten_gamespace_questions($challenge);
        $index = (int)$this->qorder - 1;
        if (!isset($questions[$index])) {
            debugging("Gamespace challenge has no question at position {$this->qorder}.", DEBUG_DEVELOPER);
            return null;
        }

        return $questions[$index];
    }

    /**
     * Returns the challenge's questions in the order qorder counts them.
     *
     * @param stdClass $challenge The gamespace challenge.
     * @return array The questions of every section, in section order.
     */
    protected function flatten_gamespace_questions($challenge) {
        $questions = [];
        foreach ($challenge->challenge->sections as $section) {
            if (!isset($section->questions)) {
                continue;
            }
            foreach ($section->questions as $question) {
                $questions[] = $question;
            }
        }
        return $questions;
    }

    /**
     * Returns the answer TopoMojo holds for this question in the attempt's gamespace.
     *
     * @param question_attempt $qa The attempt being graded or rendered.
     * @return string|null The live answer, or null when the question cannot be identified
     *      in the gamespace - in which case the question bank's answer stands.
     */
    public function get_rightanswer_topomojo(question_attempt $qa) {
        $question = $this->find_gamespace_question($this->get_challenge_for_attempt($qa));
        if (!$question || !isset($question->answer) || trim($question->answer) === '') {
            return null;
        }

        return $question->answer;
    }

    /**
     * Returns the question text TopoMojo substituted the transforms into.
     *
     * With an attempt in hand the question is identified the same way grading
     * identifies it, so the text shown is the text of the question being graded. The
     * caller falls back to the stored question text when this returns null, which is
     * the honest outcome: better the authored text with its ##token## placeholders
     * showing than another question's text.
     *
     * @param int $index Position of the question, used only by the legacy no-attempt path.
     * @param question_attempt|null $qa The attempt, when there is one.
     * @return string|null The substituted question text, or null when it cannot be identified.
     */
    public function get_transformed_question_topomojo($index, ?question_attempt $qa = null) {
        if ($qa) {
            $question = $this->find_gamespace_question($this->get_challenge_for_attempt($qa));
            return $question->text ?? null;
        } else {
            global $CFG;
            require_once("$CFG->dirroot/mod/topomojo/locallib.php");

            $client = $this->setup();
            $all_events = list_all_active_events($client);

            if (!$all_events) {
                debugging("No events found", DEBUG_DEVELOPER);
                return null;
            }

            $moodle_events = moodle_events($client, $all_events);
            $history = user_events($client, $moodle_events);
            $gamespace = get_active_event($history);

            if (!$gamespace) {
                debugging("No gamespace found for question", DEBUG_DEVELOPER);
                return null;
            }

            $challenge = get_gamespace_challenge($client, $gamespace->id);
        }

        if (!$challenge || !isset($challenge->challenge->sections[0]->questions[$index])) {
            debugging("Question at index $index not found", DEBUG_DEVELOPER);
            return null;
        }

        return $challenge->challenge->sections[0]->questions[$index]->text;
    }           

    public function grade_attempt(array $response, question_answer $rightanswer) {
        //echo "we are inside of grade_attempt<br>";
        if ($this->compare_response_with_answer($response, $rightanswer)) {
            return $rightanswer;
        }
        return null;
    }

    public function grade_response_qa(array $response, question_attempt $qa) {
        $answers = $this->get_answers();
        if (count($answers) !== 1) {
            // There is no single answer for the live TopoMojo answer to replace, so grade
            // against the question bank through the usual strategy, which tries every
            // answer in turn. This used to fall through leaving $rightanswer unassigned,
            // and grade_attempt()'s typed parameter then threw a TypeError: submitting the
            // response failed outright rather than being graded. The edit form allows more
            // than one answer row, so this is reachable by ordinary authoring.
            debugging(
                'Expected exactly one answer, found ' . count($answers) .
                    '; grading against the stored answers without a live TopoMojo answer.',
                DEBUG_DEVELOPER
            );
            return $this->grade_response($response);
        }

        $rightanswer = reset($answers);
        $live_answer = $this->get_rightanswer_topomojo($qa);
        if ($live_answer) {
            $rightanswer->answer = $live_answer;
        }

        $answer = $this->grade_attempt($response, $rightanswer);
        if ($answer) {
            return array($answer->fraction,
                    question_state::graded_state_for_fraction($answer->fraction));
        } else {
            return array(0, question_state::$gradedwrong);
        }
    }

}

class mojomatch_grading_strategy extends question_first_matching_answer_grading_strategy {
    /**
     * @var question_response_answer_comparer (presumably also a
     * {@link question_definition}) the question we are doing the grading for.
     */
    protected $question;

    /**
     * @param question_response_answer_comparer $question (presumably also a
     * {@link question_definition}) the question we are doing the grading for.
     */
    public function __construct(question_response_answer_comparer $question) {
        $this->question = $question;
    }

    public function grade(array $response) {
        //echo "we are inside of grade<br>";
        global $PAGE;
        foreach ($this->question->get_answers() as $aid => $answer) {
            if ($this->question->compare_response_with_answer($response, $answer)) {
                $answer->id = $aid;
                return $answer;
            }
        }
        return null;
    }


    public function get_correct_answer() {
        //echo "get_correct_answer<Br>";
        foreach ($this->question->get_answers() as $answer) {
            $state = question_state::graded_state_for_fraction($answer->fraction);
            if ($state == question_state::$gradedright) {
                return $answer;
            }
        }
        return null;
    }
}
