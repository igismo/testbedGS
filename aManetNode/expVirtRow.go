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
package main // expVirtRow.go
import (
	"testbedGS/common/tbExpUtils"
	"fmt"
	"testbedGS/common/tbDbaseUtils"
	"database/sql"
	"strings"
)

//====================================================================================
//OK -- AUTOLOAD a key and return the value
//====================================================================================
func VirtTableRowAUTOLOAD( exp *tbExpUtils.ExperimentType,
							tablename string, name string) map[string]interface{} {
	// name is the key
	//var empty map[string]map[string]string
	table := exp.VirtTables[tablename]
	var empty map[string]interface{}

	_,present := table.SLOTNAMES[name] //TABLEHASH[akey]
	if present == false {
		fmt.Println("No such slot " + name + " field in class $type\n")
		return empty
	}

	 for key, val := range table.SLOTNAMES {
		 if key == name {
			 table.TABLEROW[key] = val
		 }
	 }

	return table.TABLEROW
}
//====================================================================================
//OK    Delete Table Row
//====================================================================================
func VirtTableRowDESTROY(exp *tbExpUtils.ExperimentType, tablename string) {
	table := exp.VirtTables[tablename]

	for k := range table.SLOTNAMES {
		delete(table.SLOTNAMES, k)
	}
	for k := range table.TABLEHASH {
		delete(table.TABLEHASH, k)
	}

	// TODO Clear the TABLELIST array ....
}
//====================================================================================
//OK   VirtTableRowSameRow
//====================================================================================
func VirtTableRowSameRow(this,that map[string]string) bool  {

	var v1,k1,v2,k2 string
	for  _,k1 :=  range this {
		v1, _ = this[k1]
	}
	for  _,k2 :=  range this {
		v2, _ = this[k2]
	}
	if v1 == v2 && k1==k2 {
		return true
	}
	return false
}
//====================================================================================
//OK  Virt Table Row Create
//====================================================================================
func VirtTableRowCreate(db *sql.DB,exp *tbExpUtils.ExperimentType,tablename string,
	                    argRef map[string]interface{})  map[string]interface{} {
	fmt.Println("VirtTableRowCreate:  table=", tablename)
	// table is a pointer to a record containg everything about this table
	table := exp.VirtTables[tablename] // map of rows/keys for this table

	rows, err := tbDbaseUtils.DBselectQuery("VirtTableRowCreate",db, "describe " + tablename)
	if err != nil {
		if rows != nil {rows.Close()}
		return argRef
	}

	// TODO this was already done before .... in virtTableCreate,but check if called by refresh
	/*
	var Field,Type,Null,Key,Default,Extra string
	slots := make(map[string]string)
	table.SLOTNAMES = slots
	for rows.Next() {
		rows.Scan(&Field,&Type,&Null,&Key,&Default,&Extra)
		fmt.Println("VirtTableRowCreate: Field=",Field," Type=",Type," Null=",Null,
			" Key=", Key, " Default=", Default, " Extra=",Extra)
		table.SLOTNAMES[Field] = Default  // slotKey-value pairs
	}
	*/

	for key, val := range argRef {
		if _, ok := argRef[key]; ok {
			table.TABLEROW[key] = val
		}
	}
	if rows != nil {rows.Close()}
	// table.TABLE HASH = nil
	// table.TABLE LIST = nil
	// TODO above ??


	return table.TABLEROW // TODO TODO was: argRef
}
//====================================================================================
//OK --  Store a single table row to the DB.
//====================================================================================
func VirtTableRowStore(db *sql.DB, exp *tbExpUtils.ExperimentType, tablename string,
			row map[string]interface{}) bool {
	//flags := 0
	fmt.Println("VirtTableRowStore:  table=", tablename)
	pid      := exp.EXPT["pid"].(string)
	eid      := exp.EXPT["eid"].(string)
	exptidx  := exp.EXPT["idx"].(string)

	//# These are the required keys, they must be defined.
	// table := exp.VirtTables[tablename] // VirtualTableType

	//slotNames := *table.SlotNames

	fields := []string {"exptidx", "pid", "eid"}
	values := []string {"'" + exptidx + "'",
			"'" + pid + "'", "'" + eid + "' ",}

	for key := range row {
		val := row[key]

		//# Always skip these; they come from the experiment object. Prevents
		//# users from messing up the DB with a bogus XML file.
		// TODO somewhere some how key "row" is inserted which should not be
		if key == "pid" || key == "eid" || key == "exptidx" || key == "row" {
			continue
		}

		if key == "idx" {
			//# This test for eventlist.
			if tablename == "eventlist" {
				values = append(values, "NULL")
			} else {
				values = append(values, fmt.Sprint(val))
			}
		} else if val == "NULL" {
			values = append(values, "NULL")
		} else if val == "" {
			values = append(values, "''")
		} else {

			values = append(values, fmt.Sprint(val))
		}
		//# If a key remove from the list; we got it.
		// TODO was delete(table.TABLEHASH, key)
		fields = append(fields, key)
	}

	query := "insert into " + tablename + " (" + strings.Join(fields, ",") +
		") values (" + strings.Join(values,",") +  ") "

	_,_,err :=tbDbaseUtils.DBtransaction("VirtTableRowStore",db, query)

	if err != nil {
		return false
	}
	return true
}
// insert into virt_routes (exptidx,pid,eid,row)
// values ('228','DeterTest','test1' ,
// map[mem_usage:0 cpu_usage:3 usewatunnels:0 uselatestwadata:1 wa_delay_solverweight:1 Wa_plr_solverweight:500 use_ipassign:0 forcelinkdelays:0 uselinkdelays:0 wa_bw_solverweight:7 wa_plr_solverweight:default allowfixnode:1 sync_server:nodeA])
//====================================================================================
//OK --  Delete a row in a table.
//====================================================================================
func  VirtTableRowDelete(db *sql.DB, exp *tbExpUtils.ExperimentType, tablename string,
					row map[string]string)  bool {

	// flags = 0
	// debug    = ($flags & $VirtExperiment::STORE_FLAGS_DEBUG ? 1 : 0);
	// table is apointer to record containg everything about this table
	// self is a pointer to this table hash map
	// table := exp.VirtTables[tablename]
	// slotNames := *table.SlotNames

	exptidx  := exp.EXPT["idx"].(string)
	//# These are the keys for the table.
	//my %pkeys   = map { $_ => $_ } @{VirtualTablesPrimaryKeyMap[tablename]};

	pkeys := VirtualTablesPrimaryKeyMap[tablename]
	var pvals = " "

	for  _,key :=  range pkeys { // index,key :=
		value, _ := row[key]
		pvals += " and " + key + "='" + value + "' "
	}

	query := "delete from " + tablename + " where exptidx=" +
		exptidx + pvals

	_,_,err := tbDbaseUtils.DBtransaction("VirtTableRowDelete",db,query)
	if err != nil {
		return false
	}

	VirtTableDeleteTableRow(db, exp, tablename, row)

	return true
}

//====================================================================================
// TODO Dump the row contents of virt tables.
//====================================================================================
func VirtTableRowDump(exp *tbExpUtils.ExperimentType, dbrow map[string]interface{}) {

	fmt.Println("VirtTableRowDump: key \n")
	for key, val := range dbrow {
		fmt.Println("  " + key + " : " + val.(string) + "\n")
	}
}
//====================================================================================
// TODO -- Stringify for output.
//====================================================================================
/*
func Stringify() {
	table = $self->tablename();
	row   = $self->tablerow();
	pid   = ($self->experiment() ? $self->experiment()->pid() : "?");
	eid   = ($self->experiment() ? $self->experiment()->eid() : "?");
	idx   = ($self->experiment() ? $self->experiment()->idx() : "?");

	@keys   = @{ $VirtExperiment::VirtualTablesPrimaryKeyMap{$table} };
	@values = map { $row->{$_} } @keys;
	@values    = map { (defined($_) ? $_ : "NULL") } @values;
	$keystr = join(",", @values);

	return "[$table: $pid/$eid/$idx $keystr]";
}
*/

