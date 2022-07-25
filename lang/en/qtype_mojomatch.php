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
 * Strings for component 'qtype_mojomatch', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package    qtype
 * @subpackage mojomatch
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addmoreanswerblanks'] = 'Blanks for {no} More Answers';
$string['answer'] = 'Answer: {$a}';
$string['answermustbegiven'] = 'You must enter an answer if there is a grade or feedback.';
$string['answerno'] = 'Answer {$a}';
$string['caseno'] = 'No, case is unimportant';
$string['casesensitive'] = 'Case sensitivity';
$string['caseyes'] = 'Yes, case must match';
$string['correctansweris'] = 'The correct answer is: {$a}';
$string['correctanswers'] = 'Correct answers';
$string['filloutoneanswer'] = 'You must provide at least one possible answer. Answers left blank will not be used. \'*\' can be used as a wildcard to match any characters. The first matching answer will be used to determine the score and feedback.';
$string['notenoughanswers'] = 'This type of question requires at least {$a} answers';
$string['pleaseenterananswer'] = 'Please enter an answer.';
$string['pluginname'] = 'MojoMatch';
$string['pluginname_help'] = 'In response to a question (that may include an image) the respondent types a word or short phrase. There may be several possible correct answers, each with a different grade. If the "Case sensitive" option is selected, then you can have different scores for "Word" or "word".';
$string['pluginname_link'] = 'question/type/mojomatch';
$string['pluginnameadding'] = 'Adding a mojomatch question';
$string['pluginnameediting'] = 'Editing a MojoMatch question';
$string['pluginnamesummary'] = 'Allows a response of one or a few words that is graded by comparing against various model answers, which may contain wildcards.';
$string['privacy:metadata'] = 'MojoMatch question type plugin allows question authors to set default options as user preferences.';
$string['privacy:preference:defaultmark'] = 'The default mark set for a given question.';
$string['privacy:preference:penalty'] = 'The penalty for each incorrect try when questions are run using the \'Interactive with multiple tries\' or \'Adaptive mode\' behaviour.';
$string['privacy:preference:usecase'] = 'Whether the answers should be case sensitive.';

//added
$string['matchtype'] = 'Match type';
$string['matchtype_help'] = 'The type of matching to perform.';
$string['match'] = 'Match will perform an exact match of the answer string similar to a short answer.';
$string['matchalpha'] = 'MatchAlpha will strip all characters other than alphabetic characters.';
$string['matchany'] = 'MatchAny will check the response to a contain the answer as a substring.';
$string['matchall'] = 'MatchAll will check a list of words to match the correct list of words.';
$string['variant'] = 'The variant of the lab that the question belongs to.';
$string['workspaceid'] = 'The ID of the topomojo workspace; used to find the running gamespace.';
$string['transforms'] = 'Whether the lab uses transforms to generate the answer during runtime.';

