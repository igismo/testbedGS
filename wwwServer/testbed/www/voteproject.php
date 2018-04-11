<?php
include("defs.php");

# Verify page arguments.
$reqargs = RequiredPageArguments("token", PAGEARG_STRING);
$optargs = OptionalPageArguments(
    "vote",     PAGEARG_STRING,
    "comment",  PAGEARG_STRING
);
if (!isset($comment))
    $comment = '';

#
# Standard Testbed Header
#
PAGEHEADER("Vote on Testbed Project");

# the token is a lowercase SHA1 or MD5 string
if (!preg_match("/^[0-9a-f]{32,40}$/", $token))
    PAGEERROR("Error: no such token");

$query_result = DBQueryFatal(
    'SELECT v.uid_idx, v.pid_idx, p.pid ' .
    '  from votes v, projects p ' .
    " where v.pid_idx = p.pid_idx and v.token = '$token'"
);
if (mysql_num_rows($query_result) == 0)
    PAGEERROR("Error: no such token");

$row = mysql_fetch_array($query_result);
$pid_idx = $row["pid_idx"];
$pid = $row["pid"];
$uid = $row["uid_idx"];

$this_user = User::Lookup($uid);
$deter_exec = Project::LookupByPid('deter-exec');

# tally the vote if we have one
if (isset($vote)) {
    $vote_val = 0;
    if (strcmp($vote, "yes") == 0)
        $vote_val = 1;
    elseif (strcmp($vote, "abstain") == 0)
        $vote_val = 'NULL';

    $comment = addslashes($comment);

    DBQueryFatal(
        'update votes ' .
        "   set vote = $vote_val, " .
        "       comment = '$comment' " .
        " where token = '$token'"
    );

    echo "<h1>Your vote has been tallied</h1>\n";
}


# display some basic info
$project_query = DBQueryFatal(
    'select p.pid_idx, date(created) created, datediff(now(), created) elapsed ' .
    '  from projects p, votes v ' .
    " where p.pid_idx = v.pid_idx and v.token = '$token'"
);
if (mysql_num_rows($project_query) == 0)
    PAGEERROR("No project found");

$row = mysql_fetch_array($project_query);

?>

<center><h2>
    Requested <? echo $row['elapsed'] ?> day<? echo ($row['elapsed'] != 1 ? 's' : '') ?> ago
    (<? echo $row['created'] ?>)
</h2></center>

<?

# show the project table
$project = Project::Lookup($row['pid_idx']);
$project->Show($token);

# requery the db in case the vote above caused a change
$query_result = DBQueryFatal(
    "SELECT u.usr_name, u.uid_idx, v.vote, v.comment " .
    "  FROM users u, votes v " .
    " WHERE u.uid_idx = v.uid_idx AND v.pid_idx = $pid_idx"
);

?>
<form action="voteproject.php" method="POST">
<input type="hidden" name="token" value="<? echo $token ?>" />
<table style="margin: auto;">
<tr><th>Name</th><th>Vote</th><th>Comment</th></tr>
<?php

$total = 0;
$yes = 0;
$no = 0;

while ($row = mysql_fetch_array($query_result)) {
    # tally the votes
    ++$total;
    if (!is_null($row["vote"])) {
        if ($row["vote"]) ++$yes;
        else ++$no;
    }

    echo "<tr><td>" . $row["usr_name"] . "</td>";

    # display the radio button if we match the user
    if ($row["uid_idx"] == $uid) {
        echo '<td><input type="radio" name="vote" id="yes" value="yes"';
        if ($row["vote"] == 1)
            echo ' checked="checked"';
        echo ' /><label for="yes">Yes</label>';

        echo '<input type="radio" name="vote" id="no" value="no"';
        if (!is_null($row["vote"]) && $row["vote"] == 0)
            echo ' checked="checked"';
        echo ' /><label for="no">No</label>';

        echo '<input type="radio" name="vote" id="abstain" value="abstain"';
        if (is_null($row['vote']) && !is_null($row['comment'])) # hack: once they've voted comment becomes non-null
            echo ' checked="checked"';
        echo ' /><label for="abstain">Abstain</label></td>';

        echo '<td><input type="text" name="comment" value="' . $row['comment'] . '" /></td>';
    }

    # otherwise just show the vote
    else {
        echo '<td>';
        if ($row["vote"] == 1)
            echo 'yes';
        else if (!is_null($row["vote"]))
            echo 'no';
        else if (!is_null($row["comment"])) # hack: once they've voted comment becomes non-null
            echo 'abstain';
        else
            echo 'no vote';
        echo '</td>';

        echo '<td>' . $row['comment'] . '</td>';
    }

    echo "</tr>\n";
}
?>

</table>

<div style="text-align: center; margin-top: 0.5em">
    <input type="submit" value="Cast vote" />
</div>
</form>

<div style="text-align: center; margin-top: 1em">
    Current results:<br />
    Yes: <? printf('%.01f%% (%d/%d)', 100 * $yes / $total, $yes, $total) ?><br />
    No: <? printf('%.01f%% (%d/%d)', 100 * $no / $total, $no, $total) ?>
</div>

<?php

if ($deter_exec->AccessCheck($this_user, $TB_PROJECT_ADDUSER)) {

?>
<div style="text-align: center; margin-top: 1em">
    <a href="approveproject_form.php?pid=<? echo $pid ?>">Project approval form (requires login)</a>
</div>
<?

}

PAGEFOOTER();
