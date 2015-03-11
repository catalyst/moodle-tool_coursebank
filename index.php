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
 * Send course backups offsite.
 *
 * @package    tool_coursestore
 * @author     Ghada El-Zoghbi <ghada@catalyst-au.net>
 * @copyright  2015 Catalys IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/admin/tool/coursestore/lib.php');

//require_once($CFG->dirroot . '/backup/backup.class.php');
//require_once($CFG->dirroot . '/backup/controller/backup_controller.class.php');

// Only for admins or CLI.
if (!defined('CLI_SCRIPT') && !is_siteadmin()) {
    print_error('noaccesstofeature', 'tool_coursestore');
}

//require_login(null, false);
//require_capability('tool/coursestore:view', context_system::instance());


admin_externalpage_setup('toolcoursestore');

// Get a list of the course backups.
$sql = "SELECT tcs.id,
               tcs.backupfilename,
               tcs.fileid,
               tcs.chunksize,
               tcs.totalchunks,
               tcs.chunknumber,
               tcs.timecreated,
               tcs.timecompleted,
               tcs.timechunksent,
               tcs.timechunkcompleted,
               tcs.status,
               f.id AS f_fileid,
               f.contenthash,
               f.pathnamehash,
               f.contextid,
               f.filename,
               f.userid,
               f.filesize,
               f.timecreated,
               f.timemodified
        FROM {files} f
        INNER JOIN {context} c on f.contextid = c.id
        LEFT JOIN {tool_coursestore} tcs on tcs.fileid = f.id
            AND  f.timemodified > tcs.timecompleted
        WHERE c.contextlevel = " . CONTEXT_COURSE . "
        AND   f.mimetype IN ('application/vnd.moodle.backup', 'application/x-gzip')
        ORDER BY f.timecreated";

$rs = $DB->get_recordset_sql($sql);
$backupids = array();
foreach ($rs as $course) {
    echo "Course: " . $course->filename . "; id=" . $course->id . "<br />\n";
    if (!$course->id) {
        // The record hasn't been input in the course restore table yet.
        $cs = new stdClass();
        $cs->backupfilename = $course->filename;
        $cs->fileid = $course->f_fileid;
        $cs->chunksize = tool_coursestore::get_config_chunck_size();
        $cs->totalchunks = tool_coursestore::calculate_total_chunks($cs->chunksize, $course->filesize);
        $cs->chunknumber = 0;
        $cs->status = tool_coursestore::STATUS_NOTSTARTED;
        $backupids[] = $DB->insert_record('tool_coursestore', $cs);
     }
     else {
         // Insert the id of backups we need to send.
         $backupids[] = $course->id;
     }
}

echo "backupids=" . print_r($backupids, true);

// Get all the automated backups that have completed.
// $params = array('type'      => backup::TYPE_1COURSE,
//                 'operation' => backup::OPERATION_BACKUP,
//                 'status'    => backup::STATUS_FINISHED_OK,
//                 );

// $sql = "SELECT *
//         FROM {backup_controllers} bc
//         LEFT JOIN {tool_coursestore} tcs on bc.backupid = tcs.backupid
//         WHERE bc.type = :type
//         AND bc.operation = :operation
//         AND bc.status = :status
//         ";

