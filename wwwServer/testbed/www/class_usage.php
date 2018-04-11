<?php

include("defs.php");
$this_user = CheckLoginOrDie();
$idx = $this_user->uid_idx();
$isadmin = ISADMIN();

#
# People who are allowed to see this page:
#   - red dot
#   - instructor/TA for class project
#
if (!$isadmin) {
   $qr = DBQueryFatal(
        'select p.pid from projects p, group_membership g ' .
        ' where p.pid_idx = g.pid_idx and ' .
        '       g.pid_idx = g.gid_idx and ' .
        "       p.class and g.uid_idx = $idx and " .
        '       g.trust in ("project_root", "group_root")'
   );
   if (mysql_num_rows($qr) == 0)
      USERERROR("You are not authorized to see this page");
}

$optargs = OptionalPageArguments("pid", PAGEARG_STRING);

if (isset($pid))
    $title = "Usage statistics for project $pid";
else {
    $pid = '';
    $title = "Usage statistics for all class projects";
}

PAGEHEADER($title);

print "<table border=\"1\">\n";
passthru("/usr/testbed/libexec/class_usage $pid");
print "</table>\n";

PAGEFOOTER();
?>
