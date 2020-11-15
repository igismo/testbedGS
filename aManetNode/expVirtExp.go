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
package main // expVirtExp.go
import (
	"database/sql"
	"testbedGS/common/tbExpUtils"
	"fmt"
	"testbedGS/common/tbDbaseUtils"
	"strconv"
	"strings"
)

//# Store() flags.
var STORE_FLAGS_DEBUG      = 0x01
var STORE_FLAGS_IMPOTENT   = 0x02
var STORE_FLAGS_SIMPARSE   = 0x04
var LOOKUP_FLAGS_NOLOAD    = 0x01

var debug                  =  0

//====================================================================================
//# The experiment table: Only certain fields are allowed to be updated.
//====================================================================================

var ExperimentFields   =  map[string] interface{} {
	"multiplex_factor" 		: 0,	// int
	"forcelinkdelays" 		: 0,	// int
	"uselinkdelays" 		: 0,	// int
	"usewatunnels" 			: 0,	// int
	"uselatestwadata" 		: 0,	// int
	"wa_delay_solverweight" : 0.0,	// float
	"wa_bw_solverweight" 	: 0.0,	// float
	"wa_plr_solverweight" 	: 0.0,	// float
	"cpu_usage" 			: 0,	// int
	"mem_usage" 			: 0,	// int
	"allowfixnode" 			: 0,	// int
	"encap_style" 			: "default",//enum('alias','veth','veth-ne','vlan','vtun','egre','gre','default')
	"jail_osname" 			: "",	// string
	"delay_osname" 			: "",	// string
	"sync_server" 			: "",	// string
	"use_ipassign" 			: 0,	// int
	"ipassign_args" 		: "",	// string
	"usemodelnet" 			: 0,	// int
	"modelnet_cores" 		: 0,	// intinsigned
	"modelnet_edges"		: 0,	//  int unsigned
	"elab_in_elab" 			: 0,	// int
	"elabinelab_eid" 		: "",	//string
	"elabinelab_cvstag"		: "",	//string
	"elabinelab_singlenet"	: 0,	// int
	"security_level" 		: 0,	// int
	"delay_capacity" 		: 0,	// int
	"dpdb" 					: 0,	// int
}


//====================================================================================
//# Grab the virtual topo for an experiment.
// VirtualTablesPrimaryKeyMap  = map[string][]string is defined in the expVirtTable.go
// and contains list of tables with their primary keys
//====================================================================================
func VirtExpLookup(db *sql.DB,
	exp *tbExpUtils.ExperimentType) *map[string] tbExpUtils.VirtualTableType {
	// We already have the exp structure ...
	// This really just initializes virtual tables
	// so (virtExpType structures are added to exp.VirtTables hash

	// Initialize all virtual tables
	vtables := make(map[string]tbExpUtils.VirtualTableType)
	exp.VirtTables = vtables

	for  tablename,_ := range VirtualTablesPrimaryKeyMap {  // key,value
	fmt.Println("VirtExpLookup: Load Virt table=",tablename)
		table := VirtTableCreate(db, exp, tablename) // VirtualTableType
		VirtTableLoad(db,exp,tablename) // TODO checkthat we are loading something where
		// store reference in the hash table
		exp.VirtTables[tablename] = *table
	}
	return & exp.VirtTables
}

//====================================================================================
//# To avoid wrtting out all the methods.
//====================================================================================
func  VirtExpAUTOLOAD(exp *tbExpUtils.ExperimentType, name string) interface{} {
	if val, ok := ExperimentFields[name]; ok {
		return val
	} else {
		return val    /// TODO get it from DB ???
	}
}
//====================================================================================
//OK DESTROY not used
//====================================================================================
func VirtExpDESTROY() {
}

//====================================================================================
//# Create a new experiment virtual topology. This means loading the
//# experiment, but not any of the virt tables. We never create a new
//# "experiment" via this path, just a new virt topology for an existing
//# experiment. TODO correct this text ...it is wrong
// vi really return exp.VirtTables
//====================================================================================
func VirtExpCreateNew(db *sql.DB,
	exp *tbExpUtils.ExperimentType) *map[string] tbExpUtils.VirtualTableType{

	return VirtExpLookup(db, exp)

}

//====================================================================================
//OK -- Add a new empty table row. Caller must populate it.
//====================================================================================
func VirtExpNewTableRow(db *sql.DB, exp *tbExpUtils.ExperimentType, tablename string,
			argref map[string]interface{}) map[string]interface{} {
	fmt.Println("VirtExpNewTableRow: table=", tablename)
	_, present :=VirtualTablesPrimaryKeyMap[tablename]
	if present == false {
		fmt.Println("VirtExpNewTableRow: table not available " + tablename)
		return nil
	}

	row   := VirtTableNewRow(db, exp, tablename, argref)

	if row == nil {
		fmt.Println("Could not create new table row in " + tablename)
		return nil
	}
	return row
}

//====================================================================================
//# Store the experiment back to the DB. Includes the experiment table itself.
//====================================================================================
func VirtExpStore(db *sql.DB, exp *tbExpUtils.ExperimentType, flags int) bool {

	pid   := exp.EXPT["pid"].(string)
	eid   := exp.EXPT["eid"].(string)
	exptidx   := exp.EXPT["idx"].(string)

	//# Delete anything we are not allowed to set via this interface.

	//# Get the default values for the required fields.
	rows, err := tbDbaseUtils.DBselectQuery("VirtExpStore",db, "describe experiments")
	if err != nil {
		if rows != nil {rows.Close()}
		return false
	}
	var Field,Type,Null,Key,Default,Extra string

	for rows.Next() {
		rows.Scan(&Field,&Type,&Null,&Key,&Default,&Extra)
		slot  := Field
		value := Default

		//# Insert the default values for slots that we do not have
		//# so that we can set them properly in the DB query.
		if _, ok := ExperimentFields[slot]; ok {
			ExperimentFields[slot] = value
		}
	}
	if rows != nil {rows.Close()}
	//----
	var setlist  []string

	for key,val := range ExperimentFields {

		//# Always skip these; they come from the experiment object. Prevents
		//# users from messing up the DB with a bogus XML file.
		if key == "pid" || key == "eid" || key == "idx" {
			continue
		}

		if val == "NULL" || val == "__NULL__" || val == "" {
			setlist = append(setlist, key+"=NULL")
		} else {
			//# Sanity check the fields.
			if true {
				// (TBcheck_dbslot($val, "experiments", $key,
				//	TBDB_CHECKDBSLOT_WARN|TBDB_CHECKDBSLOT_ERROR)) {
				//val = DBQuoteSpecial($val);
				setlist = append(setlist, key + "=" +val.(string));
			} else {
				fmt.Println("Illegal characters in table data: experiments:" +
					key + "- " + val.(string));
				return false
			}
		}
	}

	query := "update experiments set " + strings.Join(setlist, ",") +
				" where idx='" + exptidx + "'"

	fmt.Println( "QUERY: $query\n")
	_,_,err1 := tbDbaseUtils.DBtransaction("VirtExpStore",db, query)
	if err1 != nil {
		return false
	}

	//# And then the virt table rows. First need to delete them all.
	//# Need this below:
	query1 := "select idx from event_objecttypes where type='NSE'"
	rows, err2 := tbDbaseUtils.DBselectQuery("VirtExpStore",db, query1)
	if err2 != nil {
		if rows != nil {rows.Close()}
		return false
	}
	var nse_objtype int64
	for rows.Next() {
		rows.Scan(&nse_objtype)
	}
	if rows != nil {rows.Close()}

	for tablename,_ := range VirtualTablesPrimaryKeyMap {
		fmt.Println("VirtExpStore table: " + tablename)
		var simparse = false
		if simparse {

			//# The nseconfigs table is special. During a simparse,
			//# we need delete all rows for the experiment except
			//# the one with the vname 'fullsim'. This row is
			//# essentially virtual info and does not change across
			//# swapins where as the other rows depend on the mapping
			if tablename == "nseconfigs" {
				query6 := "delete from " + tablename + " where eid='" +
					eid + "' and pid='" + pid + "' and vname!='fullsim'"
				_,_,err3 := tbDbaseUtils.DBtransaction("VirtExpStore",db, query6)
				if err3 != nil {
					fmt.Println("ERROR::::" + query6 )
				}
			} else if tablename == "eventlist" || tablename == "virt_agents" {
				//# Both eventlist and virt_agents need to be
				//# cleared for NSE event objecttype since entries
				//# in this table depend on the particular mapping
				query4 := "delete from " +
					tablename + " where pid='" + pid + "' and eid='" + eid +
					"' and objecttype='" + strconv.FormatInt(nse_objtype,10) + "'"
				_,_,err3 := tbDbaseUtils.DBtransaction("VirtExpStore",db, query4)
				if err3 != nil {
					fmt.Println("ERROR:::" + query4 )
				}
			}
		} else {
			//# In normal mode all rows deleted. During the nse parse,
			//# leave the other tables alone.
			query5 := "delete from " + tablename + " " +
				"where eid='" + eid + "' and pid='" + pid + "'"
			_,_,err3 := tbDbaseUtils.DBtransaction("VirtExpStore",db, query5)
			if err3 != nil {
				fmt.Println("ERROR :: " + query5)
			}
		}
	}

	for tablename,_ := range VirtualTablesPrimaryKeyMap {
			if VirtTableStore(db,exp,tablename) == false {
				fmt.Println("Could not store table $table")
				return  false
			}
	}
	return true
}

//====================================================================================
//# Find a particular row in a table.
//====================================================================================
func VirtExpFind(exp *tbExpUtils.ExperimentType, tablename string,
						args []string) map[string]interface{} {

	_,present := exp.VirtTables[tablename]
	if present == false {
		fmt.Println("VirtExpFind: No entry in VirtualTablesPrimaryKeyMap for " +tablename)
		 var empty map[string]interface{}
		return empty
	}

	return VirtTableFindRow(exp, tablename, args)
}
//====================================================================================
//# Return a table.
//====================================================================================
func VirtExpTable(exp *tbExpUtils.ExperimentType,
				tablename string) tbExpUtils.VirtualTableType {
	fmt.Println("VirtExpTable: get virt table ", tablename)
	value,present := exp.VirtTables[tablename]
	if present == false {
		fmt.Println("VirtExpTable: unknown table: " +tablename +
			"val=", value, "present=",present)
		var empty tbExpUtils.VirtualTableType
		return empty
	}

	return value
}

//====================================================================================
//# Flush from our little cache, as for the expire daemon.
//====================================================================================
func VirtExpFlush(exp *tbExpUtils.ExperimentType) {
	// delete($virtexperiments{$self->exptidx()})
}

//====================================================================================
//# Dump the contents of virt tables.
//====================================================================================
func VirtExpDump(exp *tbExpUtils.ExperimentType) {

	fmt.Println("VirtExpDump: \n")
	for key,val := range ExperimentFields  {
		vvv := fmt.Sprintf("%s", val)
		fmt.Println("  " + key+ " : " + vvv + "\n")
	}

	for tablename, value := range VirtualTablesPrimaryKeyMap {  // key,value
		val ,present := exp.VirtTables[tablename]
		if present == false {
			fmt.Println("VirtExpDump: --> Unknown table: " +tablename, " val=",val," value=", value)
		} else {
			VirtTableTableRowsDump(exp, tablename)
		}
	}
}
//====================================================================================
//OK  Refresh a class instance by reloading from the DB.
//====================================================================================
func VirtExpRefresh() {
	return
}

//====================================================================================
//OK   Stringify for output.     VirtExpStringify
//====================================================================================
func VirtExpStringify(exp *tbExpUtils.ExperimentType) string {

	pid   := exp.EXPT["pid"].(string)
	eid   := exp.EXPT["eid"].(string)
	idx   := exp.EXPT["idx"].(string)

	return "[VirtExperiment: " + pid + "/" + eid + "/" +
		      idx + "]"
}





