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
 * @package    moodlecore
 * @subpackage backup-moodle2
 */


defined('MOODLE_INTERNAL') || die();


/**
 * restore plugin class that provides the necessary information
 * needed to restore one turmultiplechoice qtype plugin
 *
 */
class restore_qtype_turmultiplechoice_plugin extends restore_qtype_plugin {

    /**
     * Returns the paths to be handled by the plugin at question level
     */
    protected function define_question_plugin_structure() {

        $paths = array();

        // This qtype uses question_answers, add them
        $this->add_question_question_answers($paths);

        // Add own qtype stuff
        $elename = 'turmultiplechoice';
        // we used get_recommended_name() so this works
        $elepath = $this->get_pathfor('/turmultiplechoice');
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths; // And we return the interesting paths
    }

    /**
     * Process the qtype/turmultiplechoice element
     */
    public function process_turmultiplechoice($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Detect if the question is created or mapped
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = (bool) $this->get_mappingid('question_created', $oldquestionid);

        // If the question has been created by restore, we need to create its
        // qtype_turmultichoice_options too
        if ($questioncreated) {
            // Adjust some columns
            $data->questionid = $newquestionid;

            // It is possible for old backup files to contain unique key violations.
            // We need to check to avoid that.
            if (!$DB->record_exists('qtype_turmultichoice_options', array('questionid' => $data->questionid))) {
                $newitemid = $DB->insert_record('qtype_turmultichoice_options', $data);
                $this->set_mapping('qtype_turmultichoice_options', $oldid, $newitemid);
            }
        }
    }

    public function recode_response($questionid, $sequencenumber, array $response) {
        if (array_key_exists('_order', $response)) {
            $response['_order'] = $this->recode_choice_order($response['_order']);
        }
        return $response;
    }

    /**
     * Recode the choice order as stored in the response.
     * @param string $order the original order.
     * @return string the recoded order.
     */
    protected function recode_choice_order($order) {
        $neworder = array();
        foreach (explode(',', $order) as $id) {
            if ($newid = $this->get_mappingid('question_answer', $id)) {
                $neworder[] = $newid;
            }
        }
        return implode(',', $neworder);
    }

    /**
     * Given one question_states record, return the answer
     * recoded pointing to all the restored stuff for turmultiplechoice questions
     *
     * answer are two (hypen speparated) lists of comma separated question_answers
     * the first to specify the order of the answers and the second to specify the
     * responses. Note the order list (the first one) can be optional
     */
    public function recode_legacy_state_answer($state) {
        $answer = $state->answer;
        $orderarr = array();
        $responsesarr = array();
        $lists = explode(':', $answer);
        // if only 1 list, answer is missing the order list, adjust
        if (count($lists) == 1) {
            $lists[1] = $lists[0]; // here we have the responses
            $lists[0] = '';        // here we have the order
        }
        // Map order
        if (!empty($lists[0])) {
            foreach (explode(',', $lists[0]) as $id) {
                if ($newid = $this->get_mappingid('question_answer', $id)) {
                    $orderarr[] = $newid;
                }
            }
        }
        // Map responses
        if (!empty($lists[1])) {
            foreach (explode(',', $lists[1]) as $id) {
                if ($newid = $this->get_mappingid('question_answer', $id)) {
                    $responsesarr[] = $newid;
                }
            }
        }
        // Build the final answer, if not order, only responses
        $result = '';
        if (empty($orderarr)) {
            $result = implode(',', $responsesarr);
        } else {
            $result = implode(',', $orderarr) . ':' . implode(',', $responsesarr);
        }
        return $result;
    }

    /**
     * Return the contents of this qtype to be processed by the links decoder
     */
    public static function define_decode_contents() {

        $contents = array();

        $fields = array('correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback');
        $contents[] = new restore_decode_content('qtype_turmultichoice_options',
                $fields, 'qtype_turmultichoice_options');

        return $contents;
    }
}
