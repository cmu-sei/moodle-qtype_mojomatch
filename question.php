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
    /** @var array of question_answer. */
    public $answers = array();

    public function __construct() {
        parent::__construct(new question_first_matching_answer_grading_strategy($this));
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
/*
    private function setup() {
        $client = new curl;
        $x_api_key = get_config('topomojo', 'apikey');
        $topoHeaders = array( 'x-api-key: ' . $x_api_key, 'content-type: application/json' );
        $client->setHeader($topoHeaders);
	//debugging("api key $x_api_key", DEBUG_DEVELOPER);
        return $client;
    }
*/
    public function compare_response_with_answer(array $response, question_answer $answer) {
        if (!array_key_exists('answer', $response) || is_null($response['answer'])) {
            return false;
        }
/*
	if ($this->transforms) {
		echo "<br>using transforms for answer $answer->answer<br>";
		// TODO dont even come here when previewing a question
		// TODO set feedback to indicate as such
                $x_api_key = get_config('qtype_topomojo', 'api_key');
                $topomojo_host = get_config('qtype_topomojo', 'topomojo_host');
		global $CFG;
		require_once("$CFG->dirroot/mod/topomojo/locallib.php");

		$client = $this->setup();
		// get gamespace
		$name = preg_replace('/^(.*) - \d+$/', '${1}', $this->name);
		$name = "A King's Ransom";
		$all_events = list_events($client, $name);
		$moodle_events = moodle_events($all_events);
		$history = user_events($client, $moodle_events);
		$gamespace = get_active_event($history);

		if ($gamespace) {
		    $challenge = get_gamespace_challenge($client, $gamespace->id);
		    foreach ($challenge->challenge->sections as $section) {
		        foreach ($section->questions as $question) {
			    if ($question->text == $this->questiontext) {
				$answer->answer = $question->answer;
				// TODO update quba with the correct answer
				break;
			    }
		        }
		    }
		} else {
		    echo "we must be in a preview<br>";
		    return true;
		}
		echo "the correct answer is: $question->answer<br>";
        }
*/
        return self::compare_string_with_matchtype(
                $response['answer'], $answer->answer, !$this->usecase, $this->matchtype);
    }

    public static function compare_string_with_matchtype($string, $pattern, $ignorecase, $matchtype) {

        // Normalise any non-canonical UTF-8 characters before we start.
        $pattern = self::safe_normalize($pattern);
        $string = self::safe_normalize($string);

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
            //matchan
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
}
