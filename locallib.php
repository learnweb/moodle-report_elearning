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
 * Reports implementation
 *
 * @package    report_elearning
 * @copyright  2015 BFH-TI, Luca Bösch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/formslib.php');

/**
 * Settings form for the elearning report.
 *
 * @copyright  2015 BFH-TI, Luca Bösch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * works for now
 */
class report_elearning_form extends moodleform {

    /**
     * Define the form.
     */
    protected function definition() {
        global $DB;
        $mform = $this->_form;
        $all = array();

        // All needs to be on very first place.
        $allcount = '';
        $visiblecount = get_coursecategorycoursecount(get_coursecategorypath(0), true);
        $invisiblecount = get_coursecategorycoursecount(get_coursecategorypath(0), false);

        $allcount .= " (" . $visiblecount . " " . get_string('shownplural', 'report_elearning') . ", " . $invisiblecount .
                    " " . get_string('hiddenplural', 'report_elearning') . ", " . get_string('total', 'report_elearning') .
                    " " . ($invisiblecount + $visiblecount) . ")";
        $all[0] = get_string('all', 'report_elearning') . $allcount;

        $coursecat = $DB->get_records("course_categories", array(), "sortorder ASC", "id,name,path");
        foreach ($coursecat as $id => $cat) {
            $components = preg_split('/\//', $cat->path);
            array_shift($components);
            $fullname = '';
            foreach ($components as $component) {
                $fullname .= ' / ' . format_string($coursecat[$component]->name);
            }
            $visiblecount = get_coursecategorycoursecount(get_coursecategorypath($cat->id), true);
            $invisiblecount = get_coursecategorycoursecount(get_coursecategorypath($cat->id), false);

            $fullname .= " (" . $visiblecount . " " . get_string('shownplural', 'report_elearning') . ", " . $invisiblecount .
                    " " . get_string('hiddenplural', 'report_elearning') . ", " . get_string('total', 'report_elearning') .
                    " " . ($invisiblecount + $visiblecount) . ")";
            $all[$id] = substr($fullname, 3);
        }

        if (count($all) == 2) {
            // I.e., the case for (all) plus only 1 entry, making (all) redundant ...
            unset($all[0]);
        }

        $mform->addElement('select', 'elearningcategory', get_string('category', 'report_elearning'), $all);

        $mform->addElement('checkbox', 'elearningvisibility', get_string('onlyshown', 'report_elearning'),
                $mform->getSubmitValue('elearningvisibility'));
        $mform->addElement('checkbox', 'nonews', get_string('nonewsforum', 'report_elearning'),
                $mform->getSubmitValue('nonews'));

        $mform->addElement('submit', 'submitbutton', get_string('choose', 'report_elearning'));
    }

}


// get all blocks in that course
// possible problem the fact that we now load all blocknames/coursenames dynamically adds O(n) complexity where n is
// the number of courses. That will blow up on deployment
// TODO fix that.
function blocks_DB($courseid){
    global $DB;
    $coursecontext= context_course::instance($courseid);
    $coursecontextid = $coursecontext -> id;
    /*$sql = "SELECT blockinstanceid, contextid, pagetype, visible FROM {block_positions}
            WHERE contextid = $coursecontextid;";*/
    // I don't have enough ḱnowledge about contexts yet to evaluate wether I'll need to look for subcontexts inside
    // of a course
    // Todo find out
    $sql = "SELECT DISTINCT blockname, count(blockname) AS count
            FROM {block_instances}
            WHERE parentcontextid = $coursecontextid
            GROUP BY blockname";
    $blocks = $DB -> get_records_sql($sql);
    return $blocks;
}

/**
 * This function limits the length of a string, cutting in the middle
 *
 * @see http://www.php.net/manual/en/function.substr.php#84775
 *
 * @param string $value Input string
 * @param int $length Admissible length of string
 * @return string String with reduced length
 */
function limitstringlength($value, $length = MAX_STRING_LEN) {
    if (strlen($value) >= $length) {
        $lengthmax = ($length / 2) - 3;
        $start = strlen($value) - $lengthmax;
        $limited = substr($value, 0, $lengthmax);
        $limited .= " ... ";
        $limited .= substr($value, $start, $lengthmax);
    } else {
        $limited = $value;
    }
    // Take care to badly-escaped strings ...
    return htmlentities($limited, ENT_QUOTES, 'UTF-8');
}

/**
 * Returns the amount of courses in a certain category and its subcategories.
 *
 * @param string $path The category path (e.g. /5/6).
 * @param boolean $onlyvisible Whether only visible courses should count.
 * @uses array $DB: database object
 * @return int $sql The report table creation SQL.
 * @throws dml_exception
 */
function get_coursecategorycoursecount($path, $onlyvisible=false) {
    global $DB;
    $sql = "  SELECT c.id, cc.path
                FROM {course} c
                JOIN {course_categories} cc
                  ON cc.id = c.category
               WHERE (cc.path LIKE CONCAT( '$path/%' )
                  OR cc.path LIKE CONCAT( '$path' ))";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "AND ((c.visible != 0) AND (cc.visible != 0))";
    } else {
        $sql .= "AND ((c.visible = 0) OR (cc.visible = 0))";
    }
    return(count($DB->get_records_sql($sql)));
}

function get_all_mod_names(){
    $pluginman = core_plugin_manager::instance();
    $returnarray = array();
    foreach (array("mod") as $type) {
        $pluginarray = $pluginman->get_plugins_of_type($type);
        foreach ($pluginarray as $pluigin) {
            array_push($returnarray, $pluigin->name);
        }
    }

    return $returnarray;
}

/**
 * Returns the array of an e-learning report table course row.
 *
 * @param int $courseid The course id.
 * @param boolean $onlyvisible Whether only visible courses should count.
 * @param boolean $nonews Whether news should be excluded from count.
 * @uses array $CFG: system configuration
 * @uses array $DB: database object
 * @return array $returnarray The report table array.
 * @throws dml_exception
 */
function get_coursetablecontent($courseid, $onlyvisible=false, $nonews=false){
    $sql = "SELECT mc.id, mc.fullname,";
    $pluginarray = get_all_mod_names();
    foreach ($pluginarray as $plugin){
        $sql .= "(
                      SELECT COUNT( * )
                        FROM {{$plugin}} r
                        JOIN {course} c
                          ON c.id = r.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";

        if ($onlyvisible == true) {
            $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        if ($nonews == true and $plugin == "forum") {
            $sql .= "       AND f.type != 'news'";
        }
        $sql .= "     ) AS $plugin,";
    }
    // kill that trailing comma
    $sql = mb_substr($sql, 0, -1);

    $sql .= " FROM {course} mc
              WHERE mc.id = ?
              ORDER BY mc.sortorder";

    //ok THIS is weird why is the table content handled in locallib but for the other table it's handled in index??
    //TODO fix that mess
    return $sql;
}

/**
 * Returns a formulated (fullname / fullname) category / sub-category path.
 *
 * @param string $intpath A path with the ids and slashes (e.g. /2/8/10).
 * @return string $stringpath A formulated path.
 */
function get_stringpath($intpath) {
    global $DB;
    $components = preg_split('/\//', $intpath);
    array_shift($components);
    $fullname = '';
    foreach ($components as $component) {
        $fullname .= ' / ' . format_string($DB->get_field('course_categories', 'name', array('id' => $component)));
    }
    return substr($fullname, 3);
}

/**
 * Return a instance id (course category) when you know the context.
 * @param int $id A context id.
 * @return int The according context id.
 */
function get_instancecontext($id) {
    global $DB;
    if ($id == 0) {
        return 0;
    } else {
        $instances = ($DB->get_records_sql("SELECT id"
                . "                           FROM {context}"
                . "                          WHERE instanceid = " . $id
                . "                            AND contextlevel = 40"));
        foreach ($instances as $instance) {
            $returnvalue = $instance->id;
        }
        return $returnvalue;
    }
}

/**
 * Return a course category path with a given course category id.
 * @param int $id A course category id.
 * @return string The according course category path.
 */
function get_coursecategorypath($id) {
    global $DB;
    if ($id == 0) {
        return "";
    } else {
        $categorypath = $DB->get_field('course_categories', 'path', array('id' => $id));
        return $categorypath;
    }
}
