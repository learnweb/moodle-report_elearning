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
require_once($CFG->dirroot . '/report/elearning/locallib.php');
require_once($CFG->dirroot . '/report/elearning/form.php');// Include the code to test.

const BLOCKPRE = "elearning_report_test";
class report_elearning_locallib_testcase extends advanced_testcase
{


    public function setUp() {
        global $DB;
        $this->resetAfterTest();
        // Setup category.
        $category = new stdClass(); $category->name = "testcategory";
        $this->categoryid = $DB->insert_record_raw('course_categories', $category);
        $category->path = "/" . $this->categoryid; $category->id = $this->categoryid;
        $DB->update_record('course_categories', $category);

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
            $DB->insert_record_raw('block_instances', $data);
        }

        // Setup plugins.
        $assign = new stdClass(); $assign->intro = "testquizz";
        $feedback = new stdClass(); $feedback->intro = "testfeedback"; $feedback->page_after_submit = "blank";
        $forum = new stdClass(); $forum->intro = "testforum";
        $folder = new stdClass(); $folder->intro = "testfolder";

        // Course 1.
        $assign->course = $this->course1id;
        $feedback->course = $this->course1id;
        $folder->course = $this->course1id;
        $forum->course = $this->course1id;
        $DB->insert_record_raw('assign', $assign);
        $DB->insert_record_raw('assign', $assign);
        $DB->insert_record_raw('folder', $folder);
        $DB->insert_record_raw('forum', $forum);

        // Course 2.
        $assign->course = $this->course2id;
        $feedback->course = $this->course2id;
        $folder->course = $this->course2id;
        $forum->course = $this->course2id;
        $DB->insert_record_raw('forum', $forum);
        $DB->insert_record_raw('feedback', $feedback);
        $DB->insert_record_raw('feedback', $feedback);

    }

    public function tearDown() {
        global $DB;
        $DB->delete_records_select("block_instances", "parentcontextid = '{$this->coursecontext1}'
         OR parentcontextid = '$this->coursecontext2'");
        $DB->delete_records_select("course_categories", "id='{$this->categoryid}'");
        $DB->delete_records_select("course", "id='{$this->course1id}' OR id='{$this->course2id}'");
        $DB->delete_records_select("forum", "course='{$this->course1id}' OR course='{$this->course2id}'");
        $DB->delete_records_select("assign", "course='{$this->course1id}' OR course='{$this->course2id}'");
        $DB->delete_records_select("feedback", "course='{$this->course1id}' OR course='{$this->course2id}'");
        $DB->delete_records_select("folder", "course='{$this->course1id}' OR course='{$this->course2id}'");

    }

    public function test_get_block_data() {
        $blockdata = get_block_data();
        $this->assertEquals(3, $blockdata[BLOCKPRE . "1"][$this->categoryid]);
        $this->assertEquals(1, $blockdata[BLOCKPRE . "2"][$this->categoryid]);
        $this->assertEquals(1, $blockdata[BLOCKPRE . "3"][$this->categoryid]);
        $this->assertEquals(3, count($blockdata));
    }

    public function test_get_plugin_data() {
        $plugindata = get_plugin_data();
        $this->assertEquals(2, $plugindata["assign"][$this->categoryid]);
        $this->assertEquals(2, $plugindata["feedback"][$this->categoryid]);
        $this->assertEquals(2, $plugindata["forum"][$this->categoryid]);
        $this->assertEquals(1, $plugindata["folder"][$this->categoryid]);
    }

    public function test_get_data() {
        $data = array_merge(get_plugin_data(), get_block_data());
        $this->assertEquals(3, $data[BLOCKPRE . "1"][$this->categoryid]);
        $this->assertEquals(1, $data[BLOCKPRE . "2"][$this->categoryid]);
        $this->assertEquals(1, $data[BLOCKPRE . "3"][$this->categoryid]);
        $this->assertEquals(2, $data["assign"][$this->categoryid]);
        $this->assertEquals(2, $data["feedback"][$this->categoryid]);
        $this->assertEquals(2, $data["forum"][$this->categoryid]);
        $this->assertEquals(1, $data["folder"][$this->categoryid]);
    }

    public function test_get_array_for_categories() {
        global $DB;
        $categoriestest = get_array_for_categories(0);
        $categoriescorrect = $DB->get_records_sql("SELECT id FROM {course_categories}");
        $this->assertEquals(count($categoriescorrect), count($categoriestest));
        $this->assertEquals("testcategory", $categoriestest[$this->categoryid]->name);
        $this->assertEquals(1, $categoriestest[1]->id);
        $this->assertEquals("Miscellaneous", $categoriestest[1]->name);
        $this->assertEquals("/1", $categoriestest[1]->path);
        $this->assertEquals("/Miscellaneous", $categoriestest[1]->readablepath);
    }

    public function test_context_id_to_course_id_table() {
        $map = context_id_to_course_id_table();
        $this->assertEquals($this->course1id, $map[$this->coursecontext1]);
        $this->assertEquals($this->course2id, $map[$this->coursecontext2]);
    }

    public function test_get_child_map() {
        $map = get_child_map();
        $this->assertEquals($this->categoryid, $map[$this->course1id]);
        $this->assertEquals($this->categoryid, $map[$this->course2id]);
    }


    // Testing form.php.

    public function test_get_coursecategorycoursecount() {
        $this->assertEquals(2, get_coursecategorycoursecount("/" . $this->categoryid));
    }

    public function test_get_stringpath() {
        $this->assertEquals("testcategory", get_stringpath("/" . $this->categoryid));
    }

    public function test_get_all_courses() {
        global $DB;
        $misccourses = get_all_courses(get_array_for_categories(-1));
        $misccoursescorrect = $DB->get_records_sql("SELECT id FROM {course} WHERE category = {$this->categoryid}");
        $childs = $misccourses[$this->categoryid]->childs;
        $this->assertEquals(count($misccoursescorrect), count($childs));
        foreach ($misccoursescorrect as $course) {
            $this->assertContains($course->id, $childs);
        }
    }

    // This function is highly dependent on another function. We ONLY test for this functions cause.
    public function test_get_table_headers() {
        $headers = get_table_headers();
        $this->assertEquals("id", array_shift($headers));
        $this->assertEquals("category", array_shift($headers));
        $this->assertEquals("Sum without files and folders", array_pop($headers));
        $this->assertEquals("Sum", array_pop($headers));
    }

    public function test_get_shown_table_headers() {
        $headers = get_shown_table_headers();
        $this->assertEquals("ID", array_shift($headers));
        $this->assertEquals("Category", array_shift($headers));
        $this->assertEquals("Sum without files and folders", array_pop($headers));
        $this->assertEquals("Sum", array_pop($headers));
    }
}
