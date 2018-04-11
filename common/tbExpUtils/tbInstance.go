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
	"testbedGS/common/tbDbaseUtils"
	"database/sql"
	"fmt"
)

//# Update record.

func ExpInstanceUpdate(db * sql.DB, exp * ExperimentType,
						argref map[string]string) string {
	// TODO

	var exptidx  = exp.TEMPLATE["exptidx"].(string)
	var idx      = exp.EXPT["idx"].(string)
    fmt.Println("ExpInstanceUpdate: exptidx=",exptidx, "  idx=", idx)
	all := ""
	for key := range argref {
		value := argref[key]
		if all == "" {
			all += key + "='" + value + "'"
		} else {
			all += "," + key + "='" + value + "'"
		}
	}
	query := "update experiment_runs set " + all +
		     " where exptidx='" + exptidx + "' and idx='" + idx + "'"

	_,_,err := tbDbaseUtils.DBtransaction("ExpInstanceUpdate",db, query)
	if err != nil {
		return "ExpInstanceUpdate: failed to update:" + query
	}

	return expInstanceRefresh(db, exptidx, idx)
}


//# Refresh by reloading from the DB.

func  expInstanceRefresh(db *sql.DB, exptidx, idx string) string {
	fmt.Println("expInstanceRefresh: exptidx=",exptidx, "  idx=", idx)
	rows,err := tbDbaseUtils.DBselectQuery("expInstanceRefresh",db,
			"select * from experiment_runs " +
			"where exptidx='" + exptidx + "' and idx='" + idx + "'")
	if err != nil {
		if rows != nil {rows.Close()}
		return "expInstanceRefresh: failed to refresh"
	}
	// TODO finish this in Template.pm -> Instance
	// self->{'DB'] = $query_result->fetchrow_hashref();
	if rows.Next !=nil {
		instMap :=  UnpackRowsIntoMap("experiment_runs", rows)
		fmt.Println("expInstanceRefresh: ERROR (TODO -> STORE SOMEWHERE) instMap=",instMap)
	}
	if rows != nil {rows.Close()}
	return ""
}
