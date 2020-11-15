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
//=============================================================================
// FILE NAME: expParseNS.go
// DESCRIPTION:
// This serves to interpret the NS file and create the appropriate entries in
// virt_nodes and virt_lans tables.  After this script ends successfully the
// NS file is no longer necessary.
//
// NAME              REV  DATE       REMARKS			@
// Goran Scuric      1.0  01012018  Initial design     goran@usa.net
//================================================================================
package main
import (
	"fmt"
	"os"
	"testbedGS/common/tbDbaseUtils"
	"database/sql"
	"log"
	"strconv"
	"strings"
	"testbedGS/common/tbExpUtils"
	"testbedGS/common/tbConfiguration"
	"errors"
)

func ExperimentPath(pid,eid string) string {
	return "/proj/" + pid + "/exp/" + eid
}

func RemoveVirtualState(dbConnection *sql.DB, pid, eid string) int {
 	errors := 0

	for table, _ := range virtual_tables {
		if table == "external_sourcefiles" {continue}
		if table == "experiments" {continue}

		_,_, err := tbDbaseUtils.DBtransaction("RemoveVirtualState",dbConnection,
			"DELETE FROM " + table + " WHERE pid='" +
			pid + "' AND eid='" + eid + "'")
		if err != nil {
			fmt.Println("ERROR while RemoveVirtualState for", table)
			errors++;
		}
	}

	return errors
}

// set the working path (equivalent to BOSS /usr/testbed/exp* and /proj/xyz/exp/xxx
//====================================================================================
// Options are: Gfze
// f = force
// G = xml file, not ns
// e = eid == experiment id, mandatory
// z =
// THIS IS REALY THE PARSE-NS FUNCTION. BY THIS TIME THE BATCHEXP SHOULD HAVE CALLED
// THE experiment->Create AND CREATED THE EXPERIMENT RECORD
// THIS SHOULD BE PASSED IN SO ALL THE FIELDS ARE AVAILABLE (INSTEAD OF PID,EID,...)
//====================================================================================
// # Create a new experiment. This installs the new record in the DB,
// and returns an instance. There is some bookkeeping along the way.
// from batchexp  Create(class, group, eid, argref)
//====================================================================================
func parseNS(dbConnection *sql.DB, exp *tbExpUtils.ExperimentType, nsfile string, xmlType bool ) error {

	pid     := exp.EXPT["pid"].(string)
	eid     := exp.EXPT["eid"].(string)
	//gid_idx := exp.EXPT["gid_idx"]
	//pid_idx := exp.EXPT["pid_idx"]
	//pid     := exp.EXPT["pid"]

	// Exp state should be EXPTSTATE_PRERUN
	// needs
	//	my state    = experiment->state();
	//	my gid      = experiment->gid();
	//	my exptidx  = experiment->idx();

	fmt.Println ("parseNS: parser starting nsfile=", nsfile, " xmlType=", xmlType)

	fmt.Println("parseNS: call RemoveVirtualState ")
	if RemoveVirtualState(dbConnection, pid, eid) != 0 { // cleanup everything first
		fmt.Println("parseNS: ERROR while doing RemoveVirtualState ")
		return errors.New("ERROR while doing RemoveVirtualState")
	}

	// # This setups virt_nodes,virt_names,all IP address calculation and tb-* handling.
	// #  CALL:  parse-ns xmlfile zeeopt pid gid eid nsfile DOES FOLLOWING:

	fmt.Println ("parseNS: GenDefsFile")
	GenDefsFile(dbConnection,exp,"parse.input");
	fmt.Println ("parseNS: BACK from GenDefsFile")

	if xmlType == false { // We supplied NS file
		fmt.Println("tbCreate: NS file supplied")
		// Now append the NS file to. This part is hokey. Fix later.
		//	system("cat nsfile >> parse.input")
		// p: passmode ?
		// CALL partse-ns: cmdargs = TB/libexec/ns2ir/parse.proxy -u <user> -- -q -p  pid gid eid
		// CALL sshtb -host users.deter.net cmdargs
		// my parser   = "TB/lib/ns2ir/parse.tcl";
		// my vlib     = "TB/lib/nsverify";
		// my vparser  = "TB/libexec/nsverify/nstbparse";

		// 	exec("TB/lib/ns2ir/parse.tcl  @ARGV nsfile")
		// EVALUATE parse.tcl ..... creates parse.output

		// Run the XML converter on the output.
		// TB/libexec/xmlconvert -p -x outfile pid eid"
	} else {
		fmt.Println("Starting xmlconvert on XML FILE " + nsfile + "-> parse.output ")

		err :=expXMLconvert(dbConnection, exp, nsfile)
		if err != nil {
			fmt.Println("parseNS: ERROR ERROR xmlconvert", err)
			return err
		}
		fmt.Println("parseNS: BACK from xmlconvert")
	}
	fmt.Println ("parseNS: tbCreateFixIPs")
	tbCreateFixIPs()
	fmt.Println ("parseNS: tbCreateFixTrufGens")
	tbCreateFixTrufGens()
	fmt.Println ("parseNS: tbCreateFixRiskyxp")
	tbCreateFixRiskyxp()

	fmt.Println ("parseNS:  parser finished")

	//-------------------------------------------------------------------
	// do this Until link agent runs on linux.
	//events_result :=
	//	DBQueryFatal("select ev.pid,ev.eid,vl.vnode,vl.vname,vn.osname,o.OS ".
	//		"  from eventlist as ev ".
	//		"left join event_objecttypes as ev_ob on ".
	//		"  ev.objecttype=ev_ob.idx ".
	//		"left join virt_lans as vl on vl.vname=ev.vname and ".
	//		"  vl.pid=ev.pid and vl.eid=ev.eid ".
	//		"left join virt_nodes as vn on vn.pid=ev.pid and ".
	//		"  vn.eid=ev.eid and vn.vname=vl.vnode ".
	//		"left join os_info as o on o.osname=vn.osname and".
	//		"  (o.pid=ev.pid or o.pid='emulab-ops') ".
	//		"left join experiments as e on e.pid=ev.pid and ".
	//		"  e.eid=ev.eid ".
	//		"where ev.pid='pid' and ev.eid='eid' and ".
	//		"  (vl.uselinkdelay!=0 or e.uselinkdelays!=0 or ".
	//		"   e.forcelinkdelays!=0) and ev_ob.type='LINK' and ".
	//		"  (o.os is NULL or o.os='Linux' or o.os='Fedora')");
	// if (events_result->num_rows) {
	//	tbCreateCleanup("Oops, cannot send static events to linkdelay agents on Linux!");
	//	}

	//-------------------------------------------------------------------
    // Sharing mode on nodes
	//var query_result =
	//	DBQueryFatal("select sharing_mode from virt_nodes ".
	//		"where pid='pid' and eid='eid' and sharing_mode is not null");
	// if (query_result->numrows &&
	//	!(this_user->IsAdmin() || this_user->uid eq "elabman")) {
	//     tbCreateCleanup("Only testbed admininstrators can set the sharing mode on nodes");
	// }
	//-------------------------------------------------------------------
	// Put the nsfile into the DB, now that we know it parses okay.
	// experiment->SetNSFile(nsfile) == 0 or
	// tbCreateCleanup("Error storing the NS file into the database!");
	//-------------------------------------------------------------------
	// IP Assign
	// if (experiment->use_ipassign()) {
	// 	my ipassign_args  = experiment->ipassign_args();
	//  ipassign_wrapper ipassign_args pid eid
	// }
    //-------------------------------------------------------------------
	// Fire up the route calculator.
	// staticroutes pid eid
	//-------------------------------------------------------------------
	// # Generate a topo map that is used by the remote nodes to create the
	// routes (ddijk) and the /etc/hosts file.
	// gentopofile pid eid
    //-------------------------------------------------------------------
	// GORAN: Do this only if .ns file is supplied
	// do not use with .xml specifications
	if xmlType == false {
		fmt.Println ("Verify NS file")
		// if verify-ns(pid, gid, eid, nsfile) == false {
		// 		tbCreateCleanup("verify-ns failed!")
		// }
	}
    //-------------------------------------------------------------------
	// # Do an assign_prerun to set the min/max nodes. Generates a top file too.
	// This is the only DB state that is modified during a top only run.
	// vtopgen -p pid eid   ... tbCreateCleanup("assign prerun failed!")
	//-------------------------------------------------------------------
	// Set various stats and env
	//     my %sets = ();
	//if (experiment->elabinelab());
	//sets{"security_level"} = experiment->security_level()
	//if (experiment->security_level());
	//sets{"elabinelab_exptidx"} = experiment->elabinelab_exptidx()
	//if (defined(experiment->elabinelab_exptidx()));
    //
	//if (keys(%sets)) {
	//experiment->TableUpdate("experiment_stats", \%sets) == 0 or
	//tbCreateCleanup("Could not update experiment_stats info for experiment!");
	//}
	//-------------------------------------------------------------------
	// Setup env variables.
	// exp->InitializeEnvVariables()
	// experiment->WriteEnvVariables()
	// experiment->SetupProgramAgents()
	// experiment->SetupNetworkAgents()
	// experiment->WriteProgramAgents()

	fmt.Println("parseNS: Pre run finished. (create done)")
	return nil
}



//====================================================================================
//
//====================================================================================
func tbCreateCleanup(info string) {
	// do on fatal
	// experiment->RemoveVirtualState()
}
//====================================================================================
//
//====================================================================================
func tbCreateFixIPs() {
/*

#
# Now we have to fix up one minor thing; widearea tunnel IPs. These have
# to be unique, but without the DB to ask, there is no easy way to arrange
# that.

my %subnetmap = ();
my WANETMASK = "255.255.255.248";

my query_result =
    DBQueryFatal("select vname,ips from virt_nodes ".
                 "where pid='pid' and eid='eid'");
while (my (vname,ips) = query_result->fetchrow_array()) {
    my @newiplist = ();
    my newips;

    foreach my ipinfo (split(" ", ips)) {
        my (port,ip) = split(":", ipinfo);
        my (a,b,c,d) = (ip =~ /(\d+).(\d+).(\d+).(\d+)/);

        if (a eq "69" && b eq "69") {
            my net = inet_ntoa(inet_aton(WANETMASK) & inet_aton(ip));

            if (! defined(subnetmap{net})) {
                DBQueryFatal("insert into ipsubnets (exptidx,pid,eid,idx) ".
                             "values ('exptidx','pid','eid', NULL)");
                my (id) =
                    DBQueryFatal("select LAST_INSERT_ID() ".
                                 "from ipsubnets")->fetchrow_array();

                # We are going to shift the bits up so they do not conflict
                # with the lower part.
                if (id >= 8192) {
                    die("No more widearea subnets left!\n");
                }
                id = id << 3;

                my cc = (id & 0xff00) >> 8;
                my dd = (id & 0xf8);
                subnetmap{net} = inet_aton("192.168.cc.dd");
            }
            my newsubnet = inet_ntoa(subnetmap{net} | inet_aton("d"));
            push(@newiplist, "port:{newsubnet}");
        }
        else {
            push(@newiplist, ipinfo);
        }
    }
    newips = join(" ", @newiplist);

    if (newips ne ips) {
        DBQueryFatal("update virt_nodes set ips='newips' ".
                     "where vname='vname' and pid='pid' and eid='eid'");

        foreach my ipinfo (split(" ", newips)) {
            my (port,ip) = split(":", ipinfo);

            DBQueryFatal("update virt_lans set ip='ip' ".
                         "where vnode='vname' and vport='port' ".
                         "      and pid='pid' and eid='eid'");
        }
    }
}
 */
}
//====================================================================================
//
//====================================================================================
func tbCreateFixTrufGens() {
	/*
	#
	# So, if we ended up changing any, we have look for corresponding entries
	# in virt_trafgens, and fix them too.

	if (keys(%subnetmap)) {
    query_result =
        DBQueryFatal("select vnode,vname,ip,target_ip from virt_trafgens ".
                     "where pid='pid' and eid='eid'");
    while (my (vnode,vname,ip,dstip) = query_result->fetchrow_array()) {
        my (a,b,c,d) = (ip =~ /(\d+).(\d+).(\d+).(\d+)/);
        my newip        = ip;
        my newdstip     = dstip;
        my net          = inet_ntoa(inet_aton(WANETMASK) & inet_aton(ip));

        if (defined(subnetmap{net})) {
            newip = inet_ntoa(subnetmap{net} | inet_aton("d"));
        }
        (a,b,c,d) = (dstip =~ /(\d+).(\d+).(\d+).(\d+)/);
        net          = inet_ntoa(inet_aton(WANETMASK) & inet_aton(dstip));
        if (defined(subnetmap{net})) {
            newdstip = inet_ntoa(subnetmap{net} | inet_aton("d"));
        }
        if (ip ne newip || dstip ne newdstip) {
            DBQueryFatal("update virt_trafgens set ".
                         "       ip='newip',target_ip='newdstip' ".
                         "where pid='pid' and eid='eid' and ".
                         "      vnode='vnode' and vname='vname'");
        }
    }
	*/
}
//====================================================================================
//
//====================================================================================
func tbCreateFixRiskyxp() {
	/*
	# If we are using virt_parameters to initialize the risky experiment stuff,
	# call a script to patch the db

	query_result = DBQuery("select name from virt_parameters where pid='pid' ".
                   "and eid='eid' and name rlike '^portal_(nat|rdr)_'");
	if (query_result && (query_result->numrows))
    { system ("TB/libexec/cxa_setup vp2db pid eid"); }
	 */
}
//====================================================================================
// func GenDefsFile(fname string)
// Open up a TCL file and write a bunch of TCL to it! parse.input
//====================================================================================
/*
type nodeType struct {
	Class             string `json:"class"`
	Type              string `json:"type"`
	modelnetcore_osid string `json:"modelnetcore_osid"`
	modelnetedge_osid string `json:"modelnetedge_osid"`
	isvirtnode        int 	 `json:"isvirtnode"`
	ismodelnet        int 	 `json:"ismodelnet"`
	isjailed          int 	 `json:"isjailed"`
	isdynamic         int  	 `json:"isdynamic"`
	isremotenode      int  	 `json:"isremotenode"`
	issubnode         int  	 `json:"issubnode"`
	isplabdslice      int  	 `json:"isplabdslice"`
	isplabphysnode    int  	 `json:"isplabphysnode"`
	issimnode         int  	 `json:"issimnode"`
	isgeninode        int  	 `json:"isgeninode"`
	isfednode         int  	 `json:"isfednode"`
}
*/
func tbFileWrite(fileHandle *os.File, myText string) {
	_, err := fileHandle.WriteString(myText)

	if err != nil {
		log.Fatalf("GenDefsFile: failed writing to file: %s", myText)
	}
}

func GenDefsFile(db *sql.DB, exp *tbExpUtils.ExperimentType, filename string) {

	pid     := exp.EXPT["pid"].(string)
	eid     := exp.EXPT["eid"].(string)

	workdir := tbExpUtils.WorkDir(pid, eid)
	fname := workdir + "/" + filename
	fmt.Println("GenDefsFile: START GenDefFile fname=",fname)
	TCL, err := os.Create(fname) // set directory first ....
	if err != nil {
		fmt.Println("GenDefsFile: GenDefsFile: failed creating file:", fname, " ERR=",err)
		return
	}
	defer TCL.Close() // Make sure to close the file when you're done
	//--------------------------------------------

	tbFileWrite(TCL, "namespace eval TBCOMPAT {\n")
	expParseEventObjectTypes(db,TCL)

	expParseEventTypes(db, TCL)

	//--------------------------------------------
	tbFileWrite(TCL, "\n")
	tbFileWrite(TCL, "# Node Types and Classes\n")

	// NodeType->AllTypes()
	queryResult, err := tbDbaseUtils.DBselectQuery("GenDefsFile",db,
		"select type from node_types")
	if err != nil {
		queryResult.Close()
		fmt.Println("GenDefsFile: ERROR3=",err)
		return
	}
	// First get all types
	var typeArray =make(map[int]string)
	var numberOfTypes = 0
	for queryResult.Next() {
		var aType string
		queryResult.Scan(&aType)
		typeArray[numberOfTypes] = aType
		numberOfTypes++
	}
	if queryResult != nil {queryResult.Close()}
	fmt.Println("GenDefsFile: number oftypes=", numberOfTypes)
	//
	for _, thisType := range typeArray {
		fmt.Println("GenDefsFile: process type=", thisType)
		queryType, err := tbDbaseUtils.DBselectQuery("GenDefsFile",db,
			"select * from node_types where type='" + thisType + "'")
		if err != nil {
			if queryType != nil {queryType.Close()}
			fmt.Println("GenDefsFile: ERROR5=",err)
			return
		}
		for queryType.Next() {
			var Class string
			var Type string
			var modelnetcore_osid string
			var modelnetedge_osid string
			var isvirtnode int
			var ismodelnet int
			var isjailed int
			var isdynamic int
			var isremotenode int
			var issubnode int
			var isplabdslice int
			var isplabphysnode int
			var issimnode int
			var isgeninode int
			var isfednode int
			queryType.Scan(&Class, &Type, &modelnetcore_osid, &modelnetedge_osid,
				&isvirtnode, &ismodelnet, &isjailed, &isdynamic, &isremotenode, &issubnode,
				&isplabdslice, &isplabphysnode, &issimnode, &isgeninode, &isfednode)
			tbFileWrite(TCL, "set hwtypes("+Type+") 1\n")
			tbFileWrite(TCL, "set hwtype_class("+Type+") "+Class+"\n")
			tbFileWrite(TCL, "set isremote("+Type+") "+strconv.Itoa(isremotenode)+"\n")
			tbFileWrite(TCL, "set isvirt("+Type+") "+strconv.Itoa(isvirtnode)+"\n")
			tbFileWrite(TCL, "set issubnode("+Type+") "+strconv.Itoa(issubnode)+"\n")
			tbFileWrite(TCL, "set hwtypes("+Class+") 1\n")
			// Since there are multiple types per class, this is probably not the right thing to do.
			tbFileWrite(TCL, "set isremote("+Class+") "+strconv.Itoa(isremotenode)+"\n")
			tbFileWrite(TCL, "set isvirt("+Class+") "+strconv.Itoa(isvirtnode)+"\n")
			tbFileWrite(TCL, "set issubnode("+Class+") "+strconv.Itoa(issubnode)+"\n")

			// expParseOsId(db, TCL, Type) // TODO
		} // end of for queryType.Next()
		if queryType != nil {queryType.Close()}
		//--------------------------------------------
		fmt.Println("GenDefsFile: start the rest .....")

		// expParseNodeAuxTypes(db,TCL) // TODO

		expParseGlobalVtypes(db,TCL)

		expParseNodePermissions(db,TCL)

		expParseRobots(db,TCL)

		expParseObstacles(db,TCL)

		expParseCameras(db,TCL)

		expParseOsIds(db,TCL,pid,eid)

		expParseReservedNodes(db,TCL,pid,eid)

		expParsePhysicalNodeNames(db, TCL)

		expParseLocationInfo(db, TCL)

		expParseElabInElab(TCL,pid)

		//---------------------------------------------
		// For Templates.		// Does not matter what it is, as long as it is set.
		tbFileWrite(TCL, "# Template goo\n")
		tbFileWrite(TCL, "set ::DATASTORE \""+ tbConfig.TBDB_PROJDIR+"\"\n")
		datastore := ExperimentPath(pid, eid) + "/datastore"
		tbFileWrite(TCL, "set ::DATASTORE "+datastore+ "\n")

		BindingList(db, exp, TCL)
		RunBindingList(db, exp,TCL)

		tbFileWrite(TCL, "\n\n")
		tbFileWrite(TCL, "}\n")

	} // End for each node type
	TCL.Close()
}

//====================================================================================
//
//====================================================================================
func expParseEventObjectTypes(db *sql.DB, TCL *os.File) {
	fmt.Println("GenDefsFile: expParseEventObjectTypes")
	var myIdx int
	var myType string
	tbFileWrite(TCL, "# Event Object Types\n")

	queryResult1, err := tbDbaseUtils.DBselectQuery("GenDefsFile", db,
						"select idx,type from event_objecttypes")
	if err != nil {
		if queryResult1 != nil {queryResult1.Close()}
		fmt.Println("GenDefsFile: ERROR1=", err)
		return
	}
	for queryResult1.Next() {
		queryResult1.Scan(&myIdx, &myType)
		tbFileWrite(TCL, "set objtypes("+myType+") "+strconv.Itoa(myIdx)+"\n")
	}
	if queryResult1 != nil {queryResult1.Close()}
	return
}
//====================================================================================
//
//====================================================================================
func expParseEventTypes(db *sql.DB, TCL *os.File) {
	fmt.Println("GenDefsFile: expParseEventTypes")
	tbFileWrite(TCL, "\n")
	tbFileWrite(TCL, "# Event Event Types\n")
	var myIdx int
	var myType string

	queryResult1, err := tbDbaseUtils.DBselectQuery("GenDefsFile", db,
		"select idx,type from event_eventtypes")
	if err != nil {
		if queryResult1 != nil {queryResult1.Close()}
		fmt.Println("GenDefsFile: ERROR2=", err)
		return
	}
	for queryResult1.Next() {
		queryResult1.Scan(&myIdx, &myType)
		tbFileWrite(TCL, "set eventtypes("+myType+") "+strconv.Itoa(myIdx)+"\n")
	}
	if queryResult1 != nil {queryResult1.Close()}
}
//====================================================================================
//
//====================================================================================
func expParseOsId(db *sql.DB, TCL *os.File, Type string) {
	fmt.Println("GenDefsFile: expParseOsId")
	queryOsid, err := tbDbaseUtils.DBselectQuery("GenDefsFile", db,
		"select attrkey,attrvalue,attrtype "+
			"  from node_type_attributes where attrkey='default_osid' and type='"+ Type+ "'")
	if err != nil {
		if queryOsid != nil {queryOsid.Close()}
		fmt.Println("GenDefsFile: ERROR3=",err)
		return
	}
	var attrkey string
	var osid string
	var attrtype string
	var osname string
	queryOsid.Scan(&attrkey, &osid, &attrtype)
	if queryOsid != nil {queryOsid.Close()}

	if osid != "" {
		queryOsName, _ := tbDbaseUtils.DBselectQuery("GenDefsFile", db,
			"select osname from os_info where osid='"+osid+"'")
		if err != nil {
			if queryOsName != nil {queryOsName.Close()}
			fmt.Println("GenDefsFile: ERROR4=",err)
			return
		}
		queryOsName.Scan(&osname)

		if osname != "" {
			tbFileWrite(TCL, "set default_osids("+Type+") \""+osname+"\"\n")
		}
		if queryOsName != nil {queryOsName.Close()}
	}

}
//====================================================================================
//
//====================================================================================
func expParseNodeAuxTypes(db *sql.DB, TCL *os.File) {
	fmt.Println("GenDefsFile: expParseNodeAuxTypes")
	tbFileWrite(TCL, "\n")
	tbFileWrite(TCL, "# Node Aux Types\n")

	queryAux, err := tbDbaseUtils.DBselectQuery("GenDefsFile",db,
		"select at.auxtype,nt.type from node_types_auxtypes " +
			"as at left join node_types as nt on nt.type=at.type ")
	if err != nil {
		if queryAux != nil {queryAux.Close()}
		fmt.Println("GenDefsFile: ERROR5=",err)
		return
	}

	var typeArray =make(map[string]string)
	var numberOfTypes = 0
	var auxtype string
	var Type string
	for queryAux.Next() {
		queryAux.Scan(&auxtype, &Type)
		typeArray[auxtype] = Type
		numberOfTypes++
	}
	if queryAux != nil {queryAux.Close()}
	fmt.Println("GenDefsFile: number auxTypes=", numberOfTypes)

	//for queryAux.Next() {
	for auxtype, Type := range typeArray {
		//var auxtype string
		//var Type string
		// queryAux.Scan(&auxtype, &Type)
		fmt.Println("GenDefsFile: auxtype=", auxtype, "  Type=",Type)
		queryType, err := tbDbaseUtils.DBselectQuery("GenDefsFile",db,
							"select * from node_types where type='"+Type+"'")
		if err != nil {
			if queryType != nil {queryType.Close()}
			fmt.Println("GenDefsFile: ERROR6=",err)
			return
		}
		for queryType.Next() {
			// Generate a new type entry, but named by the auxtype instead.
			//Underlying data is shared; might need to change that.
			var Class string
			var Type string
			var modelnetcore_osid string
			var modelnetedge_osid string
			var isvirtnode int
			var ismodelnet int
			var isjailed int
			var isdynamic int
			var isremotenode int
			var issubnode int
			var isplabdslice int
			var isplabphysnode int
			var issimnode int
			var isgeninode int
			var isfednode int
			queryType.Scan(&Class, &Type, &modelnetcore_osid, &modelnetedge_osid,
					&isvirtnode, &ismodelnet, &isjailed, &isdynamic, &isremotenode, &issubnode,
					&isplabdslice, &isplabphysnode, &issimnode, &isgeninode, &isfednode)

			tbFileWrite(TCL, "set hwtypes("+Type+") 1\n")
			//tbFileWrite(TCL,"set hwtype_class(" + Type + ") " + Class + "\n")
			tbFileWrite(TCL, "set isremote("+Type+") "+strconv.Itoa(isremotenode)+"\n")
			tbFileWrite(TCL, "set isvirt("+Type+") "+strconv.Itoa(isvirtnode)+"\n")
			tbFileWrite(TCL, "set issubnode("+Type+") "+strconv.Itoa(issubnode)+"\n")
			//tbFileWrite(TCL,"set hwtypes(" + Class + ") 1\n")

			queryOsid, _ := tbDbaseUtils.DBselectQuery("GenDefsFile",db,
							"select attrkey,attrvalue,attrtype from node_type_attributes " +
								" where attrkey='default_osid' and type='"+ Type +"'")
			if err != nil {
				if queryOsid != nil {queryOsid.Close()}
				fmt.Println("GenDefsFile: ERROR7=",err)
				return
			}
			var attrkey string
			var osid string
			var attrtype string
			var osname string
			queryOsid.Scan(&attrkey, &osid, &attrtype)

			if osid != "" {
				queryOsName, _ := tbDbaseUtils.DBselectQuery("GenDefsFile",db,
									"select osname from os_info where osid='"+ osid+ "'")
				if err != nil {
					if queryOsName != nil {queryOsName.Close()}
					fmt.Println("GenDefsFile: ERROR8=",err)
					return
				}
				queryOsName.Scan(&osname)

				if osname != "" {
					tbFileWrite(TCL, "set default_osids("+Type+") \""+osname+"\"\n")
				}
				if queryOsName != nil {queryOsName.Close()}
			}
		} // for queryType.Next()

	} // for queryAux.Next()
}
//====================================================================================
//
//====================================================================================
func expParseGlobalVtypes(db *sql.DB, TCL *os.File) {
	fmt.Println("GenDefsFile: expParseGlobalVtypes")
	tbFileWrite(TCL, "\n")
	tbFileWrite(TCL, "# Global Vtypes\n")

	queryGlobal, err := tbDbaseUtils.DBselectQuery("GenDefsFile", db,
		"select vtype, weight, types from global_vtypes")
	if err != nil {
		if queryGlobal != nil {queryGlobal.Close()}
		fmt.Println("GenDefsFile: ERROR9=",err)
		return
	}
	for queryGlobal.Next() {
		var vtype string
		var weight string
		var types string
		queryGlobal.Scan(&vtype, &weight, &types)
		tbFileWrite(TCL, "set ::GLOBALS::vtypes(" + vtype + ") [Vtype " + vtype+
			" "+ weight+ " {"+ types+ "}]\n")
	}
	if queryGlobal != nil {queryGlobal.Close()}
}

//====================================================================================
//
//====================================================================================
func expParseNodePermissions(db *sql.DB, TCL *os.File) {
	fmt.Println("GenDefsFile: expParseNodePermissions")
	tbFileWrite(TCL, "\n")
	tbFileWrite(TCL, "# Node Permissions\n")

	rows, err := tbDbaseUtils.DBselectQuery("GenDefsFile", db,
						"select type,pid from nodetypeXpid_permissions")
	if err != nil {
		if rows != nil {rows.Close()}
		fmt.Println("GenDefsFile: ERROR10=",err)
		return
	}
	for rows.Next() {
		var Type string
		var pid string
		rows.Scan(&Type, &pid)
		// Not final: sort all pid against each type,
		// the output Type and that list against Type (not single pid)
		tbFileWrite(TCL, "set nodetypeXpid_permissions("+Type+") [list "+pid+"]\n")
	}
	if rows != nil {rows.Close()}
}
//====================================================================================
//
//====================================================================================
func expParseRobots(db *sql.DB, TCL *os.File) {
	fmt.Println("GenDefsFile: expParseRobots")
	tbFileWrite(TCL, "\n")
	tbFileWrite(TCL, "# Robot areas\n")

	rows, err := tbDbaseUtils.DBselectQuery("GenDefsFile", db,
							"select distinct building from node_startloc")
	if err != nil {
		if rows != nil {rows.Close()}
		fmt.Println("GenDefsFile: ERROR11=",err)
		return
	}
	for rows.Next() {
		var building string
		rows.Scan(&building)
		tbFileWrite(TCL, "set areas(" + building + ") 1\n")
	}
	if rows != nil {rows.Close()}
}
//====================================================================================
//
//====================================================================================
func expParseObstacles(db *sql.DB, TCL *os.File) {
	fmt.Println("GenDefsFile: expParseObstacles")
	tbFileWrite(TCL, "\n")
	tbFileWrite(TCL, "# Obstacles\n")

	queryObst, err := tbDbaseUtils.DBselectQuery("GenDefsFile", db,
		"select o.obstacle_id,o.building,o.x1,o.x2,o.y1,o.y2,o.description, "+
			" fi.pixels_per_meter from obstacles as o "+
			"left join floorimages as fi on fi.building=o.building")
	if err != nil {
		if queryObst != nil {queryObst.Close()}
		fmt.Println("GenDefsFile: ERROR12=",err)
		return
	}
	for queryObst.Next() {
		var id string
		var building string
		var x1 string
		var x2 string
		var y1 string
		var y2 string
		var description string
		var ppm string
		queryObst.Scan(&id, &building, &x1, &x2, &y1, &y2, &description, &ppm)
		tbFileWrite(TCL, "set obstacles("+id+","+building+","+x1+") "+x1+"/"+ppm+"\n")
		tbFileWrite(TCL, "set obstacles("+id+","+building+","+x2+") "+x2+"/"+ppm+"\n")
		tbFileWrite(TCL, "set obstacles("+id+","+building+","+y1+") "+y1+"/"+ppm+"\n")
		tbFileWrite(TCL, "set obstacles("+id+","+building+","+y2+") "+y2+"/"+ppm+"\n")
		tbFileWrite(TCL, "set obstacles("+id+","+building+",description) {"+description+"}\n")
	}
	if queryObst != nil {queryObst.Close()}
}
//====================================================================================
//
//====================================================================================
func expParseCameras(db *sql.DB, TCL *os.File) {
	fmt.Println("GenDefsFile: expParseCameras")
	tbFileWrite(TCL, "\n")
	tbFileWrite(TCL, "# Cameras\n")

	rows, err := tbDbaseUtils.DBselectQuery("GenDefsFile", db,
				"select name,building,loc_x,loc_y,width,height from cameras")
	if err != nil {
		if rows != nil {rows.Close()}
		fmt.Println("GenDefsFile: ERROR13=",err)
		return
	}
	for rows.Next() {
		var name string
		var building string
		var loc_x string
		var loc_y string
		var width string
		var height string
			rows.Scan(&name, &building, &loc_x, &loc_y, &width, &height)
		tbFileWrite(TCL, "set cameras("+name+","+building+",x) "+loc_x+"\n")
		tbFileWrite(TCL, "set cameras("+name+","+building+",y) "+loc_y+"\n")
		tbFileWrite(TCL, "set cameras("+name+","+building+",width) "+width+"\n")
		tbFileWrite(TCL, "set cameras("+name+","+building+",height) "+height+"\n")
	}

	tbFileWrite(TCL, "\n")
	if rows != nil {rows.Close()}
}
//====================================================================================
//
//====================================================================================
func expParseOsIds(db *sql.DB, TCL *os.File, pid,eid string) {
	fmt.Println("GenDefsFile: expParseOsIds")
	if pid != "" {
		tbFileWrite(TCL, "# OSIDs\n")
		queryOS, err := tbDbaseUtils.DBselectQuery("GenDefsFile", db,
			"select osname from os_info where shared=1 or pid='"+pid+"'")
		if err != nil {
			if queryOS != nil {queryOS.Close()}
			fmt.Println("GenDefsFile: ERROR14=",err)
			return
		}
		for queryOS.Next() {
			var osname string
			queryOS.Scan(&osname)
			tbFileWrite(TCL, "set osids(" + osname + ") 1\n")
		}
		if queryOS != nil {queryOS.Close()}
		tbFileWrite(TCL, "\n")
		tbFileWrite(TCL, "# subOSIDs and parent OSIDs (default parent first element)\n")
/*
		SELECT ID, GROUP_CONCAT(NAME ORDER BY NAME ASC SEPARATOR ',')
		FROM (
			SELECT ID, CONCAT(NAME, ':', GROUP_CONCAT(VALUE ORDER BY VALUE ASC SEPARATOR ',')) AS NAME
		FROM test
		GROUP BY ID, NAME
		) AS A
		GROUP BY ID;

		SELECT ID, GROUP_CONCAT(CONCAT_WS(':', NAME, VALUE) SEPARATOR ',') AS Result
		FROM test GROUP BY ID
*/

		fromString := " from os_submap as osm left join os_info as oi on osm.osid=oi.osid"+
			" left join os_info as oi2 on osm.parent_osid=oi2.osid"+
			" left join os_info as oi3 on oi.def_parentosid=oi3.osid"
		/*	WAS
		rows, err := tbDbaseUtils.DBselectQuery("GenDefsFile", db,
			"select oi.osname,oi3.osname, group_concat(oi2.osname separator '\" \"')"+
				fromString + " where oi.def_parentosid is not NULL group by oi.osname")
		*/

		rows, err := tbDbaseUtils.DBselectQuery("GenDefsFile", db,
			"select oi.osname,oi3.osname, group_concat(oi2.osname separator '\" \"')"+
				fromString + " where oi.def_parentosid is not NULL group by oi.osname, oi3.osname")

		if err != nil {
			if rows != nil {rows.Close()}
			fmt.Println("GenDefsFile: ERROR15=",err)
			return
		}
		for rows.Next() {
			//while (my (osname,  def_parentosid, parent_osids) =
			//query_result->fetchrow_array())
			osname := ""
			def_parentosid := ""
			parent_osids := ""
			parentlist   := ""
			rows.Scan(&osname,  &def_parentosid, &parent_osids) //  TODO while ???
			{
				parentlist = def_parentosid
				if parent_osids != "" {
					parentlist += " " + parent_osids
				}
				tbFileWrite(TCL, "set subosids(" + osname + ") [list " + parentlist + "]\n")
			}
		}
		if rows != nil {rows.Close()}
		tbFileWrite(TCL, "\n")
	} // end of if pid != ""
}
//====================================================================================
//
//====================================================================================
func expParseReservedNodes(db *sql.DB, TCL *os.File, pid,eid string) {
	fmt.Println("GenDefsFile: expParseReservedNodes")
	if pid != "" {
		// Load reserved nodes, for swapmodify.
		rows, err := tbDbaseUtils.DBselectQuery("GenDefsFile",db,
						"select r.vname,r.node_id,n.type from reserved as r "+
						"left join nodes as n on n.node_id=r.node_id "+
						"where r.pid='"+ pid+ "' and r.eid='"+ eid+ "'")
		if err != nil {
			if rows != nil {rows.Close()}
			fmt.Println("GenDefsFile: ERROR16=",err)
			return
		}

		tbFileWrite(TCL, "# Reserved Nodes\n")
		for rows.Next() {
			var vname string
			var nodeid string
			var Type string
			rows.Scan(&vname, &nodeid, &Type)
			tbFileWrite(TCL, "lappend reserved_list \""+vname+"\"\n")
			tbFileWrite(TCL, "set reserved_type(" + vname + ") " + Type + "\n")
			tbFileWrite(TCL, "set reserved_node(" + vname + ") reserved\n")
		}
		if rows != nil {rows.Close()}
	}

}
//====================================================================================
//
//====================================================================================
func expParsePhysicalNodeNames(db *sql.DB, TCL *os.File) {
	fmt.Println("GenDefsFile: expParsePhysicalNodeNames")
	tbFileWrite(TCL, "# Physical Node Names\n")
	rows, err := tbDbaseUtils.DBselectQuery("GenDefsFile", db,
		"select n.node_id,nt.type from nodes as n "+
			"left join node_types as nt on n.type=nt.type "+
			"where n.node_id=n.phys_nodeid and nt.isremotenode=0 "+
			"and n.role='testnode' and nt.type!='dnard'")
	if err != nil {
		if rows != nil {rows.Close()}
		fmt.Println("GenDefsFile: ERROR17=",err)
		return
	}
	for rows.Next() {
		var node_id string
		var Type string
		rows.Scan(&node_id, &Type)
		tbFileWrite(TCL, "set physnodes(" + node_id + ") "+ Type + "\n")
	}
	if rows != nil {rows.Close()}
}
//====================================================================================
//
//====================================================================================
func expParseLocationInfo(db *sql.DB, TCL *os.File) {
	fmt.Println("GenDefsFile: expParseLocationInfo")
	tbFileWrite(TCL, "# Location info\n")
	queryBuild, err := tbDbaseUtils.DBselectQuery("GenDefsFile", db,
		"select li.node_id,li.building,li.loc_x,li.loc_y,"+
			"li.loc_z,fi.pixels_per_meter "+
			"from location_info as li "+
			"left join floorimages as fi on fi.building=li.building")
	if err != nil {
		if queryBuild != nil {queryBuild.Close()}
		fmt.Println("GenDefsFile: ERROR18=",err)
		return
	}
	for queryBuild.Next() {
		var node_id string
		var building string
		var loc_x, loc_y, loc_z, ppm float64
		queryBuild.Scan(&node_id, &building, &loc_x, &loc_y, &loc_z, &ppm)
		tbFileWrite(TCL, "set location_info(" + node_id + "," + building + ",x) "+
			strconv.FormatFloat(loc_x/ppm, 'f', 6, 64)+ "\n")
		tbFileWrite(TCL, "set location_info(" + node_id + "," + building + ",y) "+
			strconv.FormatFloat(loc_y/ppm, 'f', 6, 64)+ "\n")
		if strconv.FormatFloat(loc_z, 'f', 6, 64) == "" {
			loc_z = 0.0;
		}
		tbFileWrite(TCL, "set location_info(" + node_id + "," + building + ",z) "+
			strconv.FormatFloat(loc_z/ppm, 'f', 6, 64)+ "\n")
	}
	if queryBuild != nil {queryBuild.Close()}
}
//====================================================================================
//
//====================================================================================
func expParseElabInElab(TCL *os.File, pid string) {
	fmt.Println("GenDefsFile: expParseElabInElab")
	// ElabInElab stuff.
	maxnodes := 0
	if pid != "" {
		/*
	elabinelab := 0
	elabinelab_eid := ""

	TBExptIsElabInElab(pid, eid, \elabinelab, \elabinelab_eid)
		or tbdie("Failed to get ElabInElab attributes!");

	if elabinelab != 0 && elabinelab_eid != "" {
		if (! TBExptMinMaxNodes(pid, elabinelab_eid, undef, \maxnodes)) {
			tbdie("Could not get max nodes from DB!");
		}
	}
	*/
	}
	// Be sure to initialize this to something ...
	tbFileWrite(TCL, "set elabinelab_maxpcs "+strconv.Itoa(maxnodes)+"\n\n")
}
//====================================================================================
//
//====================================================================================
// TODO Get list of bindings for the current run.
func RunBindingList(dbConnection *sql.DB, exp *tbExpUtils.ExperimentType, TCL *os.File) {
	fmt.Println("GenDefsFile: RunBindingList")
	exptidx := exp.EXPT["idx"].(string) //exptidx();
	ridx  := exp.TEMPLATE["runidx"]  //self->runidx();
	if ridx== nil {return}

	runidx := ridx.(string)
	if (runidx == "") {
		// This happens when called during initial swapin.
		// TODO %prval = %results;
		return
	}

	query,err := tbDbaseUtils.DBselectQuery("RunBindingList",dbConnection,
		"select name,value from experiment_run_bindings " +
			"where exptidx='" + exptidx + "' and runidx='" + runidx + "'")
	if err != nil {
		if query != nil {query.Close()}
		fmt.Println("GenDefsFile: ERROR19=",err)
		return
	}
	for query.Next() {
		var name string
		var value string
		query.Scan(&name, &value)
		tbFileWrite(TCL, "set parameter_list_defaults(" + name + ") " + value + "\n")
	}
	if query != nil {query.Close()}
}
//====================================================================================
//  TODO Get list of bindings for the instance (the values at swapin time).
//  TODO where do guid, vers and idx come from ??
//====================================================================================
func BindingList(db *sql.DB, exp *tbExpUtils.ExperimentType, TCL *os.File) {

	fmt.Println("GenDefsFile: BindingList")
	groupuid := exp.TEMPLATE["guid"] // self->guid();
	if groupuid == nil {return}

	guid := groupuid.(string)
	vers := exp.TEMPLATE["vers"].(string) // self->vers();
	idx  := exp.EXPT["idx"].(string)  // self->idx()
	query,err := tbDbaseUtils.DBselectQuery("BindingList",db,
		"select name,value from experiment_template_instance_bindings " +
		"where instance_idx='" + idx + "' and parent_guid='" + guid + "' and " +
		" parent_vers='" + vers + "'")
	if err != nil {
		if query != nil {query.Close()}
		fmt.Println("GenDefsFile: ERROR20=",err)
		return
	}
	for query.Next() {
		var name string
		var value string
		query.Scan(&name, &value)
		tbFileWrite(TCL, "set parameter_list_defaults(" + name + ") " + value + "\n")
	}
	if query != nil {query.Close()}
}
//====================================================================================
//
//====================================================================================
func parse_error(mesg string) {
	if parse_invalid_os_error(mesg) == true {
		return
	}
	//if parse_invalid_variable_error(mesg) == true {
	//	return }
	return
}
//====================================================================================
//
//====================================================================================
func parse_invalid_os_error(mesg string) bool {

	if strings.Contains(mesg, "Invalid osid") == false {
		return false
	}

	// tbreport(SEV_ADDITIONAL, {script => 'parse.tcl'}, 'invalid_os', type, osname, undef);

	return true;
}
//====================================================================================
//
//====================================================================================
func parse_invalid_variable_error(mesg string) bool {
	var typeString = ""
	var varString  = ""

	if strings.Contains(mesg, "Invalid hardware type") == true {
		varString  = "hardware";
		typeString = "hardware_type"
	} else if strings.Contains(mesg, "Invalid lan") == true {
		varString  = "lan";
		typeString = "lan_link_name";
	} else if strings.Contains(mesg, "Invalid node name") {
		varString  = "node";
		typeString = "node_name";
	} else {
		return false;
	}
	fmt.Println("PARSE ERROR var=",varString,"type=",typeString)
	// tbreport(SEV_ADDITIONAL, {script => 'parse.tcl'}, 'invalid_variable', type, var);

	return true;
}

