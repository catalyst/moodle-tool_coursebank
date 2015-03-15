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
 * Main page for user-facing course store interface
 *
 * @package    tool_coursestore
 * @author     Adam Riddell <adamr@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

defined('MOODLE_INTERNAL') || die;

$context = context_system::instance();
require_login();

admin_externalpage_setup('tool_coursestore');

$conncheck = optional_param('conn', null, PARAM_BOOL);
$url = new moodle_url('/admin/tool/coursestore/index.php');
$PAGE->set_url($url);
$PAGE->set_context($context);

$header = get_string('pluginname', 'tool_coursestore');
$PAGE->set_title($header);

$renderer = $PAGE->get_renderer('tool_coursestore');
echo $OUTPUT->header();

echo $OUTPUT->box_start();     // The forms section at the top
echo $OUTPUT->heading($header);
echo $renderer->course_store_conncheck($conncheck);
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
