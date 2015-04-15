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
 * This file contains the Filter API.
 *
 * @package    tool_coursestore
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/admin/tool/coursestore/filters/coursestore_filter_forms.php');
require_once($CFG->dirroot.'/admin/tool/coursestore/filters/filesize.php');
require_once($CFG->dirroot.'/admin/tool/coursestore/filters/text.php');
require_once($CFG->dirroot.'/admin/tool/coursestore/filters/date.php');
require_once($CFG->dirroot.'/admin/tool/coursestore/filters/select.php');



class coursestore_filtering {
    private $prefix;
    public $_fields;
    public $_addform;
    public $_activeform;

    /**
     * Contructor
     * @param array $fieldnames array of visible fields
     * @param string $baseurl base url used for submission/return, null if the same of current page
     * @param array $extraparams extra page parameters
     */
    public function __construct($prefix, $fieldnames = null, $baseurl = null, $extraparams = null) {
        global $SESSION;

        if (!empty($prefix)) {
             $this->prefix = $prefix;
        }

        if (!isset($SESSION->coursestore_filtering[$this->prefix])) {
            $SESSION->coursestore_filtering[$this->prefix] = array();
        }

        if (empty($fieldnames)) {
            $fieldnames = array('coursefullname' => 0, 'backupfilename' => 1, 'filesize' => 1, 'filetimemodified' => 1, 'status' => 1);
        }

        $this->_fields  = array();

        foreach ($fieldnames as $fieldname => $advanced) {
            if ($field = $this->get_field($fieldname, $advanced)) {
                $this->_fields[$fieldname] = $field;
            }
        }

        // Fist the new filter form.
        $this->_addform = new coursestore_add_filter_form($baseurl, array('fields' => $this->_fields, 'extraparams' => $extraparams, 'prefix' => $this->prefix));
        if ($adddata = $this->_addform->get_data()) {
            foreach ($this->_fields as $fname => $field) {
                $data = $field->check_data($adddata);
                if ($data === false) {
                    continue; // Nothing new.
                }
                if (!array_key_exists($fname, $SESSION->coursestore_filtering[$this->prefix])) {
                    $SESSION->coursestore_filtering[$this->prefix][$fname] = array();
                }
                $SESSION->coursestore_filtering[$this->prefix][$fname][] = $data;
            }
            // Clear the form.
            $_POST = array();
            $this->_addform = new coursestore_add_filter_form($baseurl, array('fields' => $this->_fields, 'extraparams' => $extraparams, 'prefix' => $this->prefix));
        }

        // Now the active filters.
        $this->_activeform = new coursestore_active_filter_form($baseurl, array('fields' => $this->_fields, 'extraparams' => $extraparams, 'prefix' => $this->prefix));
        if ($adddata = $this->_activeform->get_data()) {
            if (!empty($adddata->removeall)) {
                $SESSION->coursestore_filtering[$this->prefix] = array();

            } else if (!empty($adddata->removeselected) and !empty($adddata->filter)) {
                foreach ($adddata->filter as $fname => $instances) {
                    foreach ($instances as $i => $val) {
                        if (empty($val)) {
                            continue;
                        }
                        unset($SESSION->coursestore_filtering[$this->prefix][$fname][$i]);
                    }
                    if (empty($SESSION->coursestore_filtering[$this->prefix][$fname])) {
                        unset($SESSION->coursestore_filtering[$this->prefix][$fname]);
                    }
                }
            }
            // Clear+reload the form.
            $_POST = array();
            $this->_activeform = new coursestore_active_filter_form($baseurl, array('fields' => $this->_fields, 'extraparams' => $extraparams, 'prefix' => $this->prefix));
        }
        // Now the active filters.
    }

    /**
     * Creates known filter if present
     * @param string $fieldname
     * @param boolean $advanced
     * @return object filter
     */
    public function get_field($fieldname, $advanced) {
        switch ($fieldname) {
            case 'coursefullname':
                return new coursestore_filter_text('coursefullname', get_string('coursefullname', 'tool_coursestore'), $advanced, 'coursefullname');
            case 'backupfilename':
                return new coursestore_filter_text('backupfilename', get_string('backupfilename', 'tool_coursestore'), $advanced, 'backupfilename');
            case 'filesize':
                return new coursestore_filter_filesize('filesize', get_string('filesize', 'tool_coursestore'), $advanced, 'filesize');
            case 'filetimemodified':
                return new coursestore_filter_date('filetimemodified', get_string('filetimemodified', 'tool_coursestore'), $advanced, 'filetimemodified');
            case 'status':
                return new coursestore_filter_select('status', get_string('status', 'tool_coursestore'), $advanced, 'status', $this->get_status_choices());
            default:
                return null;
        }
    }

    /**
     * Returns sql where statement based on active filters
     * @param string $extra sql
     * @param array $params named params (recommended prefix ex)
     * @return array sql string and $params
     */
    public function get_sql_filter($extra='', array $params=null) {
        global $SESSION;

        $sqls = array();
        if ($extra != '') {
            $sqls[] = $extra;
        }
        $params = (array)$params;

        if (!empty($SESSION->coursestore_filtering[$this->prefix])) {
            foreach ($SESSION->coursestore_filtering[$this->prefix] as $fname => $datas) {
                if (!array_key_exists($fname, $this->_fields)) {
                    continue; // Filter not used.
                }
                $field = $this->_fields[$fname];
                foreach ($datas as $i => $data) {
                    list($s, $p) = $field->get_sql_filter($data);
                    $sqls[] = $s;
                    $params = $params + $p;
                }
            }
        }

        if (empty($sqls)) {
            return array('', array());
        } else {
            $sqls = implode(' AND ', $sqls);
            return array($sqls, $params);
        }
    }

    /**
     * Returns parameters based on active filters
     * @param string $extra sql
     * @param array $params named params (recommended prefix ex)
     * @return array sql string and $params
     */
    public function get_param_filter(array $params=null) {
        global $SESSION;

        $params = (array)$params;

        if (!empty($SESSION->coursestore_filtering[$this->prefix])) {
            foreach ($SESSION->coursestore_filtering[$this->prefix] as $fname => $datas) {
                if (!array_key_exists($fname, $this->_fields)) {
                    continue; // Filter not used.
                }
                $field = $this->_fields[$fname];
                foreach ($datas as $i => $data) {
                    $p = $field->get_param_filter($data);
                    $params[$fname][] = $p;
                }
            }
        }

        return $params;
    }

    /**
     * Print the add filter form.
     */
    public function display_add() {
        $this->_addform->display();
    }

    /**
     * Print the active filter form.
     */
    public function display_active() {
        $this->_activeform->display();
    }

    /**
     * Generates a list of statuses for coursestore_filter_select
     * @return array A list of statuses
     */
    public function get_status_choices() {
        $statuses = tool_coursestore::get_statuses();

        if ($this->prefix == 'queue') {
            // Remove STATUS_FINISHED from a filter on the queue page.
            if (isset($statuses[tool_coursestore::STATUS_FINISHED])) {
                unset($statuses[tool_coursestore::STATUS_FINISHED]);
            }
            // Remove STATUS_CANCELLED from a filter on the queue page.
            if (isset($statuses[tool_coursestore::STATUS_CANCELLED])) {
                unset($statuses[tool_coursestore::STATUS_CANCELLED]);
            }
        }

        return $statuses;
    }
}