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
 * Filter for filesize fields.
 *
 * @package    tool_coursestore
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/user/filters/lib.php');

class coursestore_filter_filesize extends user_filter_text {

    /**
     * Returns an array of comparison operators
     * @return array of comparison operators
     */
    public function getOperators() {
        return array(0 => get_string('filtermorethan', 'tool_coursestore'),
                     1 => get_string('filterlessthan', 'tool_coursestore'),
                     2 => get_string('filterisequalto', 'tool_coursestore'),
                     3 => get_string('isempty', 'filters'));
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    public function setupForm(&$mform) {
        $objs = array();
        $objs['select'] = $mform->createElement('select', $this->_name.'_op', null, $this->getOperators());
        $objs['text'] = $mform->createElement('text', $this->_name, null);
        $objs['select']->setLabel(get_string('limiterfor', 'filters', $this->_label));
        $objs['text']->setLabel(get_string('valuefor', 'filters', $this->_label));
        $grp =& $mform->addElement('group', $this->_name.'_grp', $this->_label, $objs, '', false);
        $mform->setType($this->_name, PARAM_RAW);
        $mform->disabledIf($this->_name, $this->_name.'_op', 'eq', 3);
        if ($this->_advanced) {
            $mform->setAdvanced($this->_name.'_grp');
        }
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    public function check_data($formdata) {
        $field    = $this->_name;
        $operator = $field.'_op';

        if (array_key_exists($operator, $formdata)) {
            if ($formdata->$operator != 3 and $formdata->$field == '') {
                // No data - no change except for empty filter.
                return false;
            }
            // If field value is set then use it, else it's null.
            $fieldvalue = null;
            if (isset($formdata->$field)) {
                $fieldvalue = $formdata->$field;
            }
            return array('operator' => (int)$formdata->$operator, 'value' => $fieldvalue);
        }

        return false;
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return array sql string and $params
     */
    public function get_sql_filter($data) {
        $operator = $data['operator'];
        $value    = intval($data['value']);
        $field    = $this->_field;

        if ($operator != 3 and $value === '') {
            return '';
        }

        switch($operator) {
            case 0: // More than.
                $res .= "$field > $value";
                break;
            case 1: // Less than.
                $res .= "$field < $value";
                break;
            case 2: // Equal to.
                $res .= "$field = $value";
                break;
            case 3: // Empty.
                $res .= "$field = ''";
                break;
            default:
                return '';
        }
        return array($res, array());
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    public function get_label($data) {
        $operator  = $data['operator'];
        $value     = $data['value'];
        $operators = $this->getOperators();

        $a = new stdClass();
        $a->label    = $this->_label;
        $a->value    = '"'.s($value).'"';
        $a->operator = $operators[$operator];

        switch ($operator) {
            case 0: // More than.
            case 1: // Ledd than.
            case 2: // Equal to.
                return get_string('textlabel', 'filters', $a);
            case 3: // Empty.
                return get_string('textlabelnovalue', 'filters', $a);
        }

        return '';
    }
}
