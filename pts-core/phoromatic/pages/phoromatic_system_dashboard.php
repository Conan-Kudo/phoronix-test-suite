<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2014, Phoronix Media
	Copyright (C) 2014, Michael Larabel

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


class phoromatic_system_dashboard implements pts_webui_interface
{
	public static function page_title()
	{
		return 'System Dashboard';
	}
	public static function page_header()
	{
		return null;
	}
	public static function preload($PAGE)
	{
		return true;
	}
	public static function render_page_process($PATH)
	{
		echo phoromatic_webui_header_logged_in();

		$stmt = phoromatic_server::$db->prepare('SELECT * FROM phoromatic_systems WHERE AccountID = :account_id AND State >= 0 ORDER BY LastCommunication DESC');
		$stmt->bindValue(':account_id', $_SESSION['AccountID']);
		$result = $stmt->execute();
		echo '<div style="margin: 10px 0 30px; clear: both; padding-bottom: 40px;">';
		while($row = $result->fetchArray())
		{
			echo '<a href="?systems/' . $row['SystemID'] . '"><div class="phoromatic_dashboard_block">';
			echo '<div style="float: left; width: 35%;">';
			echo '<h1>' . $row['Title'] . '</h1>';

			$components = array_merge(pts_result_file_analyzer::system_component_string_to_array($row['Hardware'], array('Processor', 'Motherboard')), pts_result_file_analyzer::system_component_string_to_array($row['Software'], array('OS', 'Kernel')));
			foreach($components as &$c)
			{
				if(($x = stripos($c, ' @')) !== false)
					$c = substr($c, 0, $x);
				if(($x = stripos($c, ' (')) !== false)
					$c = substr($c, 0, $x);
			}
			echo '<p><em>' . implode(' - ', $components) . '</em></p>';
			echo '<h2>' . $row['CurrentTask'] . '</h2>';
			echo '</div>';

			$time_remaining = phoromatic_compute_estimated_time_remaining($row['EstimatedTimeForTask'], $row['LastCommunication']);

			if($time_remaining)
			{
				echo '<div style="float: left; width: 40%;">';
				echo '<h2>~ ' . $time_remaining . ' <sub>mins</sub></h2>';
				echo '<p class="font-size: 90%;"><em>Estimated Time Remaining</em></p>';
				if(!empty($row['TimeToNextCommunication']))
				{
					echo '<p><em>' . phoromatic_compute_estimated_time_remaining_string($row['TimeToNextCommunication'], $row['LastCommunication'], 'To Next Communication') . '</em></p>';
				}
				echo '</div>';
			}

			echo '<hr style="width: ' . $row['TaskPercentComplete'] . '%;" />';
			echo '</div></a>';
			$system_ids[$row['SystemID']] = $row['Title'];
		}
		echo '</div>';
		echo phoromatic_webui_footer();
	}
}

?>
