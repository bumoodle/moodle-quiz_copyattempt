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
 * This file defines the setting form for the quiz grading report.
 *
 * @package    quiz
 * @subpackage copyattempt
 * @copyright  2012 Binghamton University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/local/quizsync/synclib.php');

/**
 * Form for synchronizing of Quiz attempts between partners. 
 * 
 * @uses moodleform
 * @package quiz_syncattempt
 * @version $id$
 * @copyright 2011, 2012 Binghamton University
 * @author Kyle Temkin <ktemkin@binghamton.edu> 
 * @license GNU Public License, {@link http://www.gnu.org/copyleft/gpl.html}
 */
class quiz_syncattempt_form extends moodleform 
{
    protected $all_quizzes;

    /**
     * Creates a new instance of the Copy Attempts form.
     * 
     * @param array $all_students          Array of stdClasses, which contain the student's first/last names and userid.
     * @param array $existing_attempts     Array of 
     */
    public function __construct($all_quizzes, $action=null)
    {
        //copy the fields from the constructor
        $this->all_quizzes = $all_quizzes;

        parent::__construct($action);
    }

    /**
     * Build an associative array of quizid => quiz name for use in a select form element.
     */
    public function quiz_select()
    {
        // Populate the quiz with a default option of retrieving partnerships from the current quiz...
        $select = array();

        // ... and add each other quiz in the course, as a viable option.
        foreach($this->all_quizzes as $quiz) {
            $select[$quiz->id] = $quiz->name;
        }

        //Return the finalized list of quizzes.
        return $select;
    }


    /**
     * Builds the Copy Attempt form; specify all contained objects.
     *
     */
    protected function definition() 
    {
        //get a quick reference to the MoodleForm object
        //(mform is the standard Moodle name)
        $mform =& $this->_form;

        //Add the synchronize header
        $mform->addElement('header', 'synchronize', get_string('syncpartners', 'quiz_copyattempt'));

        //And add the 
        $mform->addElement('select', 'syncfrom', get_string('syncfrom', 'quiz_copyattempt'), $this->quiz_select());

        //add the submit/reset buttons
        $this->add_action_buttons(false, get_string('sync', 'quiz_copyattempt'));
    }
}
