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
               f.filename,
               f.userid,
               f.filesize,
               f.timecreated,
               f.timemodified,
               cr.id AS courseid,
               cr.fullname,
               cr.shortname,
               cr.category,
               cr.startdate,
               cc.id as categoryid,
               cc.name as categoryname
        FROM {files} f
        INNER JOIN {context} ct on f.contextid = ct.id
        INNER JOIN {course} cr on ct.instanceid = cr.id
        INNER JOIN {course_categories} cc on cr.category = cc.id
        LEFT JOIN {tool_coursestore} tcs on tcs.fileid = f.id
            AND (tcs.status != :statuserror)
        WHERE ct.contextlevel = :contextcourse
        AND   f.mimetype IN ('application/vnd.moodle.backup', 'application/x-gzip')
        ORDER BY f.timecreated";

$params = array('statusnotstarted' => tool_coursestore::STATUS_NOTSTARTED,
                'statuserror' => tool_coursestore::STATUS_ERROR,
                'contextcourse' => CONTEXT_COURSE
                );
$rs = $DB->get_recordset_sql($sql, $params);
$backups = array();
$totalbackups = 0;
foreach ($rs as $coursebackup) {
    if (!$coursebackup->id) {
        // The record hasn't been input in the course restore table yet.
        $cs = new stdClass();
        $cs->backupfilename = $coursebackup->filename;
        $cs->fileid = $coursebackup->f_fileid;
        $cs->chunksize = tool_coursestore::get_config_chunk_size();
        $cs->totalchunks = tool_coursestore::calculate_total_chunks($cs->chunksize, $coursebackup->filesize);
        $cs->chunknumber = 0;
        $cs->status = tool_coursestore::STATUS_NOTSTARTED;
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
        $coursebackup->status = $cs->status;
    }
    $backups[] = $coursebackup;
    $totalbackups++;
}


// Now send the backups.
foreach ($backups as $backup) {
    if($backup->status == tool_coursestore::STATUS_NOTSTARTED || $backup->status == tool_coursestore::STATUS_INPROGRESS) {
        $result = tool_coursestore::send_backup($backup);
        if (!$result) {
            echo(get_string('backupfailed', 'tool_coursestore', $backup->filename) . "\n");
        }
    }
}

