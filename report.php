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
require_once($CFG->dirroot . '/mod/quiz/report/copyattempt/syncattempt_form.php');


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

        // Get an array of all quizzes offered in this course.
        $all_quizzes = $this->get_applicable_quizzes();

        //compute the target URL for the form manually, as Moodle URL's won't mix POST and GET
        //(yet the report module does, for some reason?)
        // FIXME: This isn't true- this should be changed.
        $base_url = $CFG->wwwroot . '/mod/quiz/report.php?id=' .$this->cm->id . '&mode=copyattempt';

        //create the copy/sync form; if relevant postdata exists, this will automatically wrap it
        $copy_form = new quiz_copyattempt_form($all_students, $all_attempts, $base_url);
        $sync_form = new quiz_syncattempt_form($all_quizzes, $base_url);

        // Handle any requested attempt copy or sync actions.
        $this->handle_attempt_copy($copy_form);
        $this->handle_partner_sync($sync_form);

        //display the copy/sync form
        $copy_form->display();
        $sync_form->display();


        return true;
    }

    /**
     * Synchronizes all quiz attempts between partners, based on an array of partnerships.
     * 
     * @param array $partnerships A list of attempts to be synchronized, in source => destination format.
     * @return int The amount of partnerships synchronized successfully.
     */
    protected function synchronize_via_partnerships($partnerships) {
        
        global $DB;

        // Start a count of successfully synchronized partnerships.
        $count = 0;

        // Find all attempts at the current quiz.
        $attempts = $DB->get_records('quiz_attempts', array('quiz' => $this->quiz->id));
        
        // For each of the provided attempts...
        foreach($attempts as $attempt_data) {

            // Wrap the data in an attempt object.
            $attempt = new quiz_attempt($attempt_data, $this->quiz, $this->cm, $this->course);

            // Get the User ID for the user taking the quiz.
            $respondant = $attempt->get_userid();

            // If this is a finished quiz attempt, and the user has a partner...
            if(!empty($partnerships[$respondant]) && $attempt->is_finished()) {
                
                // ... copy the attempt to that partner.
                quiz_synchronization::copy_attempt_to_user($attempt, $partnerships[$respondant]);

                // Increase the correctly synchronized count.
                ++$count;

            }
        }

        return $count;
    }



    /** 
     * Handles the submission of the "quiz attempt copy" form.
     */
    protected function handle_attempt_copy($copy_form) {

        //if we're on this page due to a form submission, get the data
        $response = $copy_form->get_data();

        //perform the copy, if requested
        if($copy_form->is_submitted() && $response)
        {
            //load the attempt object that's about to be copied
            $attempt = quiz_attempt::create($response->copyattempt);

            //ask the synchronization library to copy the attempt to the new user
            quiz_synchronization::copy_attempt_to_user($attempt, $response->copyto);  

            //indicate that the operation was performed
            echo html_writer::tag('div', get_string('copied', 'quiz_copyattempt'), array('class' => 'notifysuccess'));
            echo html_writer::tag('p', '');

            //clear the form
            $copy_form->set_data(new stdclass);
        }
    }

    /**
     * Returns an array of all quizzes which can be used as a source for partnerships.
     */ 
    protected function get_applicable_quizzes() {

       global $DB;

       //TODO: replace with "get all quizzes with attempts" or "get all quizzes with partner questions"
       return $DB->get_records('quiz', array('course' => $this->course->id), null,'id,name');

    }

    /**
     * Returns an array containing all enrolled students, and all existing attempts. 
     * 
     * @return array    An array whose first element is an array of enrolled student objects, and whose second element is an array of (completed?) attempt objects.
     */
    protected function get_students_and_attempts($quiz_id = null)
    {
        global $DB;

        // If no quiz ID was provided, assume the current quiz.
        if($quiz_id === null) {
            $quiz_id = $this->quiz->id;
        }

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

        //get all attempts for the current quiz
        $all_attempts = $DB->get_records_sql($all_attempts_sql, array('qid' => $quiz_id));

        //return the students and attempts found
        return array($all_students, $all_attempts);
    }

    /**
     * Handles submission of the partner synchronization form.
     *
     * @param moodleform $sync_form The partner synchronization form to be handled.
     */ 
    protected function handle_partner_sync($sync_form) {

        global $OUTPUT;

        //if we're on this page due to a form submission, get the data
        $response = $sync_form->get_data();

        //perform the sync, if requested
        if($sync_form->is_submitted() && $response)
        {

            //Get a list of partnerships in present in the given quiz.
            $partnerships = $this->find_partnerships($response->syncfrom);

            // If we found a valid list of partnerships...
            if($partnerships) {

                //TODO: check to see if attempt already exists

                // Synchronize each of the partnerships...
                $count = $this->synchronize_via_partnerships($partnerships);

                // ... and report the count.
                $OUTPUT->notification(get_string('synccount', 'quiz_copyattempt', $count));
            }
        }
    }

    /**
     * Attempts to identify all known partnerships in a given quiz.
     *
     * @param int The ID of the quiz to read from.
     */ 
    protected function find_partnerships($quiz_id) {

        global $DB;

        // Start a new array mapping partnerships.
        $partners = array();

        // Create a new instance of the quiz, for reference use.
        $quiz = quiz::create($quiz_id, $USER->id);

        // Find all attempts at the given quiz.
        $attempts = $DB->get_records('quiz_attempts', array('quiz' => $quiz_id));        

        // For each of the given quiz attempts...
        foreach($attempts as $attempt_data) {

            // Create a new attempt object for the given quiz attempt...
            $attempt = new quiz_attempt($attempt_data, $quiz, $this->cm, $this->course);

            // If this was a preview attempt, exclude it from the list of partnerships.
            if(!$attempt->is_preview()) {
        
                // Find the partner for a given quiz attempt, if one exists.
                $partner = quiz_synchronization::get_partner($attempt);

                // If we've found a quiz with a valid partnership
                if($partner) {
                    
                    // Add the partnership to our list of partners.
                    // Note that we add the relationship both ways; this allows us to take advantage of 
                    // PHP's semi-fast hashed arrays, at the cost of doubling the size of the array.
                    $partners[$attempt->get_userid()] = $partner;
                    $partners[$partner] = $attempt->get_userid();
                }
            }

        }

        // Return the created list of partners.
        return $partners;
    }




}
