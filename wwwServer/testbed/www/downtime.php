<?php
#
# DETER-COPYRIGHT
# Copyright (c) 2010 University of Southern California
# All rights reserved.
# All your base are belong to us.
#
include("defs.php");

PAGEHEADER("Scheduled Downtime");

?>

<p>
A weekly maintenance window is scheduled on the testbed. During this two hour period:
</p>

<ul>
    <li>You may not be able to access experimental nodes</li>
    <li>Swap ins, swap outs, and modifies may not succeed</li>
    <li>Other intermittent issues may occur</li>
</ul>

<p>
We do our best to ensure that experiments which are swapped in will be in the same
state before and after the downtime.
</p>

<ul>
    <li>Nodes will not be removed from experiments</li>
    <li>Data on experimental nodes will be accessible after the downtime</li>
</ul>

<p>
If you have any questions, please contact <a href="http://trac.deterlab.net/wiki/GettingHelp">Testbed Operations</a>.
</p>

<?

PAGEFOOTER();
