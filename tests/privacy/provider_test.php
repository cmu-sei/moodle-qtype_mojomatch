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


namespace qtype_mojomatch\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\user_preference;
use core_privacy\local\request\writer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/mojomatch/questiontype.php');

/**
 * Privacy provider tests for the mojomatch question type.
 *
 * @package    qtype_mojomatch
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {

    /**
     * Every preference the edit form saves is declared in the metadata.
     */
    public function test_get_metadata_declares_every_saved_default(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $fromform = (object) [
            'defaultmark' => 2,
            'penalty' => 0.25,
            'usecase' => 1,
            'matchtype' => 2,
            'variant' => 3,
            'transforms' => 1,
            'workspaceid' => 'abc123',
        ];
        (new \qtype_mojomatch())->save_defaults_for_new_questions($fromform);

        $saved = [];
        foreach (get_user_preferences(null, null, $user->id) as $name => $value) {
            if (strpos($name, 'qtype_mojomatch_') === 0) {
                $saved[] = $name;
            }
        }
        $this->assertNotEmpty($saved);

        $declared = [];
        $collection = provider::get_metadata(new collection('qtype_mojomatch'));
        foreach ($collection->get_collection() as $item) {
            $this->assertInstanceOf(user_preference::class, $item);
            $declared[] = $item->get_name();
        }

        sort($saved);
        sort($declared);
        $this->assertEquals($declared, $saved);
    }

    /**
     * A user with no preferences exports nothing.
     */
    public function test_export_user_preferences_no_pref(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        provider::export_user_preferences($user->id);
        $writer = writer::with_context(\context_system::instance());
        $this->assertFalse($writer->has_any_data());
    }

    /**
     * Each preference exports under its own name, with its description.
     *
     * @param string $name The name of the user preference, without the component prefix.
     * @param mixed $value The value stored in the database.
     * @param string $expected The exported value.
     */
    #[DataProvider('user_preference_provider')]
    public function test_export_user_preferences($name, $value, $expected): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        set_user_preference("qtype_mojomatch_$name", $value, $user);

        provider::export_user_preferences($user->id);

        $writer = writer::with_context(\context_system::instance());
        $this->assertTrue($writer->has_any_data());
        $preferences = (array) $writer->get_user_preferences('qtype_mojomatch');
        $this->assertEquals([$name], array_keys($preferences));
        $this->assertEquals($expected, $preferences[$name]->value);
        $this->assertEquals(get_string("privacy:preference:$name", 'qtype_mojomatch'),
            $preferences[$name]->description);
    }

    /**
     * Valid user preferences for the mojomatch question type.
     *
     * @return array Array of valid user preferences.
     */
    public static function user_preference_provider(): array {
        return [
            'default mark 2' => ['defaultmark', 2, 2],
            'penalty 33.33333%' => ['penalty', 0.3333333, '33.33333%'],
            'usecase yes' => ['usecase', 1, 'Yes, case must match'],
            'usecase no' => ['usecase', 0, 'No, case is unimportant'],
            'matchtype matchalpha' => ['matchtype', 0,
                'MatchAlpha will strip all characters other than alphabetic characters.'],
            'matchtype matchall' => ['matchtype', 1,
                'MatchAll will require every one of the answers to appear among the words of the response.'],
            'matchtype matchany' => ['matchtype', 2, 'MatchAny will accept a response that matches any one of the answers.'],
            'matchtype match' => ['matchtype', 3,
                'Match will perform an exact match of the answer string similar to a short answer.'],
            'variant 3' => ['variant', 3, '3'],
            'transforms 1' => ['transforms', 1, '1'],
            'workspaceid' => ['workspaceid', 'abc123', 'abc123'],
        ];
    }
}
