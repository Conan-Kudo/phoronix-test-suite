<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2008 - 2009, Phoronix Media
	Copyright (C) 2008 - 2009, Michael Larabel
	pts-functions_system.php: Include system functions.

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

require_once(PTS_PATH . "pts-core/functions/pts-functions_system_software.php");
require_once(PTS_PATH . "pts-core/functions/pts-functions_system_hardware.php");
require_once(PTS_PATH . "pts-core/functions/pts-functions_system_parsing.php");
require_once(PTS_PATH . "pts-core/functions/pts-functions_system_cpu.php");
require_once(PTS_PATH . "pts-core/functions/pts-functions_system_memory.php");
require_once(PTS_PATH . "pts-core/functions/pts-functions_system_graphics.php");

function pts_hw_string($return_string = true)
{
	// Returns string of hardware information
	$hw = array();

	$hw["Processor"] = hw_cpu_string() . " (Total Cores: " . hw_cpu_core_count() . ")";
	$hw["Motherboard"] = hw_sys_motherboard_string();
	$hw["Chipset"] = hw_sys_chipset_string();
	$hw["System Memory"] = hw_sys_memory_string();
	$hw["Disk"] = hw_sys_hdd_string();
	$hw["Graphics"] = hw_gpu_string() . hw_gpu_frequency();
	$hw["Screen Resolution"] = hw_gpu_current_mode();

	return pts_process_string_array($return_string, $hw);
}
function pts_sw_string($return_string = true)
{
	// Returns string of software information
	$sw = array();

	$sw["OS"] = sw_os_release();
	$sw["Kernel"] = sw_os_kernel() . " (" . sw_os_architecture() . ")";

	if(($desktop = sw_desktop_environment()) != "")
	{
		$sw["Desktop"] = $desktop;
	}

	$sw["Display Server"] = sw_os_graphics_subsystem();

	if(($ddx = sw_xorg_ddx_driver_info()) != "")
	{
		$sw["Display Driver"] = $ddx;
	}

	$sw["OpenGL"] = sw_os_opengl();
	$sw["Compiler"] = sw_os_compiler();
	$sw["File-System"] = sw_os_filesystem();

	return pts_process_string_array($return_string, $sw);
}
function pts_sys_sensors_string($return_string = true)
{
	$sensors = array();

	// TODO: Come up with a way to not need to statically label the units, including in system_monitor module

	$sensors["GPU Temperature"] = hw_gpu_temperature() . " C";
	$sensors["CPU Temperature"] = hw_cpu_temperature() . " C";
	$sensors["HDD Temperature"] = hw_sys_hdd_temperature() . " C";
	$sensors["System Temperature"] = hw_sys_temperature() . " C";

	$sensors["CPU Frequency"] = hw_cpu_current_frequency() . " MHz";
	$sensors["GPU Frequency"] = implode(" / ", hw_gpu_current_frequency()) . " MHz";

	$sensors["CPU Usage"] = hw_cpu_usage() . " %";
	$sensors["GPU Usage"] = hw_gpu_core_usage() . " %";

	$sensors["Memory Usage"] = sw_physical_memory_usage() . " MB";
	$sensors["SWAP Usage"] = sw_swap_memory_usage() . " MB";

	$sensors["CPU Voltage"] = hw_sys_line_voltage("CPU") . " V";
	$sensors["+3.33V Voltage"] = hw_sys_line_voltage("V3") . " V";
	$sensors["+5.00V Voltage"] = hw_sys_line_voltage("V5") . " V";
	$sensors["+12.00V Voltage"] = hw_sys_line_voltage("V12") . " V";

	//$sensors["Battery Power Consumption"] = hw_sys_power_consumption_rate();
	$sensors = pts_remove_unsupported_entries($sensors);

	return pts_process_string_array($return_string, $sensors);
}

function pts_remove_unsupported_entries($array, $check_at_start_of_string = false)
{
	$clean_elements = array();

	foreach($array as $key => $value)
	{
		if($value != -1 && !empty($value))
		{
			$clean_elements[$key] = $value;
		}
	}

	return $clean_elements;
}
function pts_system_identifier_string()
{
	$components = array(hw_cpu_string(false), hw_sys_motherboard_string(), sw_os_release(), sw_os_compiler());
	return base64_encode(implode("__", $components));
}

?>
