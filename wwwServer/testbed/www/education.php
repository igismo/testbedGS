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

PAGEHEADER("DeterLab's Education Materials", null, null, $notice);
#
# No need to be logged on to do this
#

$html_share	= null;


$result = DBQueryFatal("select count(p.pid) from projects p left join project_stats as ps on p.pid=ps.pid where research_type='Class' and ps.allexpt_duration>0");
$row = mysql_fetch_array($result);
$numclass = $row["count(p.pid)"];

$result = DBQueryFatal("select count(distinct usr_affil) from users left join projects as p on head_idx=uid_idx left join project_stats as ps on p.pid=ps.pid where research_type='Class' and ps.allexpt_duration>0;");
$row = mysql_fetch_array($result);
$numinst = $row["count(distinct usr_affil)"];

$result = DBQueryFatal("select sum(data) from project_history where type='class_assign'");
$row = mysql_fetch_array($result);
$numuser = $row["sum(data)"];

echo "DeterLab is dedicated to supporting cyber security education. Since its inception, DETERLab has been used by $numclass classes, from $numinst institutions and involving $numuser users.";


$result = DBQueryFatal("select count(*) from users as u left join group_membership as g on u.uid=g.uid left join projects as p on g.pid = p.pid where p.research_type='Class' and p.pid in (select pid from project_attributes where attrkey='setup_done' and attrvalue='1')");
$row = mysql_fetch_array($result);
$numusercur = $row["count(*)"];

$result = DBQueryFatal("select count(*) from project_attributes where attrkey='setup_done' and attrvalue='1'");
$row = mysql_fetch_array($result);
$numclasscur = $row["count(*)"];

echo " It is currently being used by $numclasscur classes, and $numusercur users.";
echo "<TABLE class=stealth width=100%><TR><TD width=80%><P>DeterLab offers excellent support for teaching. Instructors can: <UL>
      <LI>Benefit from a large collection of publicly available teaching materials
      <LI>Automatically create student accounts
      <LI>Upload class materials 
     <LI>Assign homeworks/projects to students
     <LI>Track student progress on assignments
     <LI>Download assignments for grading
     <LI>Help students directly with many issues, without involving DeterLab staff
</UL>
<P>Students benefit from using DeterLab, too. They develop practical skills in cybersecurity, networking, operating system administration, and coding. These skills make a big difference in job search!  </TD><TD valign=top>";

echo "<P><CENTER><TABLE id='ed-links' class=orange><TR><TD class=orange><A HREF='http://docs.deterlab.net/education/guidelines-for-teachers/'>For Teachers</A></TD><TR><TD>&nbsp</TD><TR><TD class=orange><A HREF='http://docs.deterlab.net/education/guidelines-for-students/'>For Students</A></TD><TR><TD>&nbsp</TD><TR><TD class=orange><A HREF='sharedpublic.php'>Public Materials</A></TD></TABLE></TABLE>";

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>



