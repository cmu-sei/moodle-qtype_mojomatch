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

defined('MOODLE_INTERNAL') || die();

/**
 * mojomatch question renderer class.
 *
 * @package    qtype
 * @subpackage mojomatch
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class qtype_mojomatch_renderer extends qtype_renderer {
    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {
        $question = $qa->get_question();
        $currentanswer = $qa->get_last_qt_var('answer');
        $inputname = $qa->get_qt_field_name('answer');
        $inputattributes = array(
            'type' => 'text',
            'name' => $inputname,
            'value' => $currentanswer,
            'id' => $inputname,
            'size' => 80,
            'class' => 'form-control d-inline',
        );

        if ($options->readonly) {
            $inputattributes['readonly'] = 'readonly';
        }
        $answers = $question->get_answers();
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
        $feedbackimg = '';
        if ($options->correctness) {
            //echo "currentanswer $currentanswer<Br>";
            $answer = $question->grade_attempt(array('answer' => $currentanswer), $rightanswer);
            //echo "right answer summary for attempt: $rightanswer->answer<br>";
            if ($answer) {
                $fraction = $answer->fraction;
            } else {
                $fraction = 0;
            }
            //echo "fraction $fraction<Br>";
            $inputattributes['class'] .= ' ' . $this->feedback_class($fraction);
            $feedbackimg = $this->feedback_image($fraction);
            //echo "right answer summary is: " . $qa->get_right_answer_summary();
        }

        $questiontext = $question->format_questiontext($qa);
        $placeholder = false;
        if (preg_match('/_____+/', $questiontext, $matches)) {
            $placeholder = $matches[0];
            $inputattributes['size'] = round(strlen($placeholder) * 1.1);
        }
        $input = html_writer::empty_tag('input', $inputattributes) . $feedbackimg;

        if ($placeholder) {
            $inputinplace = html_writer::tag('label', get_string('answer'),
                    array('for' => $inputattributes['id'], 'class' => 'accesshide'));
            $inputinplace .= $input;
            $questiontext = substr_replace($questiontext, $inputinplace,
                    strpos($questiontext, $placeholder), strlen($placeholder));
        }

        $result = html_writer::tag('div', $questiontext, array('class' => 'qtext'));

        if (!$placeholder) {
            $result .= html_writer::start_tag('div', array('class' => 'ablock form-inline'));
            $result .= html_writer::tag('label', get_string('answer', 'qtype_mojomatch',
                    html_writer::tag('span', $input, array('class' => 'answer'))),
                    array('for' => $inputattributes['id']));
            $result .= html_writer::end_tag('div');
        }

        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div',
                    $question->get_validation_error(array('answer' => $currentanswer)),
                    array('class' => 'validationerror'));
        }

        return $result;
    }

    public function specific_feedback(question_attempt $qa) {
       //echo "specific_feedback<br>";
       $question = $qa->get_question();
       $answer = $question->get_matching_answer(array('answer' => $qa->get_last_qt_var('answer')));
       //$answer = $question->get_matching_answer(array('answer' => "decrypt_files_##FUNCTIONFLAG##"));
       if (!$answer || !$answer->feedback) {
            return '';
        }
        return $question->format_text($answer->feedback, $answer->feedbackformat,
                $qa, 'question', 'answerfeedback', $answer->id);
    }

    public function correct_response(question_attempt $qa) {
        $question = $qa->get_question();
        $answer = $question->get_matching_answer($question->get_correct_response());
        if (!$answer) {
            return '';
        }
        return get_string('correctansweris', 'qtype_mojomatch',
                s($answer->answer));
    }
}
