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
                $fullname .= ' / ' . $coursecat[$component]->name;
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

/**
 * Returns the sql to create a e-learning report table.
 * 
 * @param int $category The category id.
 * @param boolean $onlyvisible Whether only visible courses should count.
 * @param boolean $nonews Whether news should be excluded from count.
 * @uses array $DB: database object
 * @return string $sql The report table creation SQL.
 */
function get_tablesql($category, $onlyvisible=false, $nonews=false) {
    if ($category === 0) {
        // Summary for the whole moodle installation.
        $sql = "SELECT DISTINCT '' AS mccid, '' AS Category, '' AS mccpath,
                   (
                        SELECT COUNT(*)
                          FROM {resource} r
                          JOIN {course} c
                            ON c.id = r.course
                          JOIN {course_categories} cc
                            ON cc.id = c.category";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "   ) AS FILEs,
                    (
                        SELECT COUNT(*)
                          FROM {folder} v
                          JOIN {course} c
                            ON c.id = v.course
                          JOIN {course_categories} cc
                            ON cc.id = c.category";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "   ) AS DIRECTORIEs,
                    (
                        SELECT COUNT(*)
                          FROM {page} p
                          JOIN {course} c
                            ON c.id = p.course
                          JOIN {course_categories} cc
                            ON cc.id = c.category";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "   ) AS PAGEs,
                    (
                        SELECT COUNT(*)
                          FROM {label} t
                          JOIN {course} c
                            ON c.id = t.course
                          JOIN {course_categories} cc
                            ON cc.id = c.category";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "   ) AS LABELs,
                    (
                        SELECT COUNT(*)
                          FROM {url} k
                          JOIN {course} c
                            ON c.id = k.course
                          JOIN {course_categories} cc
                            ON cc.id = c.category";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "   ) AS LINKs,
                    (
                        SELECT COUNT(*)
                          FROM {assign} a
                          JOIN {course} c
                            ON c.id = a.course
                          JOIN {course_categories} cc
                            ON cc.id = c.category";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "   ) AS ASSIGNs,
                    (
                        SELECT COUNT(*)
                          FROM {forum} b
                          JOIN {course} c
                            ON c.id = b.course
                          JOIN {course_categories} cc
                            ON cc.id = c.category";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        // Omit announcements forums.
        if ($nonews == true) {
            $sql .= "          AND f.type != 'news'";
        }
        $sql .= "   ) AS FORUMs,
                    (
                         SELECT COUNT(*)
                           FROM {feedback} l
                           JOIN {course} c
                             ON c.id = l.course
                          JOIN {course_categories} cc
                            ON cc.id = c.category";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "   ) AS FEEDBACKs,
                    (
                        SELECT COUNT(*)
                          FROM {quiz} q
                          JOIN {course} c
                            ON c.id = q.course
                          JOIN {course_categories} cc
                            ON cc.id = c.category";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "   ) AS QUIZs,
                    (
                        SELECT COUNT(*)
                          FROM {scheduler} s
                          JOIN {course} c
                            ON c.id = s.course
                          JOIN {course_categories} cc
                            ON cc.id = c.category";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "   ) AS SCHEDULERs,
                    (
                        SELECT COUNT(*)
                          FROM {survey} u
                          JOIN {course} c
                            ON c.id = u.course
                          JOIN {course_categories} cc
                            ON cc.id = c.category";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "   ) AS SURVEYs,
                    (
                        SELECT COUNT(*)
                          FROM {data} d
                          JOIN {course} c
                            ON c.id = d.course
                          JOIN {course_categories} cc
                            ON cc.id = c.category";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "   ) AS DBs,
                    (
                        SELECT COUNT(*)
                          FROM {glossary} g
                          JOIN {course} c
                            ON c.id = g.course
                          JOIN {course_categories} cc
                            ON cc.id = c.category";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "   ) AS GLOSSARIEs,
                    (
                        SELECT COUNT(*)
                          FROM {journal} j
                          JOIN {course} c
                            ON c.id = j.course
                          JOIN {course_categories} cc
                            ON cc.id = c.category";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "   ) AS JOURNALs,
                    (
                        SELECT COUNT(*)
                          FROM {wiki} w
                          JOIN {course} c
                            ON c.id = w.course
                          JOIN {course_categories} cc
                            ON cc.id = c.category";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "   ) AS WIKIs,
                    (
                        SELECT COUNT(*)
                          FROM {choice} h
                          JOIN {course} c
                            ON c.id = h.course
                          JOIN {course_categories} cc
                            ON cc.id = c.category";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "   ) AS CHOICEs,
                    (
                        SELECT COUNT(*)
                          FROM {choicegroup} o
                          JOIN {course} c
                            ON c.id = o.course
                          JOIN {course_categories} cc
                            ON cc.id = c.category";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "   ) AS CHOICESGROUP,
                    (
                        SELECT COUNT(*)
                          FROM {chat} z
                          JOIN {course} c
                            ON c.id = z.course
                          JOIN {course_categories} cc
                            ON cc.id = c.category";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "   ) AS CHATs,
                    (
                        SELECT COUNT(*)
                          FROM {workshop} e
                          JOIN {course} c
                            ON c.id = e.course
                          JOIN {course_categories} cc
                            ON cc.id = c.category";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "   ) AS WORKSHOPs
              FROM {course_categories} mcc";
    } else {
        // A category is given.
        $categorypath = get_coursecategorypath($category);
        $sql = "SELECT mcc.id AS mccid, mcc.name AS Category, mcc.path AS mccpath,
                       (
                            SELECT COUNT(*)
                              FROM {resource} r
                              JOIN {course} c
                                ON c.id = r.course
                              JOIN {course_categories} cc
                                ON cc.id = c.category
                             WHERE (cc.path LIKE CONCAT( '$categorypath/%' )
                                OR cc.path LIKE CONCAT( '$categorypath' ))";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "       ) AS FILEs,
                        (
                            SELECT COUNT(*)
                              FROM {folder} v
                              JOIN {course} c
                                ON c.id = v.course
                              JOIN {course_categories} cc
                                ON cc.id = c.category
                             WHERE (cc.path LIKE CONCAT( '$categorypath/%' )
                                OR cc.path LIKE CONCAT( '$categorypath' ))";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "       ) AS DIRECTORIEs,
                        (
                            SELECT COUNT(*)
                              FROM {page} p
                              JOIN {course} c
                                ON c.id = p.course
                              JOIN {course_categories} cc
                                ON cc.id = c.category
                             WHERE (cc.path LIKE CONCAT( '$categorypath/%' )
                                OR cc.path LIKE CONCAT( '$categorypath' ))";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "       ) AS PAGEs,
                        (
                            SELECT COUNT(*)
                              FROM {label} t
                              JOIN {course} c
                                ON c.id = t.course
                              JOIN {course_categories} cc
                                ON cc.id = c.category
                             WHERE (cc.path LIKE CONCAT( '$categorypath/%' )
                                OR cc.path LIKE CONCAT( '$categorypath' ))";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "       ) AS LABELs,
                        (
                            SELECT COUNT(*)
                              FROM {url} k
                              JOIN {course} c
                                ON c.id = k.course
                              JOIN {course_categories} cc
                                ON cc.id = c.category
                             WHERE (cc.path LIKE CONCAT( '$categorypath/%' )
                                OR cc.path LIKE CONCAT( '$categorypath' ))";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "       ) AS LINKs,
                        (
                            SELECT COUNT(*)
                              FROM {assign} a
                              JOIN {course} c
                                ON c.id = a.course
                              JOIN {course_categories} cc
                                ON cc.id = c.category
                             WHERE (cc.path LIKE CONCAT( '$categorypath/%' )
                                OR cc.path LIKE CONCAT( '$categorypath' ))";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "       ) AS ASSIGNs,
                        (
                            SELECT COUNT(*)
                              FROM {forum} b
                              JOIN {course} c
                                ON c.id = b.course
                              JOIN {course_categories} cc
                                ON cc.id = c.category
                             WHERE (cc.path LIKE CONCAT( '$categorypath/%' )
                                OR cc.path LIKE CONCAT( '$categorypath' ))";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        // Omit news forums.
        if ($nonews == true) {
            $sql .= "          AND b.type != 'news'";
        }
        $sql .= "       ) AS FORUMs,
                        (
                             SELECT COUNT(*)
                               FROM {feedback} l
                               JOIN {course} c
                                 ON c.id = l.course
                               JOIN {course_categories} cc
                                 ON cc.id = c.category
                              WHERE (cc.path LIKE CONCAT( '$categorypath/%' )
                                 OR cc.path LIKE CONCAT( '$categorypath' ))";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "       ) AS FEEDBACKs,
                        (
                            SELECT COUNT(*)
                              FROM {quiz} q
                              JOIN {course} c
                                ON c.id = q.course
                              JOIN {course_categories} cc
                                ON cc.id = c.category
                             WHERE (cc.path LIKE CONCAT( '$categorypath/%' )
                                OR cc.path LIKE CONCAT( '$categorypath' ))";
            // Omit hidden courses and categories.
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "       ) AS QUIZs,
                        (
                            SELECT COUNT(*)
                              FROM {scheduler} s
                              JOIN {course} c
                                ON c.id = s.course
                              JOIN {course_categories} cc
                                ON cc.id = c.category
                             WHERE (cc.path LIKE CONCAT( '$categorypath/%' )
                                OR cc.path LIKE CONCAT( '$categorypath' ))";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "       ) AS SCHEDULERs,
                        (
                            SELECT COUNT(*)
                              FROM {survey} u
                              JOIN {course} c
                                ON c.id = u.course
                              JOIN {course_categories} cc
                                ON cc.id = c.category
                             WHERE (cc.path LIKE CONCAT( '$categorypath/%' )
                                OR cc.path LIKE CONCAT( '$categorypath' ))";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "       ) AS SURVEYs,
                        (
                            SELECT COUNT(*)
                              FROM {data} d
                              JOIN {course} c
                                ON c.id = d.course
                              JOIN {course_categories} cc
                                ON cc.id = c.category
                             WHERE (cc.path LIKE CONCAT( '$categorypath/%' )
                                OR cc.path LIKE CONCAT( '$categorypath' ))";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "       ) AS DBs,
                        (
                            SELECT COUNT(*)
                              FROM {glossary} g
                              JOIN {course} c
                                ON c.id = g.course
                              JOIN {course_categories} cc
                                ON cc.id = c.category
                             WHERE (cc.path LIKE CONCAT( '$categorypath/%' )
                                OR cc.path LIKE CONCAT( '$categorypath' ))";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "       ) AS GLOSSARIEs,
                        (
                            SELECT COUNT(*)
                              FROM {journal} j
                              JOIN {course} c
                                ON c.id = j.course
                              JOIN {course_categories} cc
                                ON cc.id = c.category
                             WHERE (cc.path LIKE CONCAT( '$categorypath/%' )
                                OR cc.path LIKE CONCAT( '$categorypath' ))";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "       ) AS JOURNALs,
                        (
                            SELECT COUNT(*)
                              FROM {wiki} w
                              JOIN {course} c
                                ON c.id = w.course
                              JOIN {course_categories} cc
                                ON cc.id = c.category
                             WHERE (cc.path LIKE CONCAT( '$categorypath/%' )
                                OR cc.path LIKE CONCAT( '$categorypath' ))";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "       ) AS WIKIs,
                        (
                            SELECT COUNT(*)
                              FROM {choice} h
                              JOIN {course} c
                                ON c.id = h.course
                              JOIN {course_categories} cc
                                ON cc.id = c.category
                             WHERE (cc.path LIKE CONCAT( '$categorypath/%' )
                                OR cc.path LIKE CONCAT( '$categorypath' ))";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "       ) AS CHOICEs,
                        (
                            SELECT COUNT(*)
                              FROM {choicegroup} o
                              JOIN {course} c
                                ON c.id = o.course
                              JOIN {course_categories} cc
                                ON cc.id = c.category
                             WHERE (cc.path LIKE CONCAT( '$categorypath/%' )
                                OR cc.path LIKE CONCAT( '$categorypath' ))";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "       ) AS CHOICESGROUP,
                        (
                            SELECT COUNT(*)
                              FROM {chat} z
                              JOIN {course} c
                                ON c.id = z.course
                              JOIN {course_categories} cc
                                ON cc.id = c.category
                             WHERE (cc.path LIKE CONCAT( '$categorypath/%' )
                                OR cc.path LIKE CONCAT( '$categorypath' ))";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "       ) AS CHATs,
                        (
                            SELECT COUNT(*)
                              FROM {workshop} e
                              JOIN {course} c
                                ON c.id = e.course
                              JOIN {course_categories} cc
                                ON cc.id = c.category
                             WHERE (cc.path LIKE CONCAT( '$categorypath/%' )
                                OR cc.path LIKE CONCAT( '$categorypath' ))";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "       ) AS WORKSHOPs,
                        (
                            SELECT COUNT(*)
                              FROM {etherpadlite} x
                              JOIN {course} c
                                ON c.id = x.course
                              JOIN {course_categories} cc
                                ON cc.id = c.category
                             WHERE (cc.path LIKE CONCAT( '$categorypath/%' )
                                OR cc.path LIKE CONCAT( '$categorypath' ))";
        // Omit hidden courses and categories.
        if ($onlyvisible == true) {
            $sql .= "          AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        $sql .= "       ) AS ETHERPADs
                  FROM {course_categories} mcc
                 WHERE mcc.id = " . $category .
               " ORDER BY mcc.sortorder";
    }
    return $sql;
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
 */
function get_coursetablecontent($courseid, $onlyvisible=false, $nonews=false) {
    global $CFG, $DB;
    $sql = "SELECT mc.id, mc.fullname,
                  (
                      SELECT COUNT( * )
                        FROM {resource} r
                        JOIN {course} c
                          ON c.id = r.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
    }
    $sql .= "     ) AS FILEs,
                  (
                      SELECT COUNT( * )
                        FROM {folder} v
                        JOIN {course} c
                          ON c.id = v.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
    }
    $sql .= "     ) AS DIRECTORIEs,
                  (
                      SELECT COUNT( * )
                        FROM {page} p
                        JOIN {course} c
                          ON c.id = p.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
    }
    $sql .= "     ) AS PAGEs,
                  (
                      SELECT COUNT( * )
                        FROM {label} t
                        JOIN {course} c
                          ON c.id = t.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
    }
    $sql .= "     ) AS LABELs,
                  (
                      SELECT COUNT( * )
                        FROM {url} k
                        JOIN {course} c
                          ON c.id = k.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
    }
    $sql .= "     ) AS LINKs,
                  (
                      SELECT COUNT( * )
                        FROM {assign} a
                        JOIN {course} c
                          ON c.id = a.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
    }
    $sql .= "     ) AS ASSIGNs,
                  (
                      SELECT COUNT( * )
                        FROM {forum} f
                        JOIN {course} c
                          ON c.id = f.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
    }
    // Omit news forums.
    if ($nonews == true) {
         $sql .= "       AND f.type != 'news'";
    }
    $sql .= "     ) AS FORUMs,
                  (
                      SELECT COUNT( * )
                        FROM {feedback} e
                        JOIN {course} c
                          ON c.id = e.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
    }
    $sql .= "     ) AS FEEDBACKs,
                  (
                      SELECT COUNT( * )
                        FROM {quiz} q
                        JOIN {course} c
                          ON c.id = q.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
    }
    $sql .= "     ) AS QUIZs,
                  (
                      SELECT COUNT( * )
                        FROM {scheduler} s
                        JOIN {course} c
                          ON c.id = s.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
    }
    $sql .= "     ) AS SCHEDULERs,
                  (
                      SELECT COUNT( * )
                        FROM {survey} u
                        JOIN {course} c
                          ON c.id = u.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
    }
    $sql .= "     ) AS SURVEYs,
                  (
                      SELECT COUNT( * )
                        FROM {data} d
                        JOIN {course} c
                          ON c.id = d.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
    }
    $sql .= "     ) AS DBs,
                  (
                      SELECT COUNT( * )
                        FROM {glossary} g
                        JOIN {course} c
                          ON c.id = g.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
    }
    $sql .= "     ) AS GLOSSARIEs,
                  (
                      SELECT COUNT( * )
                        FROM {journal} j
                        JOIN {course} c
                          ON c.id = j.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
    }
    $sql .= "     ) AS JOURNALs,
                  (
                      SELECT COUNT( * )
                        FROM {wiki} w
                        JOIN {course} c
                          ON c.id = w.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
    }
    $sql .= "     ) AS WIKIs,
                  (
                      SELECT COUNT( * )
                        FROM {choice} h
                        JOIN {course} c
                          ON c.id = h.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
    }
    $sql .= "     ) AS CHOICEs,
                  (
                      SELECT COUNT( * )
                        FROM {choicegroup} o
                        JOIN {course} c
                          ON c.id = o.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
    }
    $sql .= "     ) AS CHOICESGROUP,
                  (
                      SELECT COUNT( * )
                        FROM {chat} b
                        JOIN {course} c
                          ON c.id = b.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
    }
    $sql .= "     ) AS CHATs,
                  (
                      SELECT COUNT( * )
                        FROM {workshop} e
                        JOIN {course} c
                          ON c.id = e.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
    }
    $sql .= "     ) AS WORKSHOPs,
                  (
                      SELECT COUNT( * )
                        FROM {etherpadlite} x
                        JOIN {course} c
                          ON c.id = x.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
    }
    $sql .= "     ) AS ETHERPADs
              FROM {course} mc
             WHERE mc.id = ?
          ORDER BY mc.sortorder";

    $returnobject = $DB->get_records_sql($sql, array($courseid));
    return array("<a href=\"$CFG->wwwroot/course/view.php?id=" . $returnobject[$courseid]->id . "\" target=\"_blank\">"
        . $returnobject[$courseid]->id . "</a>",
        "<a href=\"$CFG->wwwroot/course/view.php?id="
        . $returnobject[$courseid]->id . "\" target=\"_blank\">" . $returnobject[$courseid]->fullname . "</a>",
        $returnobject[$courseid]->files,
        $returnobject[$courseid]->directories,
        $returnobject[$courseid]->pages,
        $returnobject[$courseid]->labels,
        $returnobject[$courseid]->links,
        $returnobject[$courseid]->assigns,
        $returnobject[$courseid]->forums,
        $returnobject[$courseid]->feedbacks,
        $returnobject[$courseid]->quizs,
        $returnobject[$courseid]->schedulers,
        $returnobject[$courseid]->surveys,
        $returnobject[$courseid]->dbs,
        $returnobject[$courseid]->glossaries,
        $returnobject[$courseid]->journals,
        $returnobject[$courseid]->wikis,
        $returnobject[$courseid]->choices,
        $returnobject[$courseid]->choicesgroup,
        $returnobject[$courseid]->chats,
        $returnobject[$courseid]->workshops,
        $returnobject[$courseid]->etherpads,
                (
                $returnobject[$courseid]->pages +
                $returnobject[$courseid]->labels +
                $returnobject[$courseid]->links +
                $returnobject[$courseid]->assigns +
                $returnobject[$courseid]->forums +
                $returnobject[$courseid]->feedbacks +
                $returnobject[$courseid]->quizs +
                $returnobject[$courseid]->schedulers +
                $returnobject[$courseid]->surveys +
                $returnobject[$courseid]->dbs +
                $returnobject[$courseid]->glossaries +
                $returnobject[$courseid]->journals +
                $returnobject[$courseid]->wikis +
                $returnobject[$courseid]->choices +
                $returnobject[$courseid]->choicesgroup +
                $returnobject[$courseid]->chats +
                $returnobject[$courseid]->workshops +
                $returnobject[$courseid]->etherpads
                ),
                (
                $returnobject[$courseid]->files +
                $returnobject[$courseid]->directories +
                $returnobject[$courseid]->pages +
                $returnobject[$courseid]->labels +
                $returnobject[$courseid]->links +
                $returnobject[$courseid]->assigns +
                $returnobject[$courseid]->forums +
                $returnobject[$courseid]->feedbacks +
                $returnobject[$courseid]->quizs +
                $returnobject[$courseid]->schedulers +
                $returnobject[$courseid]->surveys +
                $returnobject[$courseid]->dbs +
                $returnobject[$courseid]->glossaries +
                $returnobject[$courseid]->journals +
                $returnobject[$courseid]->wikis +
                $returnobject[$courseid]->choices +
                $returnobject[$courseid]->choicesgroup +
                $returnobject[$courseid]->chats +
                $returnobject[$courseid]->workshops +
                $returnobject[$courseid]->etherpads
                )
        );
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
        $fullname .= ' / ' . $DB->get_field('course_categories', 'name', array('id' => $component));
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