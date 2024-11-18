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

    private function setup() {
        $client = new curl;
        $x_api_key = get_config('topomojo', 'apikey');
        $topoHeaders = array( 'x-api-key: ' . $x_api_key, 'content-type: application/json' );
        $client->setHeader($topoHeaders);
        //debugging("api key $x_api_key", DEBUG_DEVELOPER);
        return $client;
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
            $regexp = "/##[a-zA-Z0-9]*##/";
            $string = preg_replace($regexp, '', $string);
            $pattern = preg_replace($regexp, '', $pattern);
            if (str_contains($string, $pattern)) {
                $string = $pattern;
            }
        } else if ($viewattempt == 1) {
            //echo "how can compare during view attempt<br>";
            //echo "we shouldnt even be here if its already been graded<br>";
            //echo "viewattempt variable does not get used<br>";
            // TODO should this throw an error or a debug message?
        }
        if ($matchtype == '0') {
            //matchalpha
            $string = preg_replace('/[^A-Za-z0-9]/', '', $string);
            $pattern = preg_replace('/[^A-Za-z0-9]/', '', $pattern);
            $regexp = "|^$pattern$|u";
            // Make the match insensitive if requested to.
            if ($ignorecase) {
                $regexp .= 'i';
            }
            return preg_match($regexp, trim($string));
        } else if ($matchtype == '1') {
            //match
            $string = preg_replace('/[^A-Za-z0-9]/', '', $string);
            $pattern = preg_replace('/[^A-Za-z0-9]/', '', $pattern);
            if ($ignorecase) {
                $string = strtolower($string);
                $pattern = strtolower($pattern);
            }
            $regexp = '/[ ,;:|]/';
            $answer = preg_split($regexp, $string);
            $response = preg_split($regexp, $pattern);
            $intersection = array_intersect($answer, $response);
            if (count($intersection) == count($answer)) {
                return true;
            } else {
                return false;
            }
        } else if ($matchtype =='2') {
            //matchany
            if ($ignorecase) {
                $string = strtolower($string);
                $pattern = strtolower($pattern);
            }
            return str_contains($pattern, $string);
        } else if ($matchtype == '3') {
            //match

            // Break the string on non-escaped runs of asterisks.
            // ** is equivalent to *, but people were doing that, and with many *s it breaks preg.
            $bits = preg_split('/(?<!\\\\)\*+/', $pattern);

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
            //echo "regexp $regexp<br>";
            //echo "string $string<br>";
            return preg_match($regexp, trim($string));
        }
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

    public function get_rightanswer_topomojo(question_attempt $qa) {
        global $CFG;
        require_once("$CFG->dirroot/mod/topomojo/locallib.php");
    
        $x_api_key = get_config('qtype_topomojo', 'api_key');
        $client = $this->setup();
        $name = preg_replace('/^(.*) - \d+$/', '${1}', $this->name);
        $all_events = list_events($client, $name);
    
        if (!$all_events) {
            print_error("no events");
        }
    
        $moodle_events = moodle_events($all_events);
        if (!$moodle_events) {
            print_error("no user events");
        }
    
        $history = user_events($client, $moodle_events);
        if (!$history) {
            print_error("no history");
        }
    
        $gamespace = get_active_event($history);
        if (!$gamespace) {
            debugging("no gamespace found for question", DEBUG_DEVELOPER);
            print_error("no gamespace");
        }
    
        $challenge = get_gamespace_challenge($client, $gamespace->id);
    
        // Prepare the transformed question text for comparison by applying TopoMojo transforms
        $question_index = $qa->get_slot() - 1; // Determine the index based on the slot
        $transformed_question_text = $this->get_transformed_question_topomojo($question_index);
        
        // Normalize both texts for comparison by replacing placeholders if needed
        $normalized_moodle_text = $this->normalize_text_for_comparison($this->questiontext);
        $normalized_transformed_text = $this->normalize_text_for_comparison($transformed_question_text);
    
        foreach ($challenge->challenge->sections as $section) {
            foreach ($section->questions as $question) {
                // Normalize TopoMojo question text for comparison
                $normalized_topomojo_text = $this->normalize_text_for_comparison($question->text);
                
                // Check if normalized Moodle text or transformed text matches the TopoMojo text
                if (trim($normalized_moodle_text) === trim($normalized_topomojo_text) ||
                    trim($normalized_transformed_text) === trim($normalized_topomojo_text)) {
                    return $question->answer;
                }
            }
        }
    
        return null; // Return null if no match is found
    }  

    private function normalize_text_for_comparison($text) {
        if (!$text) {
            return '';
        }
    
        // Replace all ##...## patterns with a wildcard marker (like '.*')
        // This treats the replaced transform values (like "firewall" or "command") as wildcard text.
        $text = preg_replace('/##[a-zA-Z0-9_]+##/', '.*', $text);
    
        // Trim whitespace and lowercase for relaxed matching
        return trim(strtolower($text));
    }
        

    public function get_transformed_question_topomojo($index) {
        global $CFG;
        require_once("$CFG->dirroot/mod/topomojo/locallib.php");
    
        $x_api_key = get_config('qtype_topomojo', 'api_key');
        $client = $this->setup();
        $name = preg_replace('/^(.*) - \d+$/', '${1}', $this->name);
        $all_events = list_events($client, $name);
    
        // Check if there are events, and if not, return null or a fallback value
        if (!$all_events) {
            debugging("No events found", DEBUG_DEVELOPER);
            return null; // Return null if no events are found
        }
    
        $moodle_events = moodle_events($all_events);
        $history = user_events($client, $moodle_events);
        $gamespace = get_active_event($history);
    
        // Check if there is an active gamespace, and if not, return null
        if (!$gamespace) {
            debugging("No gamespace found for question", DEBUG_DEVELOPER);
            return null; // Return null if no gamespace is found
        }
    
        $challenge = get_gamespace_challenge($client, $gamespace->id);
    
        // Check if there are questions in the challenge and the specified index exists
        if (isset($challenge->challenge->sections[0]->questions[$index])) {
            return $challenge->challenge->sections[0]->questions[$index]->text;
        }
    
        // If the question at the specified index is not found, return null
        debugging("Question at index $index not found", DEBUG_DEVELOPER);
        return null;
    }           

    public function grade_attempt(array $response, question_answer $rightanswer) {
        //echo "we are inside of grade_attempt<br>";
        if ($this->compare_response_with_answer($response, $rightanswer)) {
            return $rightanswer;
        }
        return null;
    }

    public function grade_response_qa(array $response, question_attempt $qa) {
        //echo "grade_response_qa<br>";    
        $answers = $this->get_answers();
        if (count($answers) == 1) {
            $rightanswer = reset($answers);
            if (method_exists($qa, 'get_right_answer_summary')) {
                $transformed_answer = $qa->get_right_answer_summary();
                if ($transformed_answer) {
                    // Use the transformed answer if it's an object
                    if (is_object($transformed_answer)) {
                        $rightanswer = $transformed_answer;
                    } else {
                        // Otherwise, treat it as a string answer
                        $rightanswer->answer = $transformed_answer;
                    }
                }
            }
            //$rightanswer->answer = $qa->get_right_answer_summary();
        } else {
            print_error("cannot handle more than one answer");
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