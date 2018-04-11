<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2007 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");
include("showstuff.php");
include_once("table_defs.php");

# ignore warnings for is_file permission errors
error_reporting(0);

PAGEHEADER("DeterLab's Research User Stats", null, null, $notice);
#
# No need to be logged on to do this
#

$html_share	= null;


$result = DBQueryFatal("select count(p.pid) from projects p left join project_stats as ps on p.pid=ps.pid where research_type<>'Class' and research_type<>'Internal' and ps.allexpt_duration>0");
$row = mysql_fetch_array($result);
$numclass = $row["count(p.pid)"];

$result = DBQueryFatal("select count(distinct usr_affil) from users left join projects as p on head_idx=uid_idx left join project_stats as ps on p.pid=ps.pid where research_type<>'Class' and research_type<>'Internal' and ps.allexpt_duration>0;");
$row = mysql_fetch_array($result);
$numinst = $row["count(distinct usr_affil)"];

$result = DBQueryFatal("select count(*) from users as u left join group_membership as g on u.uid=g.uid left join projects as p on g.pid = p.pid left join user_stats as us on u.uid=us.uid where p.research_type<>'Class' and p.research_type<>'Internal' and us.allexpt_duration>0");
$row = mysql_fetch_array($result);
$numuser = $row["count(*)"];


$result = DBQueryFatal("select count(distinct(usr_country)) from users as u left join group_membership as g on u.uid=g.uid left join projects as p on g.pid = p.pid left join user_stats as us on u.uid=us.uid where p.research_type<>'Class' and p.research_type<>'Internal' and us.allexpt_duration>0");
$row = mysql_fetch_array($result);
$numco = $row["count(distinct(usr_country))"];

$result = DBQueryFatal("select count(distinct(usr_city)) from users as u left join group_membership as g on u.uid=g.uid left join projects as p on g.pid = p.pid left join user_stats as us on u.uid=us.uid where p.research_type<>'Class' and p.research_type<>'Internal' and us.allexpt_duration>0");
$row = mysql_fetch_array($result);
$numci = $row["count(distinct(usr_city))"];


echo "DeterLab has been used by $numclass research projects, from $numinst institutions and involving $numuser researchers, from $numci locations and $numco countries.";

echo "<P>Here is the count of projects per research type (including class) that have made active use of the testbed.<P>";

echo "<script type=\"text/javascript\" src=\"https://www.gstatic.com/charts/loader.js\"></script>
    <script type=\"text/javascript\">
      google.charts.load('current', {'packages':['corechart']});
      google.charts.setOnLoadCallback(drawChart);
      function drawChart() {

      var data = google.visualization.arrayToDataTable([
      ['Research Type', 'Num projects'],";
      
$result = DBQueryFatal("select research_type,count(*) from projects as p left join project_stats as ps on p.pid=ps.pid where research_type<>'Internal' and ps.allexpt_duration>0 group by research_type order by count(*) desc");
while($row = mysql_fetch_array($result))
{
	#echo $row["research_type"] . " " . $row["count(*)"] . "<br>";
	echo "['" . $row["research_type"] . "'," . $row["count(*)"] . "],";
}

echo "]);

      var options = {
      title: 'Research Projects on DeterLab'
      };

      var chart = new google.visualization.PieChart(document.getElementById('piechart'));

      chart.draw(data, options);
      }
    </script>";
    
echo "<div id=\"piechart\" style=\"width: 900px; height: 500px;\"></div>";

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>



