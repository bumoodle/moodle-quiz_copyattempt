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
 * This file defines the quiz manual grading report class.
 *
 * @package    quiz
 * @subpackage grading
 * @copyright  2006 Gustav Delius
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/copyattempt/copyattempt_form.php');


/**
 * Quiz report to help teachers manually grade questions that need it.
 *
 * This report basically provides two screens:
 * - List question that might need manual grading (or optionally all questions).
 * - Provide an efficient UI to grade all attempts at a particular question.
 *
 * @copyright  2006 Gustav Delius
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_copyattempt_report extends quiz_default_report 
{
    const DEFAULT_PAGE_SIZE = 5;
    const DEFAULT_ORDER = 'random';

    protected $cm;
    protected $quiz;
    protected $course;

    protected $context;

    /**
     * Report display; displays the main content of the report, and handles all report actions. 
     * 
     * @param mixed $quiz       The relevant quiz object.
     * @param mixed $cm         The coursemodule for the current quiz.
     * @param mixed $course     The course for the current quiz.
     * @return bool             True if the display was successful; false otherwise.
     */
    public function display($quiz, $cm, $course) 
    {
        global $CFG, $DB, $PAGE, $OUTPUT;

        //populate the method's fields
        $this->quiz = $quiz;
        $this->cm = $cm;
        $this->course = $course;

        //get a reference to the active context
        $this->context = get_context_instance(CONTEXT_MODULE, $cm->id);

        //start the quiz's display by printing the header
        $this->print_header_and_tabs($cm, $course, $quiz, 'copyattempt');

        //display the report's header
        echo $OUTPUT->heading(get_string('copysync', 'quiz_copyattempt'));

        //get arrays of all enrolled students and all attempts
        list($all_students, $all_attempts) = $this->get_students_and_attempts();

        //compute the target URL for the form manually, as Moodle URL's won't mix POST and GET
        //(yet the report module does, for some reason?)
        $base_url = $CFG->wwwroot . '/mod/quiz/report.php?id=' .$this->cm->id . '&mode=copyattempt';

        //create the copy/sync form; if relevant postdata exists, this will automatically wrap it
        $mform = new quiz_copyattempt_form($all_students, $all_attempts, $base_url);
        
        //if we're on this page due to a form submission, get the data
        $response = $mform->get_data();

        //perform the copy, if requested
        if($response)
        {
            //load the attempt object that's about to be copied
            $attempt = quiz_attempt::create($response->copyattempt);

            //ask the synchronization library to copy the attempt to the new user
            quiz_synchronization::copy_attempt_to_user($attempt, $response->copyto);  

            //indicate that the operation was performed
            echo html_writer::tag('div', get_string('copied', 'quiz_copyattempt'), array('class' => 'notifysuccess'));
            echo html_writer::tag('p', '');

            //clear the form
            $mform->set_data(new stdClass);
        }

        //display the synchronization form
        $mform->display();

        return true;
    }


    /**
     * Returns an array containing all enrolled students, and all existing attempts. 
     * 
     * @return array    An array whose first element is an array of enrolled student objects, and whose second element is an array of (completed?) attempt objects.
     */
    protected function get_students_and_attempts()
    {
        global $DB;

        //get the list of all students
        $all_students = get_enrolled_users($this->context, '', 0, 'u.id, u.firstname, u.lastname');

        //We'll use the following SQL query to retrieve all _completed_ attempts for this quiz, along with some grade information.
        //This requires the database structure to remain consistent, but also is a lot faster than using the Moodle APIs.
        $all_attempts_sql =           
            'SELECT qa.id, qa.attempt, qa.sumgrades as actualpoints, q.sumgrades as possiblepoints, q.decimalpoints, u.firstname, u.lastname FROM {quiz_attempts} qa
                    LEFT JOIN {quiz} q ON q.id = qa.quiz
                    LEFT JOIN {user} u on u.id = qa.userid
                    WHERE q.id = :qid AND qa.timefinish != 0
                    ORDER BY u.lastname';

        //get all attempts
        $all_attempts = $DB->get_records_sql($all_attempts_sql, array('qid' => $this->quiz->id));

        //return the students and attempts found
        return array($all_students, $all_attempts);
    }

}
