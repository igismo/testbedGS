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

//=============================================================================
//# Lookup by idx.
//=============================================================================
func GroupLookup(db *sql.DB, pid,
							gid string) map[string]interface{} {
	fmt.Println("GroupLookup: START pid=", pid," gid=", gid)
	//# A single arg is either an index or a "pid,gid" or "pid/gid" string.
	var gid_idx = ""
	if gid == "" {
		gid_idx = pid
	}

	//# Two args means pid/gid lookup instead of gid_idx.
	if gid_idx == "" {
		rows, err := tbDbaseUtils.DBselectQuery("GroupLookup",db,"select gid_idx from groups " +
		"where pid='" + pid + "' and gid='" + gid + "'")
		if err != nil {
			if rows != nil {rows.Close()}
			fmt.Println("GroupLookup: ERROR select gid_idx")
			return nil
		}
		for rows.Next() {
			rows.Scan(&gid_idx)
		}
		fmt.Println("GroupLookup: gid_idx=", gid_idx)
		if rows != nil {rows.Close()}
	}

	//# Look in cache first
	////if (exists(groups{"gid_idx"}))   return groups{"$gid_idx"}
	//var pid,gid,pid_idx,gid_idx,gid_uuid,leader,leader_idx,created,description,
	// unix_gid,unix_name,expt_count,expt_last,wikiname,mailman_password string
	fmt.Println("GroupLookup: Get info from dBase groups table")
	rows, err := tbDbaseUtils.DBselectQuery("GroupLookup",db,
					"select * from groups where gid_idx='" + gid_idx + "'")
	if err != nil {
		if rows != nil {rows.Close()}
		fmt.Println("GroupLookup: ERROR select * from groups")
		return nil
	}
	fmt.Println("GroupLookup: unpack into group map")
	groupMap := UnpackRowsIntoMap("groups",rows)
	fmt.Println("\nGroupLookup: GROUP=", groupMap)
	if rows != nil {rows.Close()}

	//var empty map[string]interface{}
	//thisGroup["PROJECT"] = empty

	// Add to cache.
	// groups{"gid_idx"} = thisGroup

	return groupMap
}
/*
// For -> hardcoded values for testing ..... TODO
func GroupSetup(pid,gid string) Group {
	var group Group
	group.pid = pid
	group.gid = gid
	group.gid_idx = 10001
	group.leader = "scuric"
	group.leader_idx = 10002
	group.unix_gid = 6002
	return group
}
*/