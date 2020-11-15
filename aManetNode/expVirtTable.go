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
package main //  expVirtTable.go

import (
	"testbedGS/common/tbExpUtils"
	"database/sql"
	"testbedGS/common/tbDbaseUtils"
	"strconv"
	"strings"
	"fmt"
)


//====================================================================================
//# Describe the virt tables and their primary keys.
//====================================================================================

var VirtualTablesPrimaryKeyMap  = map[string][]string {
	"virt_nodes":               {"vname"},
	"virt_lans":                {"vname", "vnode", "vport"},
	"virt_lan_lans":            {"vname"},
	"virt_lan_settings":        {"vname", "capkey"},
	"virt_lan_member_settings": {"vname", "member", "capkey"},
	"virt_trafgens":            {"vname", "vnode"},
	"virt_agents":              {"vname", "vname", "vnode"},
	"virt_node_desires":        {"vname", "desire"},
	"virt_node_startloc":       {"vname", "building"},
	"virt_routes":              {"vname", "src", "dst"},
	"virt_vtypes":              {"name"},
	"virt_programs":            {"vname", "vnode"},
	"virt_user_environment":    {"name", "value"},
	"nseconfigs":               {"vname"},
	"eventlist":                {"idx"},
	"event_groups":             {"group_name", "agent_name"},
	"virt_firewalls":           {"fwname", "type", "style"},
	"firewall_rules":           {"fwname", "ruleno", "rule"},
	"virt_tiptunnels":          {"host", "vnode"},
	"virt_parameters":          {"name", "value"},
}
//====================================================================================
//OK --  Create table   VirtTableCreate
// At this point the exp.VirtualTables is set to make(map[string]VirtualTableType)
//====================================================================================
func VirtTableCreate(db *sql.DB,exp *tbExpUtils.ExperimentType,
					tablename string)  *tbExpUtils.VirtualTableType {
	fmt.Println("VirtTableCreate: START tablename=", tablename)
	var table = tbExpUtils.VirtualTableType{}

	// create exp.VirtualTables[tablename].SLOTNAMES
	var mySlots = make(map[string]string)
	rows,err := tbDbaseUtils.DBselectQuery("VirtTableCreate",db, "describe " + tablename)
	if err != nil {
		fmt.Println("VirtTableCreate: ERROR reding table ", tablename)
		return nil // TODO needs much better error handling
	}
	//# Record the default values for slots.
	var xfieldslot,xtype,xnull,xkey,xdefaultvalue string
	for rows.Next() {
		rows.Scan(&xfieldslot,&xtype,&xnull,&xkey,&xdefaultvalue)
		mySlots[xfieldslot] = xdefaultvalue
	}
	//
	slots := make(map[string]string)
	table.SLOTNAMES = slots
	for val,key := range mySlots {
		if _, ok := mySlots[key]; ok {
			table.SLOTNAMES[key] = val
		}
	}
	//
	tablerows := make(map[string]interface{})
	table.TABLEROW = tablerows
	//
	myhash := make(map[string]map[string]interface{})
	table.TABLEHASH = myhash
	//
	table.TableName  = tablename
	table.Counter    = 1
	exp.VirtTables[tablename] = table

	return &table
}
//====================================================================================
//TODO  Destroy Table/Experiment/...
//====================================================================================
func VirtTableDESTROY(exp *tbExpUtils.ExperimentType,
	tablename string) {
/*
$self->{'TABLENAME'}  = undef;
$self->{'SLOTNAMES'}  = undef;
$self->{'TABLEHASH'}  = undef;
$self->{'TABLELIST'}  = undef;
*/
}

//====================================================================================
// TODO -- Load Virtual Table
//====================================================================================
func VirtTableLoad(db *sql.DB, exp *tbExpUtils.ExperimentType, tablename string) {

	exptidx    := exp.EXPT["idx"].(string)
	fmt.Println("VirtTableLoad: table=",tablename, "  exptidx=", exptidx)
	rows,err := tbDbaseUtils.DBselectQuery("VirtTableLoad",db,"select * from " +tablename+
					" where exptidx=" + exptidx)
	if err != nil {
		if rows != nil {rows.Close()}
		fmt.Println("VirtTableLoad: ERROR reading table ", tablename)
		return
	}

	// This need much more work to pack fields from rows.Scan()
	// into argref. First get "show columns from table, then
	// repeated packing key/value pairs into argreg ?????
	// var rowMap = make(map[string]interface{})
	for rows.Next() {
		rowMap := tbExpUtils.UnpackRowsIntoMap(tablename,rows)
		VirtTableNewRow(db,exp,tablename, rowMap)
	}
	if rows != nil {rows.Close()}
}
//====================================================================================
// Create a new Virtual table row.
//====================================================================================
type VirtTableRowFunc func(string)

func VirtTableNewRow(db *sql.DB,exp *tbExpUtils.ExperimentType,tablename string,
					myRow map[string]interface{})  map[string] interface{} {
	fmt.Println("VirtTableNewRow: table=",tablename)
	var vtable tbExpUtils.VirtualTableType // self
	vtable = exp.VirtTables[tablename] // points to our VirtualTableType

	// class := "VirtExperiment::VirtTableRow::$tablename";
	// var obj *tbExpUtils.VirtTableRowType
	// var obj map[string]string
	// after all will be already in table.TABLEROW[keys]
	// tableRow is pointer to exp.VirtTables[tablename].TABLEROW
	tableRow   := VirtTableRowCreate(db,exp,tablename,myRow)

	//# These are the required keys, they must be defined for the new row
	//# to make any sense. Other slots can be filled in later of course.
	pkeys := VirtualTablesPrimaryKeyMap[tablename] // array of strings

	var pvals []string
	for  _,key :=  range pkeys {
		value,present := myRow[key]
		if present == false {
			if tablename == "eventlist" && key == "idx" {
				myRow[key] = strconv.Itoa(vtable.Counter)
				vtable.Counter += 1
			} else {
				fmt.Println("VirtTableNewRow: Missing table key for new table in $tablename")
				return nil
			}
		}
		pvals = append(pvals, value.(string)) // add value
	}

	//# This is the full key. Make sure it does not already exist.
	akey := strings.Join(pvals, ":")
	fmt.Println("VirtTableNewRow: full key=", akey)
	_,present := vtable.TABLEHASH[akey]
	if present == true {
		fmt.Println("Already have entry for '" + akey + "' in " + vtable.TableName)
		return nil
	}

	// GORAN: TODO What the heck is this all about
	//========================================
	// in perl it looks like a function call ????
	//for value,key := range argref {
		// $obj->$key($argref->{$key})
		//var funcPtr VirtTableRowFunc = obj["TABLEROW"][key]
		//funcPtr(value)
	//}

	//# Add to list of rows for this table.
	vtable.TABLELIST = append(vtable.TABLELIST, tableRow)

	//# And to the hash array using the pkey.

	vtable.TABLEHASH[akey] = tableRow // TODO change of ty to map map ??

	return tableRow
}

//====================================================================================
//OK -- Dump out table.
//====================================================================================
func  VirtTableTableRowsDump(exp *tbExpUtils.ExperimentType, tablename string) {

	table := exp.VirtTables[tablename]
	fmt.Println("VirtTable TableRows Dump\n")
	if len(table.TABLELIST) == 0 {
		fmt.Println("   empty")
		return
	}

	for _,row := range table.TABLELIST {
		VirtTableRowDump(exp, row)
	}
}
//====================================================================================
//OK -- Store rows for table.
//====================================================================================
func VirtTableStore(db *sql.DB,exp *tbExpUtils.ExperimentType, tablename string) bool {

	table := exp.VirtTables[tablename] // VirtualTableType

	//if len(table.TABLELIST) == 0 {
//		fmt.Println("VirtTableStore ======= LENGTH OF TABLELIST IS ZERO")
//		return true
//	}

		/*
	//for rowref := range tableRows {
	for  _,rowref :=  range table.TABLELIST {
		if VirtTableRowStore(db, exp, tablename, rowref) == false {
			return false
		}
	}
	*/
	tableRow := table.TABLEROW  // map[string]interface{}

	if VirtTableRowStore(db, exp, tablename, tableRow) == false {
		fmt.Println("VirtTableStore: table", tablename,"  STORE in DB", tableRow)
		return false
	}
	return true
}

//====================================================================================
//OK -- TODO Check return type ... Return list of rows.
//====================================================================================
func VirtTableRows(exp *tbExpUtils.ExperimentType) map[string]tbExpUtils.VirtualTableType {

	rows   := exp.VirtTables

	return rows
}

//====================================================================================
//OK -- Find a particular row in a table.
//====================================================================================
func VirtTableFindRow(exp *tbExpUtils.ExperimentType, tablename string,
					args []string)  map[string]interface{} {
	//@args = @_;
	table := exp.VirtTables[tablename]
	var empty map[string]interface{}
	// No members
	if len(table.TABLELIST) == 0 {//# No members.
		return empty
	}

	//# Get the slotnames that determine the lookup from the table above.
	_,present := VirtualTablesPrimaryKeyMap[tablename]
	if present == false {
		fmt.Println("Find: No entry in VirtualTablesPrimaryKeyMap for " +tablename)
		return empty
	}

	pkeys := VirtualTablesPrimaryKeyMap[tablename]

	if len(pkeys) != len(args) {
		fmt.Println("Find: Wrong number of arguments for lookup in $self");
		return empty
	}

	//# This is the full key.
	akey := strings.Join(args, ":")

	return table.TABLEHASH[akey]
}
//====================================================================================
// TODO Delete a row in a table. Do not call this. Utility for below.
//====================================================================================

func VirtTableDeleteTableRow(db *sql.DB, exp *tbExpUtils.ExperimentType,
	    			tablename string, tablerow map[string]string) {
/*
	table := exp.VirtExp.Tables[tablename]
	var newrows [] string

	rows := table.TABLEHASH[tablename]

	for val,rowref := range rows {
		if VirtTableRowSameRow(tablerow, rowref) == false {
			newrows = append(newrows, rowref)
		}
	}

	self->{'TABLES'}->{$tablename} = \@newrows;

	my @keys = keys(%{ $self->{'TABLEHASH'} });
	foreach my $key (@keys) {
	my $ref = $self->{'TABLEHASH'}->{$key};

	if ($ref->SameRow($row)) {
	delete($self->{'TABLEHASH'}->{$key});
	last;
	}
	}
	return 0;
	*/
}
/*
			for  _,key :=  range pkeys {
			value, _ := tablerows[key]
			pvals += " and " + key + "='" + value + "' "
			*/
//====================================================================================
//OK -- Stringify for output.
//====================================================================================
func VirtTableStringify(exp *tbExpUtils.ExperimentType, tablename string) string {

	pid   := exp.EXPT["pid"].(string)
	eid   := exp.EXPT["eid"].(string)
	idx   := exp.EXPT["idx"].(string)

	return "[" + tablename + ": " + pid + "/" + eid + "/" +
		       idx + "]"
}

