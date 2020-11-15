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
package main

import (
	"testbedGS/common/tbExpUtils"
	"fmt"
	"testbedGS/common/tbDbaseUtils"
	"database/sql"
	"log"
	"os"
	"os/exec"
	"time"
)


func expPreRun(db *sql.DB, exp *tbExpUtils.ExperimentType, filename string,
					xmlfile, zeeopt  string) bool {

	pid   := exp.EXPT["pid"].(string)
	eid   := exp.EXPT["eid"].(string)
	state := exp.EXPT["state"].(string)
	gid   := exp.EXPT["gid"].(string)

	fmt.Println("expPreRun: tbprerun started file=", filename, "  xmlfile=", xmlfile, " Z=",zeeopt)

	//# Kill old virtual state.
	expRemoveVirtualState(db, exp)

	//# This setups virt_nodes,virt_names, all IP address calculation and tb-* handling.
	fmt.Println("expPreRun: START parser ... at ", time.Now())

	// system("parse-ns $xmlfile $zeeopt $pid $gid $eid $nsfile")
	err := parseNS(db, exp, filename, true)
	if err != nil {
		fmt.Println("expPreRun: Running parser ... FAILED \n")
		return false
	}
	fmt.Println("expPreRun: DONE parser ... at ", time.Now())

	//# Until link agent runs on linux.
	events_result, err := tbDbaseUtils.DBselectQuery("", db,
		"select ev.pid,ev.eid,vl.vnode,vl.vname,vn.osname,o.OS " +
		"  from eventlist as ev " +
		"left join event_objecttypes as ev_ob on " +
		"  ev.objecttype=ev_ob.idx " +
		"left join virt_lans as vl on vl.vname=ev.vname and " +
		"  vl.pid=ev.pid and vl.eid=ev.eid " +
		"left join virt_nodes as vn on vn.pid=ev.pid and " +
		"  vn.eid=ev.eid and vn.vname=vl.vnode " +
		" left join os_info as o on o.osname=vn.osname and" +
		"  (o.pid=ev.pid or o.pid='emulab-ops') " +
		"left join experiments as e on e.pid=ev.pid and " +
		"  e.eid=ev.eid " +
		"where ev.pid='" + pid + "' and ev.eid='" + eid + "' and " +
		"  (vl.uselinkdelay!=0 or e.uselinkdelays!=0 or " +
		"   e.forcelinkdelays!=0) and ev_ob.type='LINK' and " +
		"  (o.os is NULL or o.os='Linux' or o.os='Fedora')");
	if err != nil {
		if events_result != nil {events_result.Close()}
	}
	if events_result != nil  && events_result.Next() {
		fmt.Println("expPreRun: Oops, cannot send static events to linkdelay agents on Linux!")
		return false
	}
	//=-=-=-=-=-=-=-=--=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	// Put the nsfile into the DB, now that we know it parses okay.
	if tbExpUtils.ExpSetNSFile(db, exp, filename) == false {
		fmt.Println("expPreRun: Error storing the NS file into the database!")
		return false
	}

	//=-=-=-=-=-=-=-=--=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	//#
	//# In update mode, do not start the renderer until later. If update fails we
	//# want to try to restore old render info rather then rerunning.
	if (state == EXPTSTATE_PRERUN) {
		fmt.Println("expPreRun: TODO prerender started in background")
		fmt.Println("expPreRun: Precomputing visualization ...")
		// TODO system("prerender -t $pid $eid");
	}
	//=-=-=-=-=-=-=-=--=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	//# See if using the new ipassign. #
	fmt.Println("expPreRun: use_ipassign ? ...")
	if exp.EXPT["use_ipassign"] != 0 {
		// TODO finish this
		ipassign_args := exp.EXPT["ipassign_args"]

		fmt.Println("expPreRun: TODO ipassign_wrapper started with", ipassign_args);
		fmt.Println("expPreRun: TODO Doing IP assignment ...")

		//if (system("ipassign_wrapper " + ipassign_args + " " + pid + " " + eid)) {
		//	fmt.Println("ipassign_wrapper failed!");
		//}
	}
	//=-=-=-=-=-=-=-=--=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	//# Fire up the route calculator.
	fmt.Println("expPreRun: TODO Setting up static routes (if requested) ... \n")
	//if (system("staticroutes " + pid + " " + eid)) {
	//	fmt.Println("FATAL: Static route calculation failed!")
	//}

	//=-=-=-=-=-=-=-=--=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	//# Generate a topo map that is used by the remote nodes to create the
	//# routes (ddijk) and the /etc/hosts file.
	fmt.Println("expPreRun: TODO Generating topomap ...\n")
	//if (system("gentopofile " + pid + " " + eid)) {
	//	fmt.Println("FATAL:gentopofile failed!")
	//}

	//## GORAN: Do this only if .ns file is supplied
	//## do not use with .xml specifications
	// TODO add meaningfull return for retry
	if xmlfile != "" {
		fmt.Println("expPreRun: Verifying parse verify-ns")
		if verifyNS(pid, gid, eid, filename) != 0 {
			return false
		}
	}
	//=-=-=-=-=-=-=-=--=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
	fmt.Println("expPreRun: Initialize environment strings variables ...\n")
	if tbExpUtils.ExpInitializeEnvVariables(db, exp) != 0 {
		fatal(db, exp, "expPreRun: Could not initialize environment strings variables");
		return false
	}
	fmt.Println("expPreRun: Writing environment strings ...\n")
	if tbExpUtils.ExpWriteEnvVariables(db, exp) != 0 {
		fatal(db, exp, "expPreRun: Could not write environment strings for program agents")
		return false
	}
	fmt.Println("expPreRun: Setting up additional program agent support ...\n")
	if expSetupProgramAgents(db, exp) != 0 {
		fatal(db, exp,  "expPreRun: Could not setup program agent support")
		return false
	}
	fmt.Println( "Setting up additional network agent support ...\n")
	if expSetupNetworkAgents(db, exp) != 0 {
		fatal(db, exp, "expPreRun: Could not setup network agent support")
		return false
	}
	fmt.Println("Writing program agent info ...\n")
	if expWriteProgramAgents(db, exp) != 0 {
		fatal(db, exp, "expPreRun: Could not write program agent info")
		return false
	}
	fmt.Println("expPreRun: Pre run finished at " + time.Now().String() + "\n")
	return true
}
// -----  END ------
func runSystemCmd(action string, arg[] string ) {
	cmd := exec.Command(action, arg ...)
	cmd.Stdout = os.Stdout
	cmd.Stderr = os.Stderr
	log.Println(cmd.Run())
}

//-=-=-=-=-=-=-==-==-=-=-=-=-=-=-=-=-=-=-=
//# Fatal error.
func fatal(db *sql.DB, exp *tbExpUtils.ExperimentType, text string)  {
	fmt.Print("expPreRun: Cleaning up after errors.\n")
	if (exp.EXPT["state"] == EXPTSTATE_PRERUN) {
		fmt.Println("Killing the renderer.\n")
	}
	fmt.Print("expPreRun: Removing experiment state.\n")
	expRemoveVirtualState(db, exp)
}
//====================================================================================
// Write the virt program data for the program agent that will run on ops.
// Ops does not speak to tmcd for experiments, so need to get this info
// over another way.
//====================================================================================
func expWriteProgramAgents(db *sql.DB, exp *tbExpUtils.ExperimentType) int {

	pid   	:= exp.EXPT["pid"].(string)
	eid   	:= exp.EXPT["eid"].(string)
	userdir	:= exp.EXPT["path"].(string)

	rows, err := tbDbaseUtils.DBselectQuery("expWriteProgramAgents",db,
		"select vname,command,dir,timeout,expected_exit_code "+
		" from virt_programs where vnode='ops' and pid='" +pid+"' and eid='" +eid+"'")
	defer rows.Close()
	if err != nil || rows == nil{
		return -1
	}

	progfile := userdir + "/tbdata/program_agents"
	fmt.Println("Writing Program Agents to " + progfile)
	file1, err := os.OpenFile(progfile, os.O_RDWR|os.O_CREATE, 0666)
	if err != nil {
		fmt.Println("Could not open " + progfile + " for writing: $!\n")
		return -1
	}

	for rows.Next() {
		var name, command, dir, timeout, expected_exit_code string
		rows.Scan(&name, &command, &dir, &timeout, &expected_exit_code)
		file1.WriteString("AGENT=" + name + "\n")
		if dir != "" {
			file1.WriteString("DIR=" + dir + "\n")
		}
		if timeout != "" {
			file1.WriteString("TIMEOUT=" + timeout + "\n")
		}
		if expected_exit_code != "" {
			file1.WriteString("EXPECTED_EXIT_CODE=" + expected_exit_code + "\n")
		}
		file1.WriteString("COMMAND=" + command + "\n")
	}

	file1.Close()
	return 0;
}
//====================================================================================
// Setup up phony program agent event agents and groups. This is so we
// can talk to the program agent itself, not to the programs the agent
// is responsible for.
//====================================================================================
func expSetupProgramAgents(db *sql.DB, exp *tbExpUtils.ExperimentType) int {

	pid   	:= exp.EXPT["pid"].(string)
	eid   	:= exp.EXPT["eid"].(string)
	idx   	:= exp.EXPT["idx"].(string)

	rows, err := tbDbaseUtils.DBselectQuery("expSetupProgramAgents",db,
		"select distinct vnode from virt_programs "+
		"where pid='" + pid + "' and eid='" + eid + "'")
	if err != nil || rows == nil{
		if rows !=nil {rows.Close()}
		return -1
	}

	vnode := ""

	for rows.Next() {
		rows.Scan(&vnode)
		_,_,err := tbDbaseUtils.DBtransaction("expSetupProgramAgents",db,"replace into virt_agents " +
			" (exptidx, pid, eid, vname, vnode, objecttype) " +
			" select '" + idx + "', '" + pid + "', '" + eid + "', " +
			"   '__" + vnode + "_program-agent', '" + vnode + "', " +
			"   idx from event_objecttypes where " +
			"   event_objecttypes.type='PROGRAM'")
		if err != nil {
			if rows !=nil {rows.Close()}
			return -1
		}

		_,_,err = tbDbaseUtils.DBtransaction("expSetupProgramAgents",db,"replace into event_groups " +
			" (exptidx, pid, eid, idx, group_name, agent_name) " +
			" values ('" + idx + "', '" + pid + "', '" + eid + "', NULL, " +
			" '__all_program-agents', '__" + vnode + "_program-agent')")
		if err != nil {
			if rows !=nil {rows.Close()}
			return -1
		}
	}
	if rows !=nil {rows.Close()}
	return 0;
}
//====================================================================================
// Seed the virt_agents table.  Each lan/link needs an agent to handle
// changes to delays or other link parameters, and that agent (might be
// several) will be running on more than one node. Delay node agent,
// wireless agent, etc. They might be running on a node different then
// where the link is really (delay node). So, just send all link event
// to all nodes, and let them figure out what they should do (what to
// ignore, what to act on). So, specify a wildcard; a "*" for the vnode
// will be treated specially by the event scheduler, and no ipaddr will
// be inserted into the event. Second, add pseudo agents, one for each
// member of the link (or just one if a lan). The objname is lan-vnode,
// and allows us to send an event to just the agent controlling that
// link (or lan node delay). The agents will subscribe to these
// additional names when they start up.
//====================================================================================
func expSetupNetworkAgents(db *sql.DB, exp *tbExpUtils.ExperimentType) int {

/*

	pid := exp.Pid
	eid := exp.Eid
	idx := exp.Idx

	virtexp := VirtExpLookup(exp, "")

	if virtexp == nil {
		return -1
	}

	ethlans = ();

	lan_members := virtexp->Table("virt_lans")

	for _,member ($lan_members->Rows()) {
		vnode = $member->vnode();
		vlanname = $member->vname();

			_,_,err := tbDbaseUtils.DBtransaction(db,"insert into virt_agents ".
			" (exptidx, pid, eid, vname, vnode, objecttype) ".
			" select '" + idx + "', '" + pid + "', '" + eid + "', " +
			"   '" + vlanname + "-" + vnode + "', '*', " +
			"   idx from event_objecttypes where event_objecttypes.type='LINK'")

			_,_,err := tbDbaseUtils.DBtransaction(db,"insert into virt_agents ".
			" (exptidx, pid, eid, vname, vnode, objecttype) ".
			" select '" + idx + "', '" + pid + "', '" + eid + "', " +
			"   '" + vlanname + "-" + vnode + "-tracemon', '*', " +
			"   idx from event_objecttypes where event_objecttypes.type='LINKTRACE'")

			_,_,err := tbDbaseUtils.DBtransaction(db,"insert into event_groups ".
			" (exptidx, pid, eid, idx, group_name, agent_name) " +
			" values ('" +idx+ "', '" + pid + "', '" + eid + "', NULL, " +
			" '__all_tracemon', '" + vlanname + "-" + vnode + "-tracemon')")

			_,_,err := tbDbaseUtils.DBtransaction(db,"insert into event_groups " +
			" (exptidx, pid, eid, idx, group_name, agent_name) " +
			" values ('" + idx + "', '" + pid + "', '" + eid + "', NULL, " +
			" '" + vlanname + "-tracemon', '" + vlanname + "-" + vnode + "-tracemon')")

		//# I do not understand this.
		if ($member->protocol() ne "ipv4") $ethlans{$vlanname} = $vlanname
	}

	lans := $virtexp->Table("virt_lan_lans");
	foreach my $lan ($lans->Rows()) {
		vlanname = $lan->vname();

		DBQueryFatal("insert into virt_agents ".
			" (exptidx, pid, eid, vname, vnode, objecttype) ".
			" select '$idx', '$pid', '$eid', '$vlanname', '*', ".
			"   idx from event_objecttypes where ".
			"   event_objecttypes.type='LINK'");

		if (exists($ethlans{$vlanname})) {

			//# XXX there is no link (delay) agent running on plab nodes
			//# (i.e., protocol==ipv4) currently, so we cannot be sending them
			//# events that they will not acknowledge.
			DBQueryFatal("insert into event_groups ".
				" (exptidx, pid, eid, idx, group_name, agent_name) ".
				" values ('$idx', '$pid', '$eid', ".
				"         NULL, '__all_lans', '$vlanname')");
		}
	}
*/
	return 0;
}
//====================================================================================
// # Grab the virtual topo for an experiment.
//====================================================================================

/*
import "sort"
m := make(map[string]string)
///////
var m map[int]string
var keys []int
for k := range m {
    keys = append(keys, k)
}
sort.Ints(keys)
for _, k := range keys {
    fmt.Println("Key:", k, "Value:", m[k])
}
//////
type Key struct {
    Path, Country string
}
hits := make(map[Key]int)
hits[Key{"/", "vn"}]++
n := hits[Key{"/ref/spec", "ch"}]
 */

func verifyNS(pid,gid,eid,nsfile string) int {

/*
	my $cmdargs = "$TB/libexec/ns2ir/parse.proxy ";
	$cmdargs .= " -u $user_uid -v -- $pid $gid $eid";

	# create the output file as the user for quota purposes
	unlink $outfile;
	open(FOO, ">$outfile") ||
		tbdie("Cannot create parser output file $outfile");
	close(FOO);

	$EUID = $UID = 0;
	system("sshtb -host $CONTROL $cmdargs < $nsfile > $outfile");
	$EUID = $UID = $SAVEUID;

	if ($?) {
	my $exit_status = $? >> 8;

	tbdie("Verify parsing failed (error code $exit_status)!");
	}

	open(NSLTMAP, "sort $outfile |");
	open(DBLTMAP, "sort ltmap |");

	my $done = 0;

	sub fcmp($$$) {
	my ($v1, $v2, $tol) = @_;

	return (abs($v1 - $v2) < $tol);
	}
	while (!$done) {
	my $nsline = <NSLTMAP>;
	my $dbline = <DBLTMAP>;

	if (!$nsline && !$dbline) {
	$done = 1;
	}
	elsif (!$nsline || !$dbline) {
	tbdie("Topology verification failed (short file)!");
	}
	elsif ($nsline =~ /^l \S+ \S+ \d+ \d+\.\d+ \d+\.\d+ \S+ \S+$/ &&
	$dbline =~ /^l \S+ \S+ \d+ \d+\.\d+ \d+\.\d+ \S+ \S+$/) {
	my @nsmatches;
	my @dbmatches;

	@nsmatches = ($nsline =~
	/^l (\S+) (\S+) (\d+) (\d+\.\d+) (\d+\.\d+) (\S+) (\S+)$/);
	@dbmatches = ($dbline =~
	/^l (\S+) (\S+) (\d+) (\d+\.\d+) (\d+\.\d+) (\S+) (\S+)$/);
	for (my $lpc = 0; $lpc < 7; $lpc++) {
	if ($lpc == 3 &&
	fcmp($nsmatches[$lpc], $dbmatches[$lpc], 0.0003)) {
	}
	elsif ($lpc == 4 &&
	fcmp($nsmatches[$lpc], $dbmatches[$lpc], 0.000010)) {
	}
	elsif ($nsmatches[$lpc] ne $dbmatches[$lpc]) {
	print "'$nsmatches[$lpc]' != '$dbmatches[$lpc]' in\n";
	print "ns: $nsline";
	print "db: $dbline";
	tbdie("Topology verification failed!");
	}
	}
	}
	elsif ($nsline ne $dbline) {
	chomp $nsline;
	chomp $dbline;
	tbdie("Topology verification failed ('$nsline'!='$dbline')!");
	}
	}

	exit(0);
*/
	return 0
}


func expRemoveVirtualState(db *sql.DB, exp *tbExpUtils.ExperimentType) int {

	pid   := exp.EXPT["pid"].(string)
	eid   := exp.EXPT["eid"].(string)
	errors := 0;

	for table := range VirtualTablesPrimaryKeyMap {
		if table == "external_sourcefiles" {continue}
		if table == "experiments" {continue}

		tbDbaseUtils.DBtransaction("expRemoveVirtualState",db,
			"DELETE FROM " + table + " WHERE pid='"+pid+"' AND eid='"+eid+"'")
		errors++;
	}
	return errors;
}