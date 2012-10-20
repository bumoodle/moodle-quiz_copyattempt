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
 * Form for copying of Quiz attempts between users. 
 * 
 * @uses moodleform
 * @package quiz_copyattempt
 * @version $id$
 * @copyright 2011, 2012 Binghamton University
 * @author Kyle Temkin <ktemkin@binghamton.edu> 
 * @license GNU Public License, {@link http://www.gnu.org/copyleft/gpl.html}
 */
class quiz_copyattempt_form extends moodleform 
{
    protected $all_students;
    protected $existing_attempts;

    /**
     * Creates a new instance of the Copy Attempts form.
     * 
     * @param array $all_students          Array of stdClasses, which contain the student's first/last names and userid.
     * @param array $existing_attempts     Array of 
     */
    public function __construct($all_students, $all_attempts, $action=null)
    {
        //copy the fields from the constructor
        $this->all_students = $all_students;
        $this->existing_attempts = $all_attempts;

        parent::__construct($action);
    }

    
    /**
     * Build an associative array of userid => student name for use in a select form element.  
     */
    protected function student_select()
    {
        $select = array();

        //add each student to the select options
        foreach($this->all_students as $student)
        {
            //TODO: respect name internationalized display preferences?
            $select[$student->id] = $student->lastname . ', ' . $student->firstname;
        }

        return $select;
    }

    /**
     * Build an associative array of attemptid => attempt information for use in a select form element.
     */
    public function attempt_select()
    {
        $select = array();

        //add each attempt to the select options
        foreach($this->existing_attempts as $attempt)
        {
            //format the attempt's grades
            $grade = format_float($attempt->actualpoints, $attempt->decimalpoints);
            $possible = format_float($attempt->possiblepoints, $attempt->decimalpoints);

            //and display the attempt's information
            $select[$attempt->id] = $attempt->lastname . ', '. $attempt->firstname . '    (' . $grade  .'/'. $possible . ')';
        }

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

        $mform->addElement('header', 'copy', get_string('copyattempt', 'quiz_copyattempt'));

        //add a "copy from" student select
        $mform->addElement('select', 'copyattempt', get_string('copyfrom', 'quiz_copyattempt'), $this->attempt_select(), array('size' => 8, 'style' => 'width: 300px'));

        //add a "copy to" student select
        $mform->addElement('select', 'copyto', get_string('copyto', 'quiz_copyattempt'), $this->student_select(), array('size' => 8, 'style' => 'width: 300px'));

        //add the submit/reset buttons
        $this->add_action_buttons(false, get_string('copyattempt', 'quiz_copyattempt'));
    }
}
