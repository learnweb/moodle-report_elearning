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

require_once(__DIR__ . '/../../../config.php');
global $CFG;
define('CLI_SCRIPT', true);
require_once($CFG->dirroot . '/report/elearning/locallib.php'); // Include the code to test

const BLOCKPRE = "elearning_report_test";
class report_elearning_locallib_testcase extends advanced_testcase
{


    public function setUp() {
        global $DB;

        // Setup category.
        $category = new stdClass(); $category->name = "testcategory";
        $this->categoryid = $DB->insert_record_raw('course_categories', $category);

        // Setup courses.
        $course1 = new stdClass(); $course1->category = $this->categoryid;
        $course2 = new stdClass(); $course2->category = $this->categoryid;
        $this->course1id = $DB->insert_record_raw('course', $course1);
        $this->course2id = $DB->insert_record_raw('course', $course2);

        // Setup blocks.
        $standardobject = new stdClass();
        $standardobject->showinsubcontexts = 0;
        $standardobject->requiredbytheme = 0;
        $standardobject->pagetypepattern = "*";
        $standardobject->defaultregion = "side-pre";
        $standardobject->defaultweight = 0;
        $standardobject->timecreated = 1544457773;
        $standardobject->timemodified = 1544457773;
        $block1 = new stdClass();
        $block2 = new stdClass();
        $block3 = new stdClass();
        $block4 = new stdClass();
        $block5 = new stdClass();
        $this->coursecontext1 = context_course::instance($this->course1id)->id;
        $this->coursecontext2 = context_course::instance($this->course2id)->id;
        $block1->id = -1; $block1->blockname = BLOCKPRE . "1"; $block1->parentcontextid = $this->coursecontext1;
        $block2->id = -2; $block2->blockname = BLOCKPRE . "2"; $block2->parentcontextid = $this->coursecontext1;
        $block3->id = -3; $block3->blockname = BLOCKPRE . "1"; $block3->parentcontextid = $this->coursecontext1;
        $block4->id = -4; $block4->blockname = BLOCKPRE . "3"; $block4->parentcontextid = $this->coursecontext2;
        $block5->id = -5; $block5->blockname = BLOCKPRE . "1"; $block5->parentcontextid = $this->coursecontext2;

        $blocks = array($block1, $block2, $block3, $block4, $block5);

        foreach ($blocks as $block) {
            $data = (object)array_merge((array)$block, (array)$standardobject);
            $DB ->insert_record_raw('block_instances', $data);
        }

    }

    public function tearDown() {
        global $DB;
        $DB->delete_records_select("block_instances", "parentcontextid = '{$this->coursecontext1}'
         OR parentcontextid = '$this->coursecontext2'");
        $DB->delete_records_select("course_categories", "id='{$this->categoryid}'");
        $DB->delete_records_select("course", "id='{$this->course1id}' OR id='{$this->course2id}'");
    }

    // Function block_DB tests.

    public function same_block_count ($a1, $a2) {
        foreach ($a1 as $block) {
            $blockname = $block->blockname;
            if (strpos($blockname, BLOCKPRE) == -1) {
                continue;
            }
            if ($a2->$blockname != $block->count) {
                return false;
            }
        }
        return true;
    }

    protected function test_get_all_courses() {
        global $DB;
        $misccourses = get_all_courses(get_array_for_categories(-1, array() ));
        $misccoursescorrect = $DB->get_records_sql("SELECT id FROM {course} WHERE category = {$this->categoryid}");
        $childs = $misccourses[$this->categoryid]->childs;
        $this->assertEquals(count($misccoursescorrect), count($childs));
        foreach ($misccoursescorrect as $course) {
            $this->assertContains($course->id, $childs);
        }

    }

    protected function test_get_array_for_categories() {
        global $DB;

        $categoriestest = get_array_for_categories(0);
        $categoriescorrect = $DB->get_records_sql("SELECT id FROM {course_categories}");
        $this->assertEquals(count($categoriescorrect), count($categoriestest));
    }

}
