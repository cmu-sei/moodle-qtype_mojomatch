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
 * Defines the editing form for the mojomatch question type.
 *
 * @package    qtype
 * @subpackage mojomatch
 * @copyright  2007 Jamie Pratt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * mojomatch question editing form definition.
 *
 * @copyright  2007 Jamie Pratt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_mojomatch_edit_form extends question_edit_form {

    protected function definition_inner($mform) {
        $menu = [
            get_string('caseno', 'qtype_mojomatch'),
            get_string('caseyes', 'qtype_mojomatch')
        ];
        $mform->addElement('select', 'usecase',
                get_string('casesensitive', 'qtype_mojomatch'), $menu);
        $mform->setDefault('usecase', $this->get_default_value('usecase', $menu[0]));


        $options = array(get_string('matchalpha', 'qtype_mojomatch'), get_string('matchany', 'qtype_mojomatch'),
                        get_string('matchall', 'qtype_mojomatch'), get_string('match', 'qtype_mojomatch'));
        $mform->addElement('select', 'matchtype', get_string('matchtype', 'qtype_mojomatch'), $options);
        $mform->setDefault('matchtype', '0');
        $mform->addHelpButton('matchtype', 'matchtype', 'qtype_mojomatch');

        $mform->addElement('static', 'answersinstruct',
                get_string('correctanswers', 'qtype_mojomatch'),
                get_string('filloutoneanswer', 'qtype_mojomatch'));
        $mform->closeHeaderBefore('answersinstruct');

        $this->add_per_answer_fields($mform, get_string('answerno', 'qtype_mojomatch', '{no}'),
                question_bank::fraction_options());

        $this->add_interactive_settings();
    }

    protected function get_more_choices_string() {
        return get_string('addmoreanswerblanks', 'qtype_mojomatch');
    }

    protected function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);
        $question = $this->data_preprocessing_answers($question);
        $question = $this->data_preprocessing_hints($question);

        return $question;
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $answers = $data['answer'];
        $answercount = 0;
        $maxgrade = false;
        foreach ($answers as $key => $answer) {
            $trimmedanswer = trim($answer);
            if ($trimmedanswer !== '') {
                $answercount++;
                if ($data['fraction'][$key] == 1) {
                    $maxgrade = true;
                }
            } else if ($data['fraction'][$key] != 0 ||
                    !html_is_blank($data['feedback'][$key]['text'])) {
                $errors["answeroptions[{$key}]"] = get_string('answermustbegiven', 'qtype_mojomatch');
                $answercount++;
            }
        }
        if ($answercount==0) {
            $errors['answeroptions[0]'] = get_string('notenoughanswers', 'qtype_mojomatch', 1);
        }
        if ($maxgrade == false) {
            $errors['answeroptions[0]'] = get_string('fractionsnomax', 'question');
        }
        return $errors;
    }

    public function qtype() {
        return 'mojomatch';
    }
}
