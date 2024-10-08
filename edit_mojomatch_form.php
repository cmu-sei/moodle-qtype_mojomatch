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
TopoMojo Plugin for Moodle
Copyright 2024 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. 
CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, 
WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. 
CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Licensed under a GNU GENERAL PUBLIC LICENSE - Version 3, 29 June 2007-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.  
Please see Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of Third-Party Software each subject to its own license.
DM24-1175
*/

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

        $mform->addElement('text', 'variant', get_string('variant', 'qtype_mojomatch'));
        $mform->setDefault('variant', '1');
        $mform->addHelpButton('variant', 'variant', 'qtype_mojomatch');

        $mform->addElement('text', 'transforms', get_string('transforms', 'qtype_mojomatch'));
        $mform->setDefault('transforms', '1');
        $mform->addHelpButton('transforms', 'transforms', 'qtype_mojomatch');

        $mform->addElement('text', 'workspaceid', get_string('workspaceid', 'qtype_mojomatch'));
        $mform->setDefault('workspaceid', '');
        $mform->addHelpButton('workspaceid', 'workspaceid', 'qtype_mojomatch');

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
