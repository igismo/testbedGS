<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2000-2006 University of Utah and the Flux Group.
# All rights reserved.
#
include("defs.php");

#
# Only admins
#

$this_user = CheckLoginOrDie();
$isadmin = ISADMIN();

if (!$isadmin)
    UserError("Must be admin");

# see whether to only display rows which have a null value
$optargs = OptionalPageArguments("nullonly", PAGEARG_BOOLEAN);
$null_only = isset($optargs['nullonly']) && $optargs['nullonly'] ? 1 : 0;

#
# Standard Testbed Header
#
PAGEHEADER("Update DETER Project Categories and Institutions");

# mmmmm [mostly] cut and paste (from view_project.php)
$sql = 
    'select p.pid, p.why, p.org_type stat_type, ifnull(p.research_type, "NULL"), ';

# pull the user affil on new experiment
if ($null_only) { $sql .=
    '       u.usr_affil_abbrev ';
}
else { $sql .=
    '       ifnull(u.usr_affil_abbrev, "NULL") ';
}

$sql .=
    '  from projects p, users u ' .
    ' where u.uid_idx = p.head_idx and ' .
    '       (p.research_type != "Internal" or p.research_type is NULL)';

$result = DBQueryFatal($sql);

?>


<?php
$i = 0;
print "<form name=form1 enctype=multipart/form-data	
                action=process_category_update.php method=post><table>";
print "<input type=submit value=\"Update Values in DB\" />";
print "<input type=\"hidden\" name=\"formfields[newdata]\" value=\"no\">";
print "<tr><td bgcolor=\"white\">PID</td><td>Description</td><td>Institution name</td><td>Institution classification</td><td>Research categories</td></tr><br>";
while ( $row = mysql_fetch_row($result)) {
      $pid = $row[0];
      $desc = $row[1];
      $acad = $row[2];
      $type = $row[3];
      $inst = $row[4];

      if ($null_only && $type != 'NULL' && $inst != 'NULL')
          continue;

      # f00 lol
      $type_style = ($type == 'NULL' ? 'background-color: #f00;' : '');
      $inst_style = ($inst == 'NULL' ? 'background-color: #f00;' : '');

      print "<tr><td><input type=\"hidden\" name=\"formfields[pid$i]\" value=\"$pid\"><b>$pid</b></td><td>$desc</td><td><input type=\"text\" name=\"formfields[inst$i]\" value=\"$inst\" style=\"$inst_style\"></td><td><input type=\"text\" name=\"formfields[acad$i]\" value=\"$acad\"></td><td><input type=\"text\" name=\"formfields[type$i]\" value=\"$type\" style=\"$type_style\"></td></tr>\n";
      $i = $i+1;
   }  

print "<input type=\"hidden\" name=\"formfields[total]\" value=\"$i\" />\n";
print "</form></table>";

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
