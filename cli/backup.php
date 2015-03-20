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
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/admin/tool/coursestore/locallib.php');

// Get a list of the course backups.
$sqlcommon = "SELECT tcs.id,
               tcs.backupfilename,
               tcs.fileid,
               tcs.chunksize,
               tcs.totalchunks,
               tcs.chunknumber,
               tcs.timecreated,
               tcs.timecompleted,
               tcs.timechunksent,
               tcs.timechunkcompleted,
               tcs.chunkretries,
               tcs.status,
               tcs.isbackedup,
               f.id AS f_fileid,
               f.contenthash,
               f.pathnamehash,
               f.filename,
               f.userid,
               f.filesize,
               f.timecreated AS filetimecreated,
               f.timemodified AS filetimemodified,
               cr.id AS courseid,
               cr.fullname AS coursefullname,
               cr.shortname AS courseshortname,
               cr.category,
               cr.startdate AS coursestartdate,
               cc.id as categoryid,
               cc.name as categoryname
        FROM {files} f
        INNER JOIN {context} ct on f.contextid = ct.id
        INNER JOIN {course} cr on ct.instanceid = cr.id
        INNER JOIN {course_categories} cc on cr.category = cc.id";

$sqlselect = "SELECT tcs.id,
               tcs.backupfilename,
               tcs.fileid,
               tcs.chunksize,
               tcs.totalchunks,
               tcs.chunknumber,
               tcs.timecreated,
               tcs.timecompleted,
               tcs.timechunksent,
               tcs.timechunkcompleted,
               tcs.chunkretries,
               tcs.status,
               tcs.isbackedup,
               f.id AS f_fileid,
               tcs.contenthash,
               tcs.pathnamehash,
               f.filename,
               tcs.userid,
               tcs.filesize,
               tcs.filetimecreated,
               tcs.filetimemodified,
               tcs.courseid,
               tcs.coursefullname,
               tcs.courseshortname,
               cr.category,
               tcs.coursestartdate,
               tcs.categoryid,
               tcs.categoryname
        FROM {files} f
        INNER JOIN {context} ct on f.contextid = ct.id
        INNER JOIN {course} cr on ct.instanceid = cr.id
        INNER JOIN {course_categories} cc on cr.category = cc.id";

$sql = $sqlcommon . "
        LEFT JOIN {tool_coursestore} tcs on tcs.fileid = f.id
        WHERE tcs.id IS NULL
        AND ct.contextlevel = :contextcourse1
        AND   f.mimetype IN ('application/vnd.moodle.backup', 'application/x-gzip')
        UNION
        " . $sqlselect . "
        INNER JOIN {tool_coursestore} tcs on tcs.fileid = f.id
        WHERE ct.contextlevel = :contextcourse2
        AND   f.mimetype IN ('application/vnd.moodle.backup', 'application/x-gzip')
        AND   (tcs.status IN (:statusnotstarted, :statusinprogress)
              OR (tcs.status = :statuserror AND tcs.chunkretries <= :maxattempts))
        UNION
        " . $sqlselect . "
        RIGHT JOIN {tool_coursestore} tcs on tcs.fileid = f.id
        WHERE f.id IS NULL
        AND tcs.isbackedup = 1";
$maxattempts = get_config('maxattempts', 'tool_coursestore');
$params = array('statusnotstarted' => tool_coursestore::STATUS_NOTSTARTED,
                'statuserror' => tool_coursestore::STATUS_ERROR,
                'statusinprogress' => tool_coursestore::STATUS_INPROGRESS,
                'contextcourse1' => CONTEXT_COURSE,
                'contextcourse2' => CONTEXT_COURSE,
                'maxattempts' => $maxattempts
                );
$rs = $DB->get_recordset_sql($sql, $params);

foreach ($rs as $coursebackup) {
    if (!isset($coursebackup->status)) {
        // The record hasn't been input in the course restore table yet.
        $cs = new stdClass();
        $cs->backupfilename = $coursebackup->filename;
        $cs->fileid = $coursebackup->f_fileid;
        $cs->chunksize = tool_coursestore::get_config_chunk_size();
        $cs->totalchunks = tool_coursestore::calculate_total_chunks($cs->chunksize, $coursebackup->filesize);
        $cs->chunknumber = 0;
        $cs->status = tool_coursestore::STATUS_NOTSTARTED;
        $cs->isbackedup = 0; // No copy has been created yet.
        $backupid = $DB->insert_record('tool_coursestore', $cs);

        $coursebackup->id = $backupid;
        $coursebackup->backupfilename = $cs->backupfilename;
        $coursebackup->fileid = $cs->fileid;
        $coursebackup->chunksize = $cs->chunksize;
        $coursebackup->totalchunks = $cs->totalchunks;
        $coursebackup->chunknumber = $cs->chunknumber;
        $coursebackup->timecreated = 0;
        $coursebackup->timecompleted = 0;
        $coursebackup->timechunksent = 0;
        $coursebackup->timechunkcompleted = 0;
        $coursebackup->chunkretries = 0;
        $coursebackup->status = $cs->status;
    }
    $result = tool_coursestore::send_backup($coursebackup);
    if ($result) {
        $delete = tool_coursestore::delete_backup($coursebackup);
        if (!$delete) {
            echo(get_string('deletefailed', 'tool_coursestore', $coursebackup->filename) . "\n");
        }
    } else {
        echo(get_string('backupfailed', 'tool_coursestore', $coursebackup->filename) . "\n");
    }
}
$rs->close();
