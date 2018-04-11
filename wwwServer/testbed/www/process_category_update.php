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

$optargs = OptionalPageArguments("submitbutton", PAGEARG_STRING,
                                 "formfields", PAGEARG_ARRAY);

#
# Standard Testbed Header
#
PAGEHEADER("Update DETER Project Categories and Institutions");

# collect all the updates
if (!isset($formfields) || !isset($formfields['total']))
    USERERROR("Missing data");
$total = $formfields['total'];

$updates = array();
for ($i = 0; $i < $total; ++$i) {
    $pid = $formfields["pid$i"];
    $inst = $formfields["inst$i"];
    $acad = $formfields["acad$i"];
    $type = $formfields["type$i"];

    $updates[$pid] = array(
        'inst' => $inst,
        'acad' => $acad,
        'type' => $type,
    );
}

$result = DBQueryFatal(
    'select p.pid, p.why, p.org_type stat_type, ' .
    '       ifnull(p.research_type, "NULL") type, ' .
    '       ifnull(u.usr_affil, "NULL") inst ' .
    '  from projects p, users u ' .
    ' where u.uid=p.head_uid and ' .
    '       (p.research_type != "Internal" or p.research_type is NULL)'
);

while ($row = mysql_fetch_array($result)) {
    $pid = $row['pid'];

    # skip this guy if we have no updates
    if (!isset($updates[$pid]))
        continue;

    $new = $updates[$pid];

    # acad is stat_type
    # the stat type will never be NULL since it's set at project creation
    # therefore we can update it without treating NULL specially
    if ($row['stat_type'] != $new['acad']) {
        $newacad = addslashes($new['acad']);
        $qstr = 
            "update projects " .
            "   set org_type = '$newacad' " .
            "  where pid = '$pid' ";
        print "$qstr<BR>\n";
        DBQueryFatal($qstr);
    }

    # research type and institution can be NULL
    # we handle NULL by using the magic string 'NULL'
    # to keep the values NULL in the db, we should only quote non-NULL values
    # this is an ugly hack :(
    if ($row['type'] != $new['type'] || $row['inst'] != $new['inst']) {
        $quoted_type = $new['type'];
        if ($new['type'] != 'NULL')
            $quoted_type = "'" . addslashes($new['type']) . "'";

        $quoted_inst = $new['inst'];
        if ($new['inst'] != 'NULL')
            $quoted_inst = "'" . addslashes($new['inst']) . "'";

        $qstr =
            "update projects as p, users as u " .
            "   set p.research_type = $quoted_type, " .
            "       u.usr_affil = $quoted_inst " .
            " where p.pid = '$pid' and u.uid=p.head_uid ";

        print "$qstr\n";

        DBQueryFatal($qstr);	
    }
}

#
# Standard Testbed Footer
# 
PAGEFOOTER();
?>
