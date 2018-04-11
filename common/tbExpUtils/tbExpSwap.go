/********************************************************************************
#
#    <"testbedGS" - Runtime structures and modular distributed component
#      architecture providing infrastructure and platform to build testbeds>
#
#    Copyright (C) <2018>  <Goran Scuric, goran@usa.net, igismo.com>
#
#    GNU GENERAL PUBLIC LICENSE ... Version 3, 29 June 2007
#
#    This program is free software: you can redistribute it and/or modify
#    it under the terms of the GNU General Public License as published by
#    the Free Software Foundation, either version 3 of the License, or
#    (at your option) any later version.
#
#    This program is distributed in the hope that it will be useful,
#    but WITHOUT ANY WARRANTY; without even the implied warranty of
#    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#    GNU General Public License for more details.
#
#    You should have received a copy of the GNU General Public License
#    along with this program.  If not, see <http://www.gnu.org/licenses/>.
#*********************************************************************************/
// NAME              REV  DATE       REMARKS			@
// Goran Scuric      1.0  01012018  Initial design     goran@usa.net
//================================================================================
package tbExpUtils

import (
	"database/sql"
	"testbedGS/common/tbDbaseUtils"
	"time"
)

//====================================================================================
//# Swapinfo accounting stuff.
//====================================================================================
func SwapSetSwapInfo(db *sql.DB, exp *ExperimentType) error {

	//pid := exp.HASH["EXPT"]["pid"].(string)
	//eid := exp.HASH["EXPT"]["eid"].(string)

	swapSetSwapTime(db, exp)
	swapSetSwapper(db, exp)
	return ExpRefresh(db, exp)
}

//====================================================================================
//# Just the swap time.
//====================================================================================
func swapSetSwapTime(db *sql.DB, exp * ExperimentType) error {

	idx := exp.EXPT["idx"].(string)
	rsrcidx := exp.EXPT["rsrcidx"].(string)


	_,_, err :=tbDbaseUtils.DBtransaction("swapSetSwapTime",db,"update experiments set expt_swapped='"+
		time.Now().String() + "' where idx='" + idx + "'")
	if err != nil {
		return err
	}

	if exp.EXPT["swapin_time"] != "" {

		_,_, err :=tbDbaseUtils.DBtransaction("swapSetSwapTime",db,"update experiment_resources " +
		"set swapout_time=UNIX_TIMESTAMP(now()) where idx='" + rsrcidx + "'")
		if err != nil {
			return err
		}
	}
	return nil
}

//====================================================================================
//# Just the swap uid.
//====================================================================================
func  swapSetSwapper(db *sql.DB, exp * ExperimentType) error {

	pid  := exp.EXPT["pid"].(string)
	eid  := exp.EXPT["eid"].(string)
	dbid := exp.EXPT["swapper_idx"].(string)
	uid  := exp.EXPT["expt_swap_uid"].(string)


	_,_, err :=tbDbaseUtils.DBtransaction("swapSetSwapper",db,"update experiments set " +
			"   expt_swap_uid='" + uid + "', swapper_idx='" + dbid + "' " +
			"where pid='" + pid + "' and eid='" + eid + "'")
	if err != nil {
		return err
	}

	return ExpRefresh(db, exp)
}
//====================================================================================
//# Finalize bookkeeping for a swap operation.
//====================================================================================
func SwapPostSwap(db *sql.DB, exp *ExperimentType, swapper, which, flags string) bool {
/*
my ($self, $swapper, $which, $flags) = @_;

# Must be a real reference.
return -1
if (! ref($self));

$flags = 0
if (!defined($flags));

my $exptidx  = $self->idx();
my $rsrcidx  = $self->rsrcidx();
my $lastrsrc = $self->lastrsrc();

# Old swap gathering stuff.
$self->GatherSwapStats($swapper, $which, 0) == 0
or return -1;

#
# On a swapout/modify complete, update the duration counters. We
# want to update the aggregates too below, so get the numbers we
# need for that first. Modify is a bit of a complication since we
# want to charge for the experiment as it *was* until this point,
# since the number of nodes has changed.
#
my $pnodes       = 0;
my $vnodes       = 0;
my $duration     = 0;
my $prev_uid_idx = 0;
my $prev_swapper = $swapper;
my $query_result;

#
# Need to update the previous record with the swapmod_time.
#
if ($which eq $EXPT_SWAPMOD) {
my $when = "UNIX_TIMESTAMP(now())";
# unless its active, in which case pick up swapin time.
$when = $self->swapin_time()
if ($self->state() eq libdb::EXPTSTATE_ACTIVE());

DBQueryWarn("update experiment_resources set ".
"  swapmod_time=$when ".
"where idx='$lastrsrc'")
or return -1;
}


if ($which eq $EXPT_SWAPOUT ||
($which eq $EXPT_SWAPMOD &&
$self->state() eq libdb::EXPTSTATE_ACTIVE())) {

#
# If this is a swapout, we use the current resource record. If this
# is a swapmod, we have to back to the previous resource record,
# since the current one reflects usage for the new swap.
#
if ($which eq $EXPT_SWAPOUT) {
$query_result =
DBQueryWarn("select r.pnodes,r.vnodes,r.uid_idx, ".
"  r.swapout_time - r.swapin_time ".
" from experiment_resources as r ".
"where r.idx='$rsrcidx'");
}
else {
$query_result =
DBQueryWarn("select r.pnodes,r.vnodes,r.uid_idx, ".
"  r.swapmod_time - r.swapin_time ".
" from experiment_resources as r ".
"where r.idx='$lastrsrc'");
}
return -1
if (!$query_result);

if ($query_result->numrows) {
($pnodes,$vnodes,$prev_uid_idx,$duration) =
$query_result->fetchrow_array;
# Might happen if swapin stats got losts.
$duration = 0
if (! defined($duration) || $duration < 0);

$prev_swapper = User->Lookup($prev_uid_idx);
$prev_swapper = $swapper
if (!defined($prev_swapper));
}
}

# Special case for initial record. Needs to be fixed.
if ($which eq $EXPT_SWAPIN && !$self->lastidx()) {
DBQueryWarn("update experiment_resources set byswapin=1 ".
"where idx='$rsrcidx'")
or return -1;
}

#
# Increment idleswap indicator, but only valid on swapout. Harmless
# if this fails, so do not worry about it.
#
if ($which eq $EXPT_SWAPOUT &&
$flags & libdb::TBDB_STATS_FLAGS_IDLESWAP()) {
DBQueryWarn("update experiment_stats ".
"set idle_swaps=idle_swaps+1 ".
"where exptidx=$exptidx");
}

#
# On successful swapin, get the number of pnodes. assign_wrapper
# has filled in everything else, but until the experiment actually
# succeeds in swapping, do not set the pnode count. The intent
# is to avoid counting experiments that ultimately fail as taking
# up physical resources.
#
if ($which eq $EXPT_START ||
$which eq $EXPT_SWAPIN ||
($which eq $EXPT_SWAPMOD &&
$self->state() eq libdb::EXPTSTATE_ACTIVE())) {
$query_result =
DBQueryWarn("select r.node_id,n.type,r.erole,r.vname, ".
"    n.phys_nodeid,nt.isremotenode,nt.isvirtnode ".
"  from reserved as r ".
"left join nodes as n on r.node_id=n.node_id ".
"left join node_types as nt on nt.type=n.type ".
"where r.exptidx='$exptidx' and ".
"      (n.role='testnode' or n.role='virtnode')");

return -1
if (! $query_result);

# Count up the unique *local* pnodes.
my %pnodemap = ();
# Generate the pmapping insert.
my @mappings = ();

while (my ($node_id,$type,$erole,$vname,$physnode,$isrem,$isvirt) =
$query_result->fetchrow_array()) {
push(@mappings,
"($rsrcidx, '$vname', '$physnode', '$type', '$erole')");

# We want just local physical nodes in this counter.
$pnodemap{$physnode} = $physnode
if (! ($isrem || $isvirt));
}
if (@mappings) {
DBQueryWarn("insert into experiment_pmapping values ".
join(",", @mappings))
or return -1;
}
$pnodes = scalar(keys(%pnodemap));

DBQueryWarn("update experiment_resources set pnodes=$pnodes ".
"where idx=$rsrcidx")
or return -1;
}

#
# Per project/group/user aggregates. These can now be recalculated,
# so if this fails, do not worry about it.
#
if ($which eq $EXPT_PRELOAD ||
$which eq $EXPT_START ||
$which eq $EXPT_SWAPOUT ||
$which eq $EXPT_SWAPIN ||
$which eq $EXPT_SWAPMOD) {
$self->GetProject()->UpdateStats($which, $duration, $pnodes, $vnodes);
$self->GetGroup()->UpdateStats($which, $duration, $pnodes, $vnodes);
if ($which eq $EXPT_SWAPOUT ||
$which eq $EXPT_SWAPMOD) {
$prev_swapper->UpdateStats($which, $duration, $pnodes, $vnodes);
}
else {
$swapper->UpdateStats($which, 0, 0, 0);
}


#
# Update the per-experiment record.
# Note that we map start into swapin.
#
if ($which eq $EXPT_SWAPOUT ||
$which eq $EXPT_SWAPIN ||
$which eq $EXPT_START ||
$which eq $EXPT_SWAPMOD) {
my $tmp = $which;
if ($which eq $EXPT_START) {
$tmp = $EXPT_SWAPIN;
}
DBQueryWarn("update experiment_stats ".
"set ${tmp}_count=${tmp}_count+1, ".
"    ${tmp}_last=now(), ".
"    last_activity=${tmp}_last, ".
"    swapin_duration=swapin_duration+${duration}, ".
"    swap_exitcode=0, ".
"    last_error=NULL ".
"where exptidx=$exptidx");
}

# Batch mode info.
if ($which eq $EXPT_SWAPIN || $which eq $EXPT_START) {
my $batchmode = $self->batchmode();

DBQueryWarn("update experiment_resources set ".
"    batchmode=$batchmode ".
"where idx=$rsrcidx");
}
}

#
# This last step clears lastrsrc, which is how we know that the record
# is consistent and that we can do another swap operation on it.
#
DBQueryWarn("update experiment_stats set lastrsrc=NULL ".
"where exptidx=$exptidx");

$self->Refresh();
*/
return true
}


