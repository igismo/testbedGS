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
	"strconv"
	"testbedGS/common/tbDbaseUtils"
)

//====================================================================================
// show columns from experiment_resources
//====================================================================================

type ProjectType struct {
	//proj PROJECTtype
	// group GROUPtype
}

//====================================================================================
func ProjectIndex(dbx *sql.DB, project string) (int64, error) {
	return GetIndex(dbx, "projects", "pid_idx", "pid", project)
}

//====================================================================================
// Load the project object for an experiment.
//====================================================================================
// for now create SLICE with all projects ..... or to start, just a single project

func ProjectGetProject(db *sql.DB, exp *ExperimentType, pid_idx int) *ProjectType {
	project := ProjectLookup(db, exp, strconv.Itoa(pid_idx))
	return project;
}
//====================================================================================
//
//====================================================================================
func ProjectLookup(db *sql.DB, exp *ExperimentType, token string) *ProjectType {
	var DeterTest ProjectType // need to initialize it (from DB ??)

	/*
	if _, err := strconv.ParseInt(token,10,64); err == nil {
		fmt.Printf("%q looks like a number.\n", token)
		// $query_result = DBQueryWarn("select * from projects where pid_idx='$token'");
	} else {
		// $query_result = DBQueryWarn("select * from projects where pid='$token'");
	}
	*/
	rows, err := tbDbaseUtils.DBselectQuery("ProjectLookup",db,
		"select * from projects where pid='" + token + "'")
	if err != nil {
		if rows!= nil {rows.Close()}
		return nil
	}

	projectMap := UnpackRowsIntoMap("projects",rows)
	if rows!= nil {rows.Close()}
	exp.PROJECT = projectMap

	exp.GROUP = GroupLookup(db, projectMap["pid_idx"].(string), "")

	return &DeterTest
}
//====================================================================================
func ProjectMembership(db *sql.DB, user, project string) (string, error) {

	rows, err := tbDbaseUtils.DBselectQuery("ProjectMembership",db,
		"select trust from group_membership where uid=? and pid=?" +
		"and user='" + user + "' and project='" + project + "'")
	if err != nil  {
		if rows!= nil {rows.Close()}
		return "", err
	}

	if !rows.Next() {
		if rows!= nil {rows.Close()}
		return "", nil
	}

	//extract trust level
	var trust string
	err = rows.Scan(&trust)
	if err != nil {
		if rows!= nil {rows.Close()}
		return "", err
	}
	if rows!= nil {rows.Close()}
	return trust, nil

}
