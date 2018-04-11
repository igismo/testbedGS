<?php
include("defs.php");

# Verify page arguments.
$optargs = OptionalPageArguments(
    "pid",      PAGEARG_STRING,
    "uid",      PAGEARG_STRING
);


$this_user = CheckLoginOrDie();
$isadmin   = ISADMIN();

if (!$isadmin)
    USERERROR("You are not a testbed administrator");

#
# Standard Testbed Header
#
PAGEHEADER("New Project Vote Links");

# $query_result = DBQueryFatal(
$query =
    'select u.uid, p.pid, p.status, v.token ' .
    '  from votes v, projects p, users u ' .
    ' where v.pid_idx = p.pid_idx and v.uid_idx = u.uid_idx ' .
    '       and p.status=\'unapproved\'';

# add PID if set
if (isset($pid)) {
    $pid = addslashes($pid);
    $query .= "and p.pid = '$pid' ";
}

# add UID if set
if (isset($uid)) {
    $uid = addslashes($uid);
    $query .= "and u.uid = '$uid' ";
}

$query .= ' order by p.created desc, u.uid';

$query_result = DBQueryFatal($query);

# spit out the table

?>

<table style="margin-left: auto; margin-right: auto; width: 60%;">
<tr>
    <th>Exec Member</th>
    <th>Project</th>
    <th></th>
    <th>Link</th>
</tr>

<?

# one row per link
while ($row = mysql_fetch_array($query_result)) {
    row(
        url($row[0], "vote_links.php?uid=$row[0]"),
        url($row[1], "vote_links.php?pid=$row[1]"),
        image($row[2] ? 'greenball.gif' : 'redball.gif'),
        url("Vote", "voteproject.php?token=$row[3]")
    );
}

echo "</table>";

PAGEFOOTER();

#
# helper functions below
#

function row() {
    echo "<tr>";

    foreach (func_get_args() as $col)
        echo "<td>$col</td>";

    echo "</tr>";
}

function url($text, $url) {
    return "<a href=\"$url\">$text</a>";
}

function image($file) {
    return "<img src=\"$file\" />";
}
