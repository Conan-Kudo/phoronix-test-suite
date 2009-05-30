<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2009, Phoronix Media
	Copyright (C) 2009, Michael Larabel

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

class gui_gtk implements pts_option_interface
{
	public static function required_function_sets()
	{
		return array("gui", "run", "gtk", "install_dependencies");
	}
	public static function run($r)
	{
		if(!extension_loaded("gtk") && !extension_loaded("php-gtk"))
		{
			echo "\nThe PHP GTK module must be loaded for the GUI.\nThis module can be found @ http://gtk.php.net/\n\n";

			if($_ENV["TERM"] == null)
			{
				pts_display_web_browser(STATIC_DIR . "error-gui.html", null, true, true);
			}

			return;
		}

		gui_gtk::show_main_interface();
	}
	public static function kill_gtk_window()
	{
		Gtk::main_quit();
	}
	public static function system_tray_monitor($update_text = null, $icon_blink = null)
	{
		static $system_tray = null;

		if($system_tray == null)
		{
			$system_tray = new GtkStatusIcon();
			$system_tray->set_from_file(STATIC_DIR . "pts-icon.png");
			$system_tray->set_tooltip("Phoronix Test Suite v" . PTS_VERSION);
			$system_tray->connect("activate", array("gui_gtk", "system_tray_activate"));
			$system_tray->connect("popup-menu", array("gui_gtk", "system_tray_menu"));
		}

		if($update_text != null)
		{
			$system_tray->set_tooltip($update_text);
		}
		if($icon_blink !== null)
		{
			$system_tray->set_blinking($icon_blink);
		}
	}
	public static function system_tray_activate()
	{
		static $is_showing = true;

		if(($window = pts_read_assignment("GTK_OBJ_WINDOW")) instanceOf pts_gtk_window)
		{
			if($is_showing)
			{
				$window->hide_all();
			}
			else
			{
				$window->show_all();
			}
			$is_showing = !$is_showing;
		}
	}
	public static function system_tray_menu()
	{
		$menu = array();

		array_push($menu, new pts_gtk_menu_item("About The Phoronix Test Suite", array("gui_gtk", "show_about_interface")));

		$gtk_menu = new GtkMenu();

		foreach($menu as $pts_gtk_menu_item)
		{	
			$menu_item = new GtkMenuItem($pts_gtk_menu_item->get_title());
			$menu_item->connect("activate", $pts_gtk_menu_item->get_function_call());
			$gtk_menu->append($menu_item);
		}

		$gtk_menu->show_all();
		$gtk_menu->popup();		
	}
	public static function show_main_interface()
	{
		$window = new pts_gtk_window("Phoronix Test Suite v" . PTS_VERSION);
		$window->drag_dest_set(Gtk::DEST_DEFAULT_ALL, array(array("text/uri-list", 0, 0)), Gdk::ACTION_COPY);
		$window->set_position(Gtk::WIN_POS_CENTER_ALWAYS);
		$window->connect("drag-data-received", array("gui_gtk", "drag_drop_item"), $window);
		pts_set_assignment("GTK_OBJ_WINDOW", $window);
		$vbox = new GtkVBox();
		$vbox->set_spacing(4);
		$window->add($vbox);

		$clipboard = new GtkClipboard($window->get_display(), Gdk::atom_intern("CLIPBOARD"));
		pts_set_assignment("GTK_OBJ_CLIPBOARD", $clipboard);

		// Menu Setup
		$refresh_graphs = new pts_gtk_menu_item(array("GTK_OBJ_REFRESH_GRAPHS", "Regenerate Graphs"), array("gui_gtk", "refresh_graphs"));

		$file_menu = array("Phoronix Global" => array(new pts_gtk_menu_item("Clone / View Results", array("gui_gtk", "show_phx_global_clone_interface")), 
		new pts_gtk_menu_item("User Log-In", array("gui_gtk", "show_phx_global_login_interface"))), null);

		if(!pts_pcqs_is_installed())
		{
			array_push($file_menu, new pts_gtk_menu_item("Install PCQS", array("gui_gtk", "show_pcqs_install_interface")));
		}

		$file_menu["Export Results"] = array(new pts_gtk_menu_item("Save To PDF", array("gui_gtk", "show_generate_pdf_interface")),
		new pts_gtk_menu_item("Save To Text", array("gui_gtk", "show_generate_text_interface")),
		new pts_gtk_menu_item("Save To CSV", array("gui_gtk", "show_generate_csv_interface"))
		);

		array_push($file_menu, new pts_gtk_menu_item(array("GTK_OBJ_GENERATE_ARCHIVE", "Archive Results"), array("gui_gtk", "show_generate_archive_interface")));
		array_push($file_menu, new pts_gtk_menu_item(array("GTK_OBJ_GLOBAL_UPLOAD", "_Upload To Phoronix Global"), array("gui_gtk", "upload_results_to_global")));
		array_push($file_menu, null);
		array_push($file_menu, new pts_gtk_menu_item("Quit", array("gui_gtk", "kill_gtk_window"), "STRING", Gtk::STOCK_QUIT));

		$license_type = array();
		foreach(pts_license_test_types() as $license)
		{
			array_push($license_type, new pts_gtk_menu_item($license, array("gui_gtk", "check_test_license_select"), "CHECK_BUTTON", null, true));
		}

		$subsystem_type = array();
		foreach(pts_subsystem_test_types() as $subsystem)
		{
			array_push($subsystem_type, new pts_gtk_menu_item($subsystem, array("gui_gtk", "check_test_type_select"), "CHECK_BUTTON", null, true));
		}

		$view_menu = array(
			new pts_gtk_menu_item("System _Information", array("gui_gtk", "show_system_info_interface")),
			new pts_gtk_menu_item("Software _Dependencies", array("gui_gtk", "show_dependency_info_interface")),
			null,
			new pts_gtk_menu_item(array("Tests", "Suites"), array("gui_gtk", "radio_test_suite_select"), "RADIO_BUTTON"),
			null,
			"Show License" => $license_type,
			"Show Subsystem" => $subsystem_type,
			"Show Dependencies" => array(new pts_gtk_menu_item(array("Show All", "All Dependencies Installed", "Dependencies Missing"), array("gui_gtk", "radio_test_dependencies_select"), "RADIO_BUTTON"))
			);

		$main_menu_items = array(
		"_File" => $file_menu,
		"_Edit" => array(
			new pts_gtk_menu_item(array("GTK_OBJ_REFRESH_GRAPHS", "Regenerate Graphs"), array("gui_gtk", "quick_operation", "refresh_graphs")),
			null,
			new pts_gtk_menu_item("_Preferences", array("gui_gtk", "show_preferences_interface"), null, Gtk::STOCK_PREFERENCES)),
		"_View" => $view_menu,
		"_Tools" => array(
		// TODO: Implement build suite option
		//	new pts_gtk_menu_item(array("GTK_OBJ_BUILD_SUITE", "Build Suite"), array("gui_gtk", ""), "STRING", Gtk::STOCK_NEW),
			new pts_gtk_menu_item(array("GTK_OBJ_MERGE_RESULTS", "Merge Results"), array("gui_gtk", "")),
			null,
			new pts_gtk_menu_item(array("GTK_OBJ_ANALYZE_RUNS", "Analyze All Runs"), array("gui_gtk", "quick_operation", "analyze_all_runs")),
			new pts_gtk_menu_item(array("GTK_OBJ_ANALYZE_BATCH", "Analyze Batch Run"), array("gui_gtk", "quick_operation", "analyze_batch"))),
		"_Help" => array(
		new pts_gtk_menu_item("View Documentation", array("gui_gtk", "launch_web_browser"), "STRING"), 
		null,
		new pts_gtk_menu_item("Community Support Online", array("gui_gtk", "launch_web_browser"), "STRING", Gtk::STOCK_HELP), 
		new pts_gtk_menu_item("Phoronix-Test-Suite.com", array("gui_gtk", "launch_web_browser"), "STRING"), 
		new pts_gtk_menu_item("Phoronix Media", array("gui_gtk", "launch_web_browser"), "STRING"), 
		null,
		new pts_gtk_menu_item("_About", array("gui_gtk", "show_about_interface"), "STRING", Gtk::STOCK_ABOUT))
		);
		pts_gtk_add_menu($vbox, $main_menu_items);

		pts_gtk_object_set_sensitive("GTK_OBJ_ANALYZE_RUNS", false);
		pts_gtk_object_set_sensitive("GTK_OBJ_ANALYZE_BATCH", false);
		pts_gtk_object_set_sensitive("GTK_OBJ_MERGE_RESULTS", false);
		pts_gtk_object_set_sensitive("GTK_OBJ_GLOBAL_UPLOAD", false);
		pts_gtk_object_set_sensitive("GTK_OBJ_MENU_EXPORT_RESULTS", false);

		//
		// Top Header
		//

/*		$header_hbox = new GtkHBox();
		$header_hbox->modify_bg(Gtk::STATE_NORMAL, $window->get_style()->bg[Gtk::STATE_SELECTED]);
		//$header_hbox->set_size_request(-1, 50);

		$vbox->pack_start($header_hbox);
*/
		//
		// Main Area
		//

		// Details Frame
		$main_frame = new GtkFrame((($t = pts_read_assignment("PREV_SAVE_NAME_TITLE")) !== false ? $t : "Welcome"));
		pts_set_assignment("GTK_OBJ_MAIN_FRAME", $main_frame);

		$i = pts_read_assignment("PREV_SAVE_RESULTS_IDENTIFIER");
		$u = pts_read_assignment("PREV_GLOBAL_UPLOAD_URL");
		$p = pts_read_assignment("PREV_PDF_FILE");
		$ti = pts_read_assignment("PREV_TEST_INSTALLED");

		$main_frame_objects = array();
		if($i != false || $u != false || $p != false || $ti != false)
		{

			// TODO: this area here could be cleaned up...

			array_push($main_frame_objects, null);
			if(!empty($i))
			{
				$tr_button = new pts_gtk_button("View Test Results", array("gui_gtk", "launch_web_browser"), SAVE_RESULTS_DIR . $i . "/composite.xml");
				array_push($main_frame_objects, $tr_button);
			}
			if(!empty($ti))
			{
				$ti_button = new GtkButton("Run " . pts_test_identifier_to_name($ti));
				$ti_button->connect_simple("clicked", array("gui_gtk", "show_run_confirmation_interface"), $ti);
				array_push($main_frame_objects, $ti_button);
			}
			if(!empty($u))
			{
				$pg_button = new pts_gtk_button("View On Phoronix Global", array("gui_gtk", "launch_web_browser"), $u);
				array_push($main_frame_objects, $pg_button);
			}
			if(!empty($p))
			{
				$pdf_label = new GtkLabel("PDF Saved To: " . $p);
				$pdf_label->set_line_wrap(true);
				$pdf_label->set_size_request(260, -1);
				array_push($main_frame_objects, $pdf_label);
			}
			if(!empty($i) && pts_read_assignment("PREV_COMMAND") != "reference_comparison")
			{
				$reference_tests = pts_result_file_reference_tests($i);
				if(count($reference_tests) > 0)
				{
					$objs = gui_gtk::pts_gtk_reference_system_comparison_objects($reference_tests, $i);

					foreach($objs as $obj)
					{
						array_push($main_frame_objects, $obj);
					}
				}
				else
				{
					array_push($main_frame_objects, null);
				}
			}
			else
			{
				array_push($main_frame_objects, null);
			}
		}
		else
		{
			$event_box = new GtkEventBox();
			//$event_box->connect_simple("button-press-event", array("gui_gtk", "launch_web_browser"), "");
			$logo = GtkImage::new_from_file(RESULTS_VIEWER_DIR . "pts-logo.png");
			$logo->set_size_request(158, 82);
			$event_box->add($logo);
			array_push($main_frame_objects, $event_box);

			$welcome = file_get_contents(STATIC_DIR . "short-description.txt");
			array_push($main_frame_objects, $t = new pts_gtk_text_area($welcome, -1, -1, true));
		}

		$main_frame_box = pts_gtk_array_to_boxes($main_frame, $main_frame_objects, 6);
		pts_set_assignment("GTK_OBJ_MAIN_FRAME_BOX", $main_frame_box);

		// Notebook Area
		$main_notebook = new GtkNotebook();
		$main_notebook->connect("switch-page", array("gui_gtk", "notebook_main_page_select"));
		$main_notebook->set_size_request(-1, 300);
		pts_set_assignment("GTK_OBJ_MAIN_NOTEBOOK", $main_notebook);
		gui_gtk::update_main_notebook();

		// Bottom Line
		$check_mode_batch = new GtkCheckButton("Batch Mode");
		$check_mode_batch->set_sensitive(false);
		pts_set_assignment("GTK_OBJ_CHECK_BATCH", $check_mode_batch);

		$check_mode_defaults = new GtkCheckButton("Defaults Mode");
		$check_mode_defaults->set_sensitive(false);
		pts_set_assignment("GTK_OBJ_CHECK_DEFAULTS", $check_mode_defaults);

		$check_mode_defaults->connect("toggled", array("gui_gtk", "check_test_mode_select"), $check_mode_batch);
		$check_mode_batch->connect("toggled", array("gui_gtk", "check_test_mode_select"), $check_mode_defaults);

		$details_button = new pts_gtk_button("More Information", array("gui_gtk", "details_button_clicked"), null, 150, -1, Gtk::STOCK_FIND);
		$details_button->set_sensitive(false);
		pts_set_assignment("GTK_OBJ_DETAILS_BUTTON", $details_button);

		$run_button = new pts_gtk_button("Run", array("gui_gtk", "show_confirmation_interface"), null, 100, -1, Gtk::STOCK_EXECUTE);
		$run_button->set_sensitive(false);
		pts_set_assignment("GTK_OBJ_RUN_BUTTON", $run_button);

		pts_gtk_array_to_boxes($vbox, array(array($main_frame, $main_notebook), array($check_mode_batch, $check_mode_defaults, $details_button, $run_button)), 6, true);

		// Setup System Tray
		if(class_exists("GtkStatusIcon"))
		{
			// TOOD: re-enable and get working properly
			gui_gtk::system_tray_monitor();
			// pts_attach_module("gui_gtk_events");
		}

		$window->show_all();
		Gtk::main();
	}
	public static function drag_drop_item($widget, $context, $x, $y, $data, $info, $time, $img)
	{
		$file = str_replace("file://", "", trim($data->data));

		if(substr($file, -4) == ".svg")
		{
			$svg_options = pts_parse_svg_options($file);

			if(count($svg_options) > 0)
			{
				$window = pts_read_assignment("GTK_OBJ_WINDOW");

				foreach($window->get_children() as $child)
				{
					$window->remove($child);
				}

				$vertical = array(null);

				for($i = 0; $i < count($svg_options); $i++)
				{
					array_push($vertical, new pts_gtk_button($svg_options[$i][1], array("gui_gtk", "drag_drop_item_clicked"), $svg_options[$i][0]));
				}
				array_push($vertical, new pts_gtk_button("Return", array("gui_gtk", "drag_drop_item_clicked"), "return", -1, -1, Gtk::STOCK_QUIT));
				array_push($vertical, null);

				pts_gtk_array_to_boxes($window, $vertical, 16);
				gui_gtk::redraw_main_window();
			}
		}
	}
	public static function drag_drop_item_clicked($clicked)
	{
		$window = pts_read_assignment("GTK_OBJ_WINDOW");
		$window->destroy();

		switch($clicked)
		{
			case "return":
				// TODO: refresh main window instead of having to redo it all
				pts_run_option_next("gui_gtk");
				break;
			default:
				pts_run_option_next("install_test", $clicked, array("SILENCE_MESSAGES" => true));
				gui_gtk::show_run_confirmation_interface($clicked);
				break;
		}
	}
	public static function update_details_frame_from_select($object)
	{
		$previous_select = (is_array($p = pts_read_assignment("GTK_SELECTED_ITEM_PREV")) ? $p : array());
		$identifiers = pts_gtk_selected_items($object);
		pts_set_assignment("GTK_MULTIPLE_SELECT_ITEMS", ($multiple_selected = count($identifiers) > 1));
		pts_set_assignment("GTK_LAST_SELECTED_ITEM", ($identifier = array_pop(array_diff($identifiers, $previous_select))));
		pts_set_assignment("GTK_SELECTED_ITEMS", $identifiers);
		pts_set_assignment_once("GTK_HAS_TOUCHED_SELECT_MENU", true);

		$gtk_obj_main_frame = pts_read_assignment("GTK_OBJ_MAIN_FRAME");
		$gtk_obj_main_frame->set_label($identifier);

		if(pts_is_assignment("GTK_OBJ_MAIN_FRAME_BOX"))
		{
			$gtk_obj_main_frame_box = pts_read_assignment("GTK_OBJ_MAIN_FRAME_BOX");
			$gtk_obj_main_frame->remove($gtk_obj_main_frame_box);
		}

		$info_r = array();
		$append_elements = array();

		pts_gtk_object_set_sensitive("GTK_OBJ_RUN_BUTTON", true);
		pts_gtk_object_set_sensitive("GTK_OBJ_DETAILS_BUTTON", !$multiple_selected);
		pts_gtk_object_set_sensitive("GTK_OBJ_CHECK_DEFAULTS", pts_read_assignment("GTK_MAIN_NOTEBOOK_SELECTED") != "Test Results");
		pts_gtk_object_set_sensitive("GTK_OBJ_CHECK_BATCH", pts_read_assignment("GTK_MAIN_NOTEBOOK_SELECTED") != "Test Results");

		// PTS Test
		$test_menu_items_sensitive = false;

		if($multiple_selected)
		{
			$test_menu_items_sensitive = false;
			$info_r["Tests Selected"] = count($identifiers) . " Selected";
			$info_r["null1"] = null;
			array_push($append_elements, new pts_gtk_text_area("Note: This mode of selecting multiple tests simultaneously is currently experimental.", -1, -1, true));
		}
		else if(pts_read_assignment("GTK_MAIN_NOTEBOOK_SELECTED") == "Test Results")
		{
			$test_menu_items_sensitive = true;

			$result_file = new pts_test_result_details($identifier);

			$info_r["Title"] = $result_file->get_title();
			$info_r["null1"] = null;
			$info_r["Test"] = $result_file->get_suite();
			$info_r["null2"] = null;

			$reference_tests = pts_result_file_reference_tests($identifier);
			if(count($reference_tests) > 0)
			{
				$objs = gui_gtk::pts_gtk_reference_system_comparison_objects($reference_tests, $identifier);

				foreach($objs as $obj)
				{
					array_push($append_elements, $obj);
				}
			}
			else
			{
				if(count($result_file->get_identifiers()) > 1)
				{
					array_push($append_elements, new pts_gtk_text_area($result_file->identifiers_string(), -1, -1, true));
				}
				if(count($result_file->get_unique_tests()) > 1)
				{
					//array_push($append_elements, null);
					array_push($append_elements, new pts_gtk_text_area($result_file->unique_tests_string(), -1, -1, true));
				}
			}
		}
		else if(pts_read_assignment("GTK_TEST_OR_SUITE") == "TEST")
		{
			$identifier = pts_test_name_to_identifier($identifier);
			$test_profile = new pts_test_profile_details($identifier);

			$info_r["Maintainer"] = $test_profile->get_maintainer();
			$info_r["Test Type"] = $test_profile->get_test_hardware_type();
			$info_r["Software Type"] = $test_profile->get_test_software_type();
			$info_r["License"] = $test_profile->get_license();

			if($test_profile->get_download_size() > 0)
			{
				$info_r["Download Size"] = $test_profile->get_download_size() . " MB";
			}
			if($test_profile->get_environment_size() > 0)
			{
				$info_r["Installed Size"] = $test_profile->get_environment_size() . " MB";
			}

			$textview_description = new pts_gtk_text_area($test_profile->get_description(), -1, -1, true);
			array_push($append_elements, $textview_description);
		}
		else if(pts_read_assignment("GTK_TEST_OR_SUITE") == "SUITE")
		{
			$identifier = pts_suite_name_to_identifier($identifier);
			$test_suite = new pts_test_suite_details($identifier);

			$info_r["Maintainer"] = $test_suite->get_maintainer();
			$info_r["Suite Type"] = $test_suite->get_suite_type();

			$textview_description = new pts_gtk_text_area($test_suite->get_description(), -1, -1, true);
			array_push($append_elements, $textview_description);
		}

		pts_gtk_object_set_sensitive("GTK_OBJ_GENERATE_ARCHIVE", $test_menu_items_sensitive);
		pts_gtk_object_set_sensitive("GTK_OBJ_MENU_EXPORT_RESULTS", $test_menu_items_sensitive);
		pts_gtk_object_set_sensitive("GTK_OBJ_REFRESH_GRAPHS", $test_menu_items_sensitive);
		pts_gtk_object_set_sensitive("GTK_OBJ_ANALYZE_RUNS", $test_menu_items_sensitive);
		pts_gtk_object_set_sensitive("GTK_OBJ_ANALYZE_BATCH", $test_menu_items_sensitive);
		pts_gtk_object_set_sensitive("GTK_OBJ_GLOBAL_UPLOAD", $test_menu_items_sensitive);

		$titles = array();
		$values = array();
		foreach($info_r as $head => $show)
		{
			$label_head = new GtkLabel(($show == null ? null : $head . ": "));
			$label_head->set_alignment(0, 0);
			$label_head->set_padding(0, 0);
			array_push($titles, $label_head);

			$label_show = new GtkLabel($show);
			$label_show->set_alignment(0, 0);
			array_push($values, $label_show);
		}

		$elements = array(array($titles, $values));

		foreach($append_elements as $e)
		{
			array_push($elements, $e);
		}

		$box = pts_gtk_array_to_boxes($gtk_obj_main_frame, $elements, 4);
		pts_set_assignment("GTK_OBJ_MAIN_FRAME_BOX", $box);

		gui_gtk::update_run_button();
		gui_gtk::redraw_main_window();
	}
	public static function update_details_frame_for_install($to_install)
	{
		$gtk_obj_main_frame = pts_read_assignment("GTK_OBJ_MAIN_FRAME");
		$gtk_obj_main_frame->set_label(array_pop(pts_to_array($to_install)));

		if(pts_is_assignment("GTK_OBJ_MAIN_FRAME_BOX"))
		{
			$gtk_obj_main_frame_box = pts_read_assignment("GTK_OBJ_MAIN_FRAME_BOX");
			$gtk_obj_main_frame->remove($gtk_obj_main_frame_box);
		}

		$info_r = array();
		$append_elements = array();

		// PTS Test
		$info_r["Estimated Download Size"] = pts_estimated_download_size($to_install) . " MB";
		$info_r["null1"] = null;
		$info_r["Estimated Install Size"] = pts_estimated_environment_size($to_install) . " MB";
		$info_r["null2"] = null;

		$titles = array();
		$values = array();
		foreach($info_r as $head => $show)
		{
			$label_head = new GtkLabel(($show == null ? null : $head . ": "));
			$label_head->set_alignment(0, 0);
			$label_head->set_padding(0, 0);
			array_push($titles, $label_head);

			$label_show = new GtkLabel($show);
			$label_show->set_alignment(0, 0);
			array_push($values, $label_show);
		}

		$install_button = new pts_gtk_button("Install Now", null, null, 200, 100);
		$install_button->connect_simple("clicked", array("gui_gtk", "confirmation_button_clicked"), "install", $to_install);

		$elements = array(array($titles, $values), null, $install_button, null);
		$box = pts_gtk_array_to_boxes($gtk_obj_main_frame, $elements, 4);
		pts_set_assignment("GTK_OBJ_MAIN_FRAME_BOX", $box);

		pts_gtk_object_set_sensitive("GTK_OBJ_RUN_BUTTON", false);

		gui_gtk::redraw_main_window();
	}
	public static function pts_gtk_reference_system_comparison_objects($reference_tests, $comparison_identifier)
	{
		// TODO: cleanup this function
		$append_elements = array();
		pts_set_assignment("REFERENCE_COMPARISONS_IDENTIFIER", $comparison_identifier);
		array_push($append_elements, new GtkLabel("Reference Systems"));
		$compare_results = new pts_gtk_button("Compare Results", array("gui_gtk", "compare_reference_systems"), null);
		$compare_results->set_sensitive(false);

		foreach($reference_tests as $global_id)
		{
			$xml_parser = new pts_results_tandem_XmlReader($global_id);
			$ref_identifiers = $xml_parser->getXMLArrayValues(P_RESULTS_SYSTEM_IDENTIFIERS);

			for($i = 0; $i < count($ref_identifiers); $i++)
			{
				$ref_check_button = new GtkCheckButton($ref_identifiers[$i]);
				$ref_check_merge = new pts_result_merge_select($global_id, $ref_identifiers[$i]);

				$ref_check_button->set_active(false);
				$ref_check_button->connect("toggled", array("gui_gtk", "toggle_reference_systems"), $ref_check_merge, $compare_results);

				array_push($append_elements, $ref_check_button);
			}
		}
		array_push($append_elements, $compare_results);

		return $append_elements;
	}
	public static function compare_reference_systems()
	{
		$reference_comparisons = pts_read_assignment("REFERENCE_COMPARISONS");
		$identifier = pts_read_assignment("REFERENCE_COMPARISONS_IDENTIFIER");

		if(empty($reference_comparisons))
		{
			return false;
		}

		$pass_args = array("AUTOMATED_MODE" => true, "REFERENCE_COMPARISONS" => $reference_comparisons);
		pts_run_option_next("reference_comparison", $identifier, $pass_args);
		pts_run_option_next("gui_gtk");

		$window = pts_read_assignment("GTK_OBJ_WINDOW");

		if($window instanceOf GtkWindow)
		{
			$window->destroy();
		}
	}
	public static function toggle_reference_systems($checkbutton, $merge_object, $compare_button)
	{
		$reference_comparisons = pts_read_assignment("REFERENCE_COMPARISONS");

		if(empty($reference_comparisons))
		{
			$reference_comparisons = array();
		}

		if($checkbutton->get_active())
		{
			if(!in_array($merge_object, $reference_comparisons))
			{
				array_push($reference_comparisons, $merge_object);
			}
		}
		else if(count($reference_comparisons) > 0)
		{
			if(($key = array_search($merge_object, $reference_comparisons)) !== false)
			{
				unset($reference_comparisons[$key]);
			}
		}

		$compare_button->set_sensitive((count($reference_comparisons) > 0));
		pts_set_assignment("REFERENCE_COMPARISONS", $reference_comparisons);
	}
	public static function update_main_notebook()
	{
		$main_notebook = pts_read_assignment("GTK_OBJ_MAIN_NOTEBOOK");

		if($main_notebook == null)
		{
			return;
		}

		foreach($main_notebook->get_children() as $child)
		{
			$main_notebook->remove($child);
		}

		if(pts_read_assignment("GTK_TEST_OR_SUITE") == "SUITE")
		{
			// Installed Suites
			$i_s = pts_gui_installed_suites();
			$installed_suites = pts_gtk_table(array(count($i_s) . " Suites"), $i_s, array("gui_gtk", "update_details_frame_from_select"), "No suites are currently installed.");
			pts_gtk_add_notebook_tab($main_notebook, $installed_suites, "Installed Suites");

			$a_s = pts_gui_available_suites(pts_read_assignment("GTK_TEST_TYPES_TO_SHOW"), pts_read_assignment("GTK_TEST_LICENSES_TO_SHOW"));
			$available_suites = pts_gtk_table(array(count($a_s) . " Suites"), $a_s, array("gui_gtk", "update_details_frame_from_select"), "No suites are available.");
			pts_gtk_add_notebook_tab($main_notebook, $available_suites, "Available Suites");
		}
		else
		{
			// Installed Tests
			$i_t = pts_gui_installed_tests(pts_read_assignment("GTK_TEST_TYPES_TO_SHOW"), pts_read_assignment("GTK_TEST_LICENSES_TO_SHOW"));
			$installed_tests = pts_gtk_table(array(count($i_t) . " Tests"), $i_t, array("gui_gtk", "update_details_frame_from_select"), "No tests are currently installed.");
			pts_gtk_add_notebook_tab($main_notebook, $installed_tests, "Installed Tests");

			// Available Tests
			$a_t = pts_gui_available_tests(pts_read_assignment("GTK_TEST_TYPES_TO_SHOW"), pts_read_assignment("GTK_TEST_LICENSES_TO_SHOW"), pts_read_assignment("GTK_DEPENDENCY_LIMIT"));
			$available_tests = pts_gtk_table(array(count($a_t) . " Tests"), $a_t, array("gui_gtk", "update_details_frame_from_select"), "No tests are available.");
			pts_gtk_add_notebook_tab($main_notebook, $available_tests, "Available Tests");
		}

		$r_i = pts_gui_saved_test_results_identifiers();
		$test_results = pts_gtk_table(array(count($r_i) . " Test Results"), $r_i, array("gui_gtk", "update_details_frame_from_select"), "No test results have been saved.");
		pts_gtk_add_notebook_tab($main_notebook, $test_results, "Test Results");
	}
	public static function radio_test_suite_select($object)
	{
		if($object->get_active())
		{
			$item = $object->child->get_label();
			pts_set_assignment("GTK_TEST_OR_SUITE", ($item == "Tests" ? "TEST" : "SUITE"));

			gui_gtk::update_main_notebook();
			gui_gtk::redraw_main_window();
		}
	}
	public static function radio_test_dependencies_select($object)
	{
		if($object->get_active())
		{
			$item = $object->child->get_label();

			switch($item)
			{
				case "All Dependencies Installed":
					$dependency_limit = "DEPENDENCIES_INSTALLED";
					break;
				case "Dependencies Missing":
					$dependency_limit = "DEPENDENCIES_MISSING";
					break;
				default:
					$dependency_limit = null;
					break;
			}

			pts_set_assignment("GTK_DEPENDENCY_LIMIT", $dependency_limit);

			gui_gtk::update_main_notebook();
			gui_gtk::redraw_main_window();
		}
	}
	public static function confirmation_button_clicked($button_call, $identifiers = "")
	{
		switch($button_call)
		{
			case "return":
				gui_gtk::show_main_interface();
				break;
			case "install":
				pts_run_option_next("install_test", $identifiers, array("SILENCE_MESSAGES" => true));
				pts_run_option_next("gui_gtk");
				break;
			case "benchmark":
				$args_to_pass = array("IS_BATCH_MODE" => pts_read_assignment("GTK_BATCH_MODE"), 
				"IS_DEFAULTS_MODE" => pts_read_assignment("GTK_DEFAULTS_MODE"), "AUTOMATED_MODE" => true);

				$save_results = pts_read_assignment("GTK_OBJ_SAVE_RESULTS");
				$save_results = $save_results->get_active();

				if($save_results)
				{
					$save_name = pts_read_assignment("GTK_OBJ_SAVE_NAME");
					$save_name = $save_name->get_text();

					$results_identifier = pts_read_assignment("GTK_OBJ_TEST_IDENTIFIER");
					$results_identifier = $results_identifier->get_text();

					$upload_to_global = pts_read_assignment("GTK_OBJ_GLOBAL_UPLOAD");
					$upload_to_global = $upload_to_global->get_active();

					$args_to_pass["AUTO_SAVE_NAME"] = $save_name;
					$args_to_pass["AUTO_TEST_RESULTS_IDENTIFIER"] = $results_identifier;
					$args_to_pass["AUTO_UPLOAD_TO_GLOBAL"] = $upload_to_global;
				}
				else
				{
					$args_to_pass["DO_NOT_SAVE_RESULTS"] = true;
				}

				if(pts_is_assignment("GTK_TEST_RUN_OPTIONS_SET"))
				{
					$preset_test_options = array();
					$set_options = pts_read_assignment("GTK_TEST_RUN_OPTIONS_SET");

					foreach($set_options as $test_name => $test_settings)
					{
						foreach($test_settings as $name => $value)
						{
							if($value instanceOf GtkEntry)
							{
								$preset_test_options[$test_name][$name] = $value->get_text();
							}
							else if($value instanceOf GtkComboBox)
							{
								$preset_test_options[$test_name][$name] = $value->get_active();
							}
						}
					}
					$args_to_pass["AUTO_TEST_OPTION_SELECTIONS"] = $preset_test_options;
				}

				pts_run_option_next("run_test", $identifiers, $args_to_pass);
				pts_run_option_next("gui_gtk");
				break;
		}

		$window = pts_read_assignment("GTK_OBJ_WINDOW");
		if($window instanceOf GtkWindow)
		{
			$window->destroy();
		}

		$window = pts_read_assignment("GTK_OBJ_CONFIRMATION_WINDOW");
		if($window instanceOf GtkWindow)
		{
			$window->destroy();
		}
	}
	public static function pcqs_button_clicked($button_call)
	{
		if($button_call == "install")
		{
			pts_pcqs_install_package();
		}

		$window = pts_read_assignment("GTK_OBJ_PCQS_WINDOW");
		$window->destroy();
	}
	public static function check_test_mode_select($checkbox, $other_checkbox)
	{
		$toggled_mode = $checkbox->get_label();
		$other_mode = $other_checkbox->get_label();

		if($other_checkbox->get_active())
		{
			$other_checkbox->set_active(false);
		}

		if($toggled_mode == "Batch Mode")
		{
			$batch_checkbox = $checkbox;
			$defaults_checkbox = $other_checkbox;
		}
		else
		{
			$batch_checkbox = $other_checkbox;
			$defaults_checkbox = $checkbox;
		}

		pts_set_assignment("GTK_BATCH_MODE", $batch_checkbox->get_active());
		pts_set_assignment("GTK_DEFAULTS_MODE", $defaults_checkbox->get_active());
	}
	public static function show_confirmation_interface($task = null, $identifier = null)
	{
		if($identifier == null)
		{
			$identifier = gui_gtk::notebook_selected_to_identifier();
		}

		switch(($task == null ? pts_read_assignment("GTK_RUN_BUTTON_TASK") : $task))
		{
			case "INSTALL":
			case "UPDATE":
				gui_gtk::update_details_frame_for_install($identifier);
				break;
			case "RUN":
				gui_gtk::show_run_confirmation_interface($identifier);
				break;
		}
	}
	public static function show_run_confirmation_interface($identifiers)
	{
		if(empty($identifiers))
		{
			echo "DEBUG: Null identifier in gtk_gui::show_run_confirmation_interface()\n";
			return;
		}

		$main_window = pts_read_assignment("GTK_OBJ_WINDOW");
		$main_window->destroy();

		$menu_items = array();

		if(pts_read_assignment("GTK_BATCH_MODE") != false)
		{
			array_push($menu_items, new pts_gtk_label("Test Options", "Sans 19"));
			array_push($menu_items, new GtkLabel("No user options, running in batch mode."));
		}
		else if(pts_read_assignment("GTK_DEFAULTS_MODE") != false)
		{
			array_push($menu_items, new pts_gtk_label("Test Options", "Sans 19"));
			array_push($menu_items, new GtkLabel("No user options, running in defaults mode."));
		}
		else
		{
			$selected_options = array();

			foreach($identifiers as $identifier)
			{
				$test_options = pts_test_options($identifier);
				array_push($menu_items, new pts_gtk_label($identifier . " Test Options", "Sans 19"));

				if(count($test_options) == 0)
				{
					array_push($menu_items, new GtkLabel("No user options available."));
				}

				for($i = 0; $i < count($test_options); $i++)
				{
					$o = $test_options[$i];
					$option_count = $o->option_count();

					if($option_count == 0)
					{
						// User inputs their option
						// TODO: could add check to make sure the text field isn't empty when pressed
						$selected_options[$identifier][$o->get_identifier()] = new GtkEntry();
						array_push($menu_items, array(new GtkLabel($o->get_name() . ":"), $selected_options[$identifier][$o->get_identifier()]));
					}
					else
					{
						if($option_count == 1)
						{
							// Only one option in menu, so auto-select it
							$bench_choice = 0;
						}
						else
						{
							$combobox = GtkComboBox::new_text();
							foreach($o->get_all_option_names() as $option_name)
							{
								$combobox->append_text($option_name);
							}
							$combobox->set_active(0);

							$selected_options[$identifier][$o->get_identifier()] = $combobox;
							array_push($menu_items, array(new GtkLabel($o->get_name() . ":"), $combobox));
						}
					}
				}
			}

			pts_set_assignment("GTK_TEST_RUN_OPTIONS_SET", $selected_options);
		}

		array_push($menu_items, null);
		array_push($menu_items, new pts_gtk_label("Results", "Sans 19"));

		$save_results = new GtkCheckButton("Save Results");
		pts_set_assignment("GTK_OBJ_SAVE_RESULTS", $save_results);
		$save_results->set_active(true);
		$save_results->connect("toggled", array("gui_gtk", "toggle_save_results"));
		array_push($menu_items, $save_results);

		$save_name = new GtkEntry();
		pts_set_assignment("GTK_OBJ_SAVE_NAME", $save_name);
		array_push($menu_items, array(new GtkLabel("Save Name"), $save_name));

		$test_identifier = new GtkEntry();
		pts_set_assignment("GTK_OBJ_TEST_IDENTIFIER", $test_identifier);
		array_push($menu_items, array(new GtkLabel("Test Identifier"), $test_identifier));

		$global_upload = new GtkCheckButton("Upload Results To Phoronix Global");
		$global_upload->set_active(true);
		pts_set_assignment("GTK_OBJ_GLOBAL_UPLOAD", $global_upload);
		array_push($menu_items, $global_upload);

		array_push($menu_items, null);

		$window = new pts_gtk_window("phoronix-test-suite benchmark " . implode(" ", $identifiers));
		$window->set_resizable(false);
		$vbox = new GtkVBox();
		$window->add($vbox);

		pts_gtk_array_to_boxes($vbox, $menu_items, -1, true);

		//$return_button = new pts_gtk_button("Return", array("gui_gtk", "confirmation_button_clicked"), "return", -1, -1, Gtk::STOCK_CANCEL);

		$continue_img = GtkImage::new_from_stock(Gtk::STOCK_APPLY, Gtk::ICON_SIZE_SMALL_TOOLBAR);
		$continue_button = new GtkButton("Continue");
		$continue_button->connect_simple("clicked", array("gui_gtk", "confirmation_button_clicked"), "benchmark", $identifiers);
		$continue_button->set_image($continue_img);

		pts_gtk_array_to_boxes($vbox, array($continue_button));

		$window->show_all();
		pts_set_assignment("GTK_OBJ_CONFIRMATION_WINDOW", $window);
		Gtk::main();
	}
	public static function toggle_save_results()
	{
		$save_results = pts_read_assignment("GTK_OBJ_SAVE_RESULTS");
		$is_save = $save_results->get_active();

		pts_gtk_object_set_sensitive("GTK_OBJ_SAVE_NAME", $is_save);
		pts_gtk_object_set_sensitive("GTK_OBJ_TEST_IDENTIFIER", $is_save);
		pts_gtk_object_set_sensitive("GTK_OBJ_GLOBAL_UPLOAD", $is_save);
	}
	public static function show_phx_global_clone_interface()
	{
		// We need to close the main window now since it seems to cause problems if closed from within launch_phoronix_global_action()
		$main_window = pts_read_assignment("GTK_OBJ_WINDOW");
		$main_window->destroy();

		$window = new pts_gtk_window("Phoronix Global");

		$label_global = new GtkLabel("Phoronix Global is the web repository for the Phoronix Test Suite where test results are publicly hosted. Enter a Phoronix Global ID below. To access Phoronix Global, visit:\n\nhttp://global.phoronix.com/\n");
		$label_global->set_size_request(420, -1);
		$label_global->set_line_wrap(true);

		$global_label = new GtkLabel("Phoronix Global ID:");
		$global_id = new GtkEntry();
		$global_id->connect_simple("backspace", array("gui_gtk", "phoronix_global_id_entry_changed"), null);
		$global_id->connect_simple("key-press-event", array("gui_gtk", "phoronix_global_id_entry_changed"), null);
		$global_id->connect_simple("paste-clipboard", array("gui_gtk", "phoronix_global_id_entry_changed"), null);
		pts_set_assignment("GTK_OBJ_GLOBAL_ID", $global_id);

		$results_button = new pts_gtk_button("View Results", array("gui_gtk", "launch_phoronix_global_action"), "view_results");
		$results_button->set_sensitive(false);
		$clone_button = new pts_gtk_button("Clone", array("gui_gtk", "launch_phoronix_global_action"), "clone_results");
		$clone_button->set_sensitive(false);
		$run_button = new pts_gtk_button("Run Comparison", array("gui_gtk", "launch_phoronix_global_action"), "run_comparison");
		$run_button->set_sensitive(false);

		pts_set_assignment("GTK_OBJ_GLOBAL_RESULTS", $results_button);
		pts_set_assignment("GTK_OBJ_GLOBAL_CLONE", $clone_button);
		pts_set_assignment("GTK_OBJ_GLOBAL_RUN", $run_button);

		pts_gtk_array_to_boxes($window, array($label_global, array($global_label, $global_id), array($results_button, $clone_button, $run_button)), 4);
		$window->show_all();
		pts_set_assignment("GTK_OBJ_GLOBAL_WINDOW", $window);
		Gtk::main();
	}
	public static function show_phx_global_login_interface()
	{
		// We need to close the main window now since it seems to cause problems if closed from within launch_phoronix_global_action()
		$main_window = pts_read_assignment("GTK_OBJ_WINDOW");
		$main_window->destroy();

		$window = new pts_gtk_window("Phoronix Global Log-In");

		$label_global = new GtkLabel("Phoronix Global is the web repository for the Phoronix Test Suite where test results are publicly hosted. Anonymous uploads are supported or you can log-in to your account here. To create an account, visit: http://global.phoronix.com/\n");
		$label_global->set_size_request(420, -1);
		$label_global->set_line_wrap(true);

		$user_label = new GtkLabel("User-Name:");
		$user_login = new GtkEntry();
		pts_set_assignment("GTK_OBJ_GLOBAL_USER", $user_login);

		$password_label = new GtkLabel("Password:");
		$password_login = new GtkEntry();
		$password_login->set_visibility(false); // TODO: currently this causes the password not to show with ->get_text()
		pts_set_assignment("GTK_OBJ_GLOBAL_PASSWORD", $password_login);

		$login_button = new pts_gtk_button("Log-In", array("gui_gtk", "launch_phoronix_global_action"), "login");

		pts_set_assignment("GTK_OBJ_GLOBAL_RESULTS", $results_button);
		pts_set_assignment("GTK_OBJ_GLOBAL_CLONE", $clone_button);
		pts_set_assignment("GTK_OBJ_GLOBAL_RUN", $run_button);

		pts_gtk_array_to_boxes($window, array($label_global, array($user_label, $user_login), array($password_label, $password_login), array($login_button)), 4);
		$window->show_all();
		pts_set_assignment("GTK_OBJ_GLOBAL_WINDOW", $window);
		Gtk::main();
	}
	public static function phoronix_global_id_entry_changed($force = false)
	{
		$id_entry = pts_read_assignment("GTK_OBJ_GLOBAL_ID");
		$global_id = $id_entry->get_text();
		$is_valid = $force || pts_global_valid_id_string($global_id);

		pts_gtk_object_set_sensitive("GTK_OBJ_GLOBAL_RESULTS", $is_valid);
		pts_gtk_object_set_sensitive("GTK_OBJ_GLOBAL_CLONE", $is_valid);
		pts_gtk_object_set_sensitive("GTK_OBJ_GLOBAL_RUN", $is_valid);
	}
	public static function launch_phoronix_global_action($action)
	{
		$id_entry = pts_read_assignment("GTK_OBJ_GLOBAL_ID");
		$global_id = ($id_entry != false ? trim($id_entry->get_text()) : false);

		/*
		if(!pts_is_global_id($global_id))
		{
			gui_gtk::phoronix_global_id_entry_changed(true);
			return;
		}
		*/

		$global_window = pts_read_assignment("GTK_OBJ_GLOBAL_WINDOW");
		$global_window->destroy();

		switch($action)
		{
			case "view_results":
				gui_gtk::launch_web_browser(pts_global_public_url($global_id));
				pts_run_option_next("gui_gtk");
				break;
			case "clone_results":
				pts_run_option_next("clone_global_result", $global_id, array("AUTOMATED_MODE" => true));
				pts_run_option_next("gui_gtk");
				//Gtk::main_quit();
				break;
			case "run_comparison":
				gui_gtk::show_run_confirmation_interface($global_id);
				break;
			case "login":
				$username = pts_read_assignment("GTK_OBJ_GLOBAL_USER");
				$password = pts_read_assignment("GTK_OBJ_GLOBAL_PASSWORD");

				$username = $username->get_text();
				$password = md5($password->get_text());

				pts_global_setup_account($username, $password); // TODO: add interface showing whether or not it worked
				pts_run_option_next("gui_gtk");
				break;
		}
	}
	public static function launch_web_browser($url = "")
	{
		if($url instanceOf GtkImageMenuItem || $url instanceOf GtkMenuItem)
		{
			switch($url->child->get_label())
			{
				case "Phoronix-Test-Suite.com":
					$url = "http://www.phoronix-test-suite.com/";
					break;
				case "Community Support Online":
					$url = "http://www.phoronix.com/forums/forumdisplay.php?f=49";
					break;
				case "Phoronix Media":
					$url = "http://www.phoronix-media.com/";
					break;
				case "View Documentation":
					$url = PTS_PATH . "documentation/index.html";
					break;
			}
		}
		else if($url == "")
		{
			$url = "http://www.phoronix-test-suite.com/";
		}

		pts_display_web_browser($url, null, true, true);
	}
	public static function details_button_clicked()
	{
		$identifier = pts_read_assignment("GTK_LAST_SELECTED_ITEM");

		if(pts_read_assignment("GTK_MAIN_NOTEBOOK_SELECTED") == "Test Results")
		{
			pts_display_web_browser(SAVE_RESULTS_DIR . $identifier . "/index.html", null, true, true);
		}
		else
		{
			$gtk_obj_main_frame = pts_read_assignment("GTK_OBJ_MAIN_FRAME");

			if(pts_is_assignment("GTK_OBJ_MAIN_FRAME_BOX"))
			{
				$gtk_obj_main_frame_box = pts_read_assignment("GTK_OBJ_MAIN_FRAME_BOX");
				$gtk_obj_main_frame->remove($gtk_obj_main_frame_box);
			}

			$elements = array();

			if(pts_read_assignment("GTK_TEST_OR_SUITE") == "SUITE")
			{
				$identifier = pts_suite_name_to_identifier($identifier);

				$label_tests = new GtkLabel("Suite Contains: " . implode(", ", pts_contained_tests($identifier, false, false, true)));
				$label_tests->set_size_request(420, -1);
				$label_tests->set_line_wrap(true);
				array_push($elements, $label_tests);
			}
			else
			{
				$identifier = pts_test_name_to_identifier($identifier);
				$obj = new pts_test_profile_details($identifier);

				$str = "Software Dependencies: ";
				$i = 0;
				foreach($obj->dependency_names() as $dependency)
				{
					if($i > 0)
					{
						$str .= ", ";
					}

					$str .= $dependency;
					$i++;
				}
				if($i == 0)
				{
					$str .= "N/A";
				}

				$label_dependencies = new GtkLabel($str);
				$label_dependencies->set_size_request(420, -1);
				$label_dependencies->set_line_wrap(true);
				array_push($elements, $label_dependencies);

				// Suites

				$str = "Suites Using This Test: ";
				$i = 0;
				foreach($obj->suites_using_this_test() as $suite)
				{
					if($i > 0)
					{
						$str .= ", ";
					}

					$str .= pts_suite_identifier_to_name($suite);
					$i++;
				}
				if($i == 0)
				{
					$str .= "N/A";
				}

				$label_suites = new GtkLabel($str);
				$label_suites->set_size_request(420, -1);
				$label_suites->set_line_wrap(true);
				array_push($elements, $label_suites);
			}

			$box = pts_gtk_array_to_boxes($gtk_obj_main_frame, $elements, 4);
			pts_set_assignment("GTK_OBJ_MAIN_FRAME_BOX", $box);
			gui_gtk::redraw_main_window();
		}
	}
	public static function notebook_selected_to_identifier()
	{
		$identifiers = pts_read_assignment("GTK_SELECTED_ITEMS");
		$selected = array();

		foreach($identifiers as $identifier)
		{
			if(pts_read_assignment("GTK_MAIN_NOTEBOOK_SELECTED") == "Test Results")
			{
				$identifier = $identifier;
			}
			else if(pts_read_assignment("GTK_TEST_OR_SUITE") == "SUITE")
			{
				$identifier = pts_suite_name_to_identifier($identifier);
			}
			else
			{
				$identifier = pts_test_name_to_identifier($identifier);
			}
			array_push($selected, $identifier);
		}

		return $selected;
	}
	public static function update_run_button()
	{
		$identifier = gui_gtk::notebook_selected_to_identifier();

		if(count($identifier) > 1)
		{
			$button_string = "Run"; // TODO: cleanup
		}
		else
		{
			$identifier = array_pop($identifier);

			if(pts_is_test($identifier))
			{
				if(!pts_test_installed($identifier))
				{
					$button_string = "Install";

				}
				else if(pts_test_needs_updated_install($identifier))
				{
					$button_string = "Update";
				}
				else
				{
					$button_string = "Run";
				}
			}
			else if(pts_is_suite($identifier) || pts_is_test_result($identifier))
			{
				$button_string = (pts_suite_needs_updated_install($identifier) ? "Update" : "Run");
			}
		}

		pts_set_assignment("GTK_RUN_BUTTON_TASK", strtoupper($button_string));
		$run_button = pts_read_assignment("GTK_OBJ_RUN_BUTTON");
		$run_button->set_label($button_string);
	}
	public static function notebook_main_page_select($notebook, $ptr, $current_page)
	{
		$details_button = pts_read_assignment("GTK_OBJ_DETAILS_BUTTON");
		if(!($details_button instanceOf pts_gtk_button))
		{
			return;
		}

		$selected_page = $notebook->get_tab_label_text($notebook->get_nth_page($current_page));
		pts_set_assignment("GTK_MAIN_NOTEBOOK_SELECTED", $selected_page);

		switch($selected_page)
		{
			case "Test Results":
				$details_button->set_label("View Results");
				pts_gtk_object_set_sensitive("GTK_OBJ_CHECK_DEFAULTS", false);
				pts_gtk_object_set_sensitive("GTK_OBJ_CHECK_BATCH", false);
				break;
			default:
				$has_selected = pts_read_assignment("GTK_HAS_TOUCHED_SELECT_MENU");

				$details_button->set_label("More Information");
				pts_gtk_object_set_sensitive("GTK_OBJ_CHECK_DEFAULTS", $has_selected);
				pts_gtk_object_set_sensitive("GTK_OBJ_CHECK_BATCH", $has_selected);
				break;
		}
	}
	public static function check_test_type_select($object)
	{
		gui_gtk::check_test_select($object, "SUBSYSTEMS");
	}
	public static function check_test_license_select($object)
	{
		gui_gtk::check_test_select($object, "LICENSES");
	}
	public static function check_test_select($object, $type)
	{
		$item = $object->child->get_label();
		//$to_add = $object->get_active();

		$items_to_show = pts_read_assignment(($type == "SUBSYSTEMS" ? "GTK_TEST_TYPES_TO_SHOW" : "GTK_TEST_LICENSES_TO_SHOW"));

		if($items_to_show == null)
		{
			$items_to_show = array();
		}

		if(!in_array($item, $items_to_show))
		{
			array_push($items_to_show, $item);
		}
		else
		{
			$items_to_show_1 = $items_to_show;
			$items_to_show = array();

			foreach($items_to_show_1 as $show)
			{
				if($show != $item)
				{
					array_push($items_to_show, $show);
				}
			}
		}

		pts_set_assignment(($type == "SUBSYSTEMS" ? "GTK_TEST_TYPES_TO_SHOW" : "GTK_TEST_LICENSES_TO_SHOW"), $items_to_show);

		gui_gtk::update_main_notebook();
		gui_gtk::redraw_main_window();
	}
	public static function show_about_interface()
	{
		$window = new pts_gtk_window("About");
		$window->set_resizable(false);

		$logo = GtkImage::new_from_file(RESULTS_VIEWER_DIR . "pts-logo.png");
		$logo->set_size_request(158, 82);

		$label_version = new GtkLabel("Version " . PTS_VERSION);

		$event_box = new GtkEventBox();
		$label_url = new GtkLabel("www.phoronix-test-suite.com");
		$event_box->connect_simple("button-press-event", array("gui_gtk", "launch_web_browser"), "");
		$event_box->add($label_url);

		pts_gtk_array_to_boxes($window, array($logo,
			new pts_gtk_label(ucwords(strtolower(PTS_CODENAME)), "Sans 19"), $label_version, $event_box,
			new pts_gtk_label("Copyright (C) 2008 - 2009 By Phoronix Media\nCopyright (C) 2008 - 2009 By Michael Larabel", "Sans 9")), 6);

		$window->show_all();
		Gtk::main();
	}
	public static function show_batch_preferences_interface()
	{
		gui_gtk::show_preferences_interface("BATCH");
	}
	public static function show_preferences_interface()
	{
		$editable_preferences = array(
		// User Settings
		P_OPTION_TEST_REMOVEDOWNLOADS, P_OPTION_CACHE_SEARCHMEDIA, P_OPTION_CACHE_SYMLINK,
		P_OPTION_PROMPT_DOWNLOADLOC, P_OPTION_TEST_ENVIRONMENT, P_OPTION_CACHE_DIRECTORY,
		P_OPTION_TEST_SLEEPTIME, P_OPTION_LOG_VSYSDETAILS, P_OPTION_LOG_BENCHMARKFILES,
		P_OPTION_BATCH_SAVERESULTS, P_OPTION_BATCH_LAUNCHBROWSER, P_OPTION_BATCH_UPLOADRESULTS,
		P_OPTION_BATCH_PROMPTIDENTIFIER, P_OPTION_BATCH_PROMPTDESCRIPTION, P_OPTION_BATCH_PROMPTSAVENAME,
		// Graph Settings
		P_GRAPH_SIZE_WIDTH, P_GRAPH_SIZE_HEIGHT, P_GRAPH_RENDERER,
		P_GRAPH_MARKCOUNT, P_GRAPH_WATERMARK,
		P_GRAPH_BORDER, P_GRAPH_COLOR_BACKGROUND, P_GRAPH_COLOR_BODY,
		P_GRAPH_COLOR_NOTCHES, P_GRAPH_COLOR_BORDER, P_GRAPH_COLOR_ALTERNATE,
		P_GRAPH_COLOR_PAINT, P_GRAPH_COLOR_HEADERS, P_GRAPH_COLOR_MAINHEADERS,
		P_GRAPH_COLOR_TEXT, P_GRAPH_COLOR_BODYTEXT, P_GRAPH_FONT_TYPE, P_GRAPH_FONT_SIZE_HEADERS,
		P_GRAPH_FONT_SIZE_SUBHEADERS, P_GRAPH_FONT_SIZE_TEXT, P_GRAPH_FONT_SIZE_IDENTIFIERS,
		P_GRAPH_FONT_SIZE_AXIS
		);

		$window = new pts_gtk_window("Preferences");

		$previous_heading = null;
		$read_config = new pts_config_tandem_XmlReader();
		$graph_config = new pts_graph_config_tandem_XmlReader();

		$i = 0;
		$pages = 0;
		$page_items = array();
		$preference_objects = array();

		$notebook = new GtkNotebook();
		$page_prefix = "";
		$config_type = "user";

		foreach($editable_preferences as $preference)
		{
			if(($h = pts_extract_identifier_from_path($preference)) != $previous_heading)
			{
				if($pages > 0)
				{
					$vbox_page_{$pages} = new GtkVBox();
					pts_gtk_array_to_boxes($vbox_page_{$pages}, $page_items, 1, true);
					$notebook->append_page($vbox_page_{$pages}, new GtkLabel($page_prefix . gui_gtk::caps_to_spaces($previous_heading)));
				}

				if($previous_heading == "BatchMode")
				{
					$config_type = "graph";
					$page_prefix = "Graph ";
				}

				$previous_heading = $h;
				$pages++;
				$page_items = array();
			}

			$pref = basename($preference);
			$current_value = pts_read_graph_config($preference, null, ($config_type == "graph" ? $graph_config : $read_config));

			if($current_value == "TRUE" || $current_value == "FALSE")
			{
				$hb[$i] = new GtkHBox();
				$hb[$i]->pack_start(($radio_true[$i] = new GtkRadioButton(null, "TRUE", true)));
				$hb[$i]->pack_start(($radio_false[$i] = new GtkRadioButton($radio_true[$i], "FALSE", false)));

				$radio_true[$i]->set_active($current_value == "TRUE");
				$preference_objects[$preference] = array($hb[$i], $radio_true[$i]);
			}
			else if(substr($current_value, 0, 1) == "#" && strpos($current_value, " ") == false)
			{
				$cb[$i] = new GtkColorButton();
				$cb[$i]->set_color(GdkColor::parse($current_value));

				$preference_objects[$preference] = $cb[$i];
			}
			else if(is_numeric($current_value))
			{
				$spin[$i] = GtkSpinButton::new_with_range(0, 1024, 1);
				$spin[$i]->set_value($current_value);

				$preference_objects[$preference] = $spin[$i];
			}
			else if(in_array($preference, array(P_GRAPH_FONT_TYPE)))
			{
				$entry[$i] = new pts_gtk_button($current_value, array("gui_gtk", "show_find_font_interface"), "this");
				$preference_objects[$preference] = $entry[$i];
			}
			else
			{
				$entry[$i] = new GtkEntry();
				$entry[$i]->set_text($current_value);
				$preference_objects[$preference] = $entry[$i];
			}

			$header[$i] = new GtkLabel(" " . gui_gtk::caps_to_spaces(basename($pref)) . ":");
			$header[$i]->set_alignment(0, 0.5);
			array_push($page_items, array($header[$i], (is_array($preference_objects[$preference]) ? 
			array_shift($preference_objects[$preference]) : $preference_objects[$preference])));

			$i++;
		}
		$vbox_page_{$pages} = new GtkVBox();
		pts_gtk_array_to_boxes($vbox_page_{$pages}, $page_items, 1, true);
		$notebook->append_page($vbox_page_{$pages}, new GtkLabel($page_prefix . $previous_heading));

		pts_set_assignment("GTK_OBJ_PREFERENCES", $preference_objects);

		$return_button = new pts_gtk_button("Help", array("gui_gtk", "launch_web_browser"), PTS_USER_DIR . "user-config.xml", -1, -1, Gtk::STOCK_HELP);
		$continue_button = new pts_gtk_button("Save", array("gui_gtk", "preferences_button_clicked"), "save", -1, -1, Gtk::STOCK_APPLY);

		pts_gtk_array_to_boxes($window, array($notebook, array($return_button, $continue_button)), 2);
		pts_set_assignment("GTK_OBJ_PREFERENCES_WINDOW", $window);

		$window->show_all();
		Gtk::main();
	}
	public static function caps_to_spaces($str)
	{
		$new_str = "";

		for($i = 0; $i < strlen($str); $i++)
		{
			$new_str .= ($i > 0 && ($ascii = ord($str[$i])) > 64 && $ascii < 91 ? " " : "") . $str[$i];
		}

		return $new_str;
	}
	public static function preferences_button_clicked($button_press)
	{
		if($button_press == "save")
		{
			$preferences = pts_read_assignment("GTK_OBJ_PREFERENCES");
			$preferences_set = array();

			$preferences_set[P_OPTION_BATCH_CONFIGURED] = "TRUE";

			foreach($preferences as $preference => $object)
			{
				if(is_array($object))
				{
					$object = array_pop($object);
				}

				if($object instanceOf GtkEntry)
				{
					$preferences_set[$preference] = $object->get_text();
				}
				else if($object instanceOf GtkRadioButton)
				{
					$preferences_set[$preference] = ($object->get_active() ? "TRUE" : "FALSE");
				}
				else if($object instanceOf GtkColorButton)
				{
					$color = $object->get_color();

					$red = dechex(floor(($color->red / 65535) * 255));
					$green = dechex(floor(($color->green / 65535) * 255));
					$blue = dechex(floor(($color->blue / 65535) * 255));

					$color = $red . $green . $blue;

					if(($sl = strlen($color)) == 3)
					{
						$color .= $color;
					}
					else if($sl < 6)
					{
						$color .= str_repeat(substr($color, -1), (6 - $sl));
					}					

					$preferences_set[$preference] = "#" . $color;
				}
				else if($object instanceOf GtkSpinButton)
				{
					$preferences_set[$preference] = $object->get_value();
				}
				else if($object instanceOf GtkComboBox)
				{
					$preferences_set[$preference] = pts_config_bool_to_string($object->get_active() == 0);
				}
				else if($object instanceOf GtkButton)
				{
					$preferences_set[$preference] = $object->get_label();
				}
			}
			pts_user_config_init($preferences_set);
			pts_graph_config_init($preferences_set);
		}

		$window = pts_read_assignment("GTK_OBJ_PREFERENCES_WINDOW");
		$window->destroy();
	}
	public static function show_find_font_interface($button)
	{
		$dialog = new GtkFileChooserDialog("Open TTF Font", null,  Gtk::FILE_CHOOSER_ACTION_OPEN, array(Gtk::STOCK_OK, Gtk::RESPONSE_OK), null);
		$dialog->set_filename("/usr/share/fonts/");
		$dialog->show_all();

		if($dialog->run() == Gtk::RESPONSE_OK)
		{
			$open_file = $dialog->get_filename();
			$button->set_label($open_file);
		}
		$dialog->destroy();
	}
	public static function upload_results_to_global()
	{
		$main_window = pts_read_assignment("GTK_OBJ_WINDOW");
		$main_window->destroy();

		$identifier = pts_read_assignment("GTK_LAST_SELECTED_ITEM");
		pts_run_option_next("upload_result", $identifier, array("AUTOMATED_MODE" => true));
		pts_run_option_next("gui_gtk");
	}
	public static function show_generate_pdf_interface()
	{
		gui_gtk::show_generate_export_interface("pdf");
	}
	public static function show_generate_csv_interface()
	{
		gui_gtk::show_generate_export_interface("csv");
	}
	public static function show_generate_text_interface()
	{
		gui_gtk::show_generate_export_interface("text");
	}
	public static function show_generate_export_interface($type)
	{
		$dialog = new GtkFileChooserDialog("Save Results To " . strtoupper($type), null, Gtk::FILE_CHOOSER_ACTION_SAVE, array(Gtk::STOCK_OK, Gtk::RESPONSE_OK), null);
		$dialog->show_all();

		if($dialog->run() == Gtk::RESPONSE_OK)
		{
			$save_file = $dialog->get_filename();

			$main_window = pts_read_assignment("GTK_OBJ_WINDOW");
			$main_window->destroy();

			$identifier = pts_read_assignment("GTK_LAST_SELECTED_ITEM");
			pts_run_option_next("result_file_to_" . strtolower($type), $identifier, array("SAVE_TO" => $save_file));
			pts_run_option_next("gui_gtk");
		}
		$dialog->destroy();
	}
	public static function show_generate_archive_interface()
	{
		$dialog = new GtkFileChooserDialog("Archive Results", null, Gtk::FILE_CHOOSER_ACTION_SAVE, array(Gtk::STOCK_OK, Gtk::RESPONSE_OK), null);
		$dialog->show_all();

		if($dialog->run() == Gtk::RESPONSE_OK)
		{
			$save_file = $dialog->get_filename();
			$identifier = pts_read_assignment("GTK_LAST_SELECTED_ITEM");
			pts_archive_result_directory($identifier, $save_file);
		}
		$dialog->destroy();
	}
	public static function quick_operation($ignore_var, $operation)
	{
		$main_window = pts_read_assignment("GTK_OBJ_WINDOW");
		$main_window->destroy();

		$identifier = pts_read_assignment("GTK_LAST_SELECTED_ITEM");

		switch($operation)
		{
			default:
				pts_run_option_next($operation, $identifier, array("AUTOMATED_MODE" => true));
				break;
		}

		pts_run_option_next("gui_gtk");
	}
	public static function show_dependency_info_interface()
	{
		$window = new pts_gtk_window("External Dependencies");
		$window->set_resizable(false);

		$notebook = new GtkNotebook();
		$notebook->set_size_request(540, 250);

		$installed_dependencies = array_map("pts_external_dependency_generic_title", pts_external_dependencies_installed());
		sort($installed_dependencies);
		$installed = pts_gtk_table(array(""), $installed_dependencies, null, "No software dependencies are installed.", false);
		pts_gtk_add_notebook_tab($notebook, $installed, "Installed Dependencies");

		$missing_dependencies = array_map("pts_external_dependency_generic_title", pts_external_dependencies_missing());
		sort($missing_dependencies);
		$missing = pts_gtk_table(array(""), $missing_dependencies, null, "No software dependencies are missing.", false);
		pts_gtk_add_notebook_tab($notebook, $missing, "Missing Dependencies");

		pts_gtk_array_to_boxes($window, array($notebook), 3);

		$window->show_all();
		Gtk::main();
	}
	public static function show_system_info_interface()
	{
		$window = new pts_gtk_window("System Information");
		$window->set_resizable(false);

		$notebook = new GtkNotebook();
		$notebook->connect("switch-page", array("gui_gtk", "system_info_change_notebook"));
		$notebook->set_size_request(540, 250);

		pts_set_assignment("GTK_SYSTEM_INFO_NOTEBOOK", "Hardware");
		$hw = pts_gtk_table(array("", ""), pts_array_with_key_to_2d(pts_hw_string(false)), null, "No system information available.", false);
		pts_gtk_add_notebook_tab($notebook, $hw, "Hardware");

		$sw = pts_gtk_table(array("", ""), pts_array_with_key_to_2d(pts_sw_string(false)), null, "No system information available.", false);
		pts_gtk_add_notebook_tab($notebook, $sw, "Software");

		$sensors = pts_gtk_table(array("", ""), pts_array_with_key_to_2d(pts_sys_sensors_string(false)), null, "No system information available.", false);
		pts_gtk_add_notebook_tab($notebook, $sensors, "Sensors");

		$copy_button = new pts_gtk_button("Copy To Clipboard", array("gui_gtk", "system_info_copy_to_clipboard"), null);

		pts_gtk_array_to_boxes($window, array($notebook, $copy_button), 3);

		$window->show_all();
		Gtk::main();
	}
	public static function system_info_change_notebook($notebook, $ptr, $current_page)
	{
		$identifier = $notebook->get_tab_label_text($notebook->get_nth_page($current_page));
		pts_set_assignment("GTK_SYSTEM_INFO_NOTEBOOK", $identifier);
	}
	public static function system_info_copy_to_clipboard()
	{
		$clipboard = pts_read_assignment("GTK_OBJ_CLIPBOARD");

		switch(pts_read_assignment("GTK_SYSTEM_INFO_NOTEBOOK"))
		{
			case "Hardware":
				$to_copy = pts_hw_string();
				break;
			case "Software":
				$to_copy = pts_sw_string();
				break;
			case "Sensors":
				$to_copy = pts_sys_sensors_string();
				break;
		}

		$clipboard->set_text($to_copy);	
	}
	public static function show_pcqs_install_interface()
	{
		$license = pts_pcqs_user_license();

		if($license == false)
		{
			return;
		}

		$window = new pts_gtk_window("Phoronix Certification & Qualification Suite");
		$textview_pcqs = new pts_gtk_text_area($license, 540, 250);
		$return_button = new pts_gtk_button("Return", array("gui_gtk", "pcqs_button_clicked"), "return", -1, -1, Gtk::STOCK_CANCEL);
		$continue_button = new pts_gtk_button("Install", array("gui_gtk", "pcqs_button_clicked"), "install", -1, -1, Gtk::STOCK_APPLY);

		pts_gtk_array_to_boxes($window, array($textview_pcqs, array($return_button, $continue_button)), 4);
		pts_set_assignment("GTK_OBJ_PCQS_WINDOW", $window);

		$window->show_all();
		Gtk::main();
	}
	public static function process_user_agreement_prompt($event)
	{
		pts_set_assignment("AGREED_TO_TERMS", ($event == "yes"));

		$window = pts_read_assignment("GTK_USER_AGREEMENT_WINDOW");
		$window->destroy();
	}
	public static function pts_user_agreement_prompt($user_agreement)
	{
		pts_set_assignment("AGREED_TO_TERMS", false);
		$window = new pts_gtk_window("Phoronix Test Suite - User Agreement");

		$textview_agreement = new pts_gtk_text_area(trim($user_agreement), 540, 250);
		$return_button = new pts_gtk_button("Quit", array("gui_gtk", "process_user_agreement_prompt"), "quit", -1, -1, Gtk::STOCK_CANCEL);
		$continue_button = new pts_gtk_button("Accept To Terms", array("gui_gtk", "process_user_agreement_prompt"), "yes", -1, -1, Gtk::STOCK_APPLY);

		pts_gtk_array_to_boxes($window, array($textview_agreement, new GtkLabel("Do you agree to the user terms listed above?"), array($return_button, $continue_button)), 1);

		pts_set_assignment("GTK_USER_AGREEMENT_WINDOW", $window);
		$window->show_all();
		Gtk::main();

		return pts_read_assignment("AGREED_TO_TERMS");
	}
	public static function redraw_main_window()
	{
		$window = pts_read_assignment("GTK_OBJ_WINDOW");
		$window->show_all();
	}
}

?>
