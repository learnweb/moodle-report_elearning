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

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class _form extends moodleform {

    /**
     * Define the form.
     */
    protected function definition() {
        global $DB;
        $mform = $this->_form;
        $all = array();

        // All needs to be on very first place.
        $count = get_coursecategorycoursecount(get_coursecategorypath(0));

        $allcount = " (" . get_string('total', 'report_elearning') .
            " " . $count . ")";
        $all[0] = get_string('all', 'report_elearning') . $allcount;

        $coursecat = $DB->get_records("course_categories", array(), "sortorder ASC", "id,name,path");
        foreach ($coursecat as $id => $cat) {
            $components = preg_split('/\//', $cat->path);
            array_shift($components);
            $fullname = '';
            foreach ($components as $component) {
                $fullname .= ' / ' . format_string($coursecat[$component]->name);
            }
            $count = get_coursecategorycoursecount(get_coursecategorypath($cat->id));

            $fullname .= " (" . get_string('total', 'report_elearning') .
                " " . ($count) . ")";
            $all[$id] = substr($fullname, 3);
        }

        if (count($all) == 2) {
            // I.e., the case for (all) plus only 1 entry, making (all) redundant ...
            unset($all[0]);
        }

        $mform->addElement('select', 'elearningcategory', get_string('category', 'report_elearning'), $all);
        $mform->addElement('submit', 'submitbutton', get_string('choose', 'report_elearning'));
    }

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
function get_coursecategorycoursecount($path) {
    global $DB;
    $sql = "  SELECT c.id, cc.path
                FROM {course} c
                JOIN {course_categories} cc
                  ON cc.id = c.category
               WHERE (cc.path LIKE CONCAT( '$path/%' )
                  OR cc.path LIKE CONCAT( '$path' ))";
    return(count($DB->get_records_sql($sql)));
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

function get_all_courses($cats) {
    foreach ($cats as $cat) {
        $cat->childs = array();
    }
    global $DB;

    $c = $DB->get_records_sql("SELECT id, category FROM {course} WHERE NOT category = 0");
    foreach ($c as $course) {
        array_push($cats[$course->category]->childs, $course->id);
    }

    return $cats;

}

const TYPES = array("mod", "block");
function get_table_headers() {
    $returnarray = get_all_plugin_names(TYPES);
    array_unshift($returnarray, "category");
    array_unshift($returnarray, "id");
    array_push($returnarray, "Sum");
    array_push($returnarray, "Sum without files and folders");
    return $returnarray;
}

function get_shown_table_headers() {
    $pluginman = core_plugin_manager::instance();
    $returnarray = array();
    foreach (TYPES as $type) {
        // Get all plugins of a given type.
        $pluginarray = $pluginman->get_plugins_of_type($type);
        // Plugins will be returned as objects so get the name of each and push it into a new array.
        foreach ($pluginarray as $plugin) {
            array_push($returnarray, $plugin->displayname . "\n($type)");
        }
    }
    array_unshift($returnarray, "Category");
    array_unshift($returnarray, "ID");
    array_push($returnarray, "Sum");
    array_push($returnarray, "Sum without files and folders");
    return $returnarray;
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