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
)

//# Update a template record given an array reference of slot/value pairs.

func ExpTemplateUpdate(db *sql.DB, exp *ExperimentType,
	                  argref map[string]string) error {

	guid := exp.TEMPLATE["guid"].(string)
	vers := exp.TEMPLATE["vers"].(string)
	if guid == "" || vers == "" {
		return nil
	}
	all := ""
	for key := range argref {
		value := argref[key]
		if all == "" {
			all += key + "='" + value + "'"
		} else {
			all += "," + key + "='" + value + "'"
		}
	}
	query := "update experiment_templates set " + all +
		     " where guid='" + guid + "' and vers='" + vers + "'"

	_,_,err := tbDbaseUtils.DBtransaction("ExpTemplateUpdate",db, query)
	if err != nil {
		return err
	}

	return TemplateRefresh(db, exp, guid, vers)
}

//# Refresh a template instance by reloading from the DB.
func TemplateRefresh(db *sql.DB, exp *ExperimentType, guid, vers string) error {

	rows,err := tbDbaseUtils.DBselectQuery("TemplateRefresh",db,
		"select * from experiment_templates " +
		"where guid='" + guid + "' and vers='" + vers + "'")
	if err != nil {
		if rows != nil {rows.Close()}
		return err
	}

	exp.TEMPLATE = UnpackRowsIntoMap("experiment_templates",rows)
	if rows != nil {rows.Close()}
	return nil
}

//# Lookup a template given pid,eid. This refers to the template itself,
//# not an instance of the template.
//
func TemplateLookupByPidEid(db *sql.DB, exp *ExperimentType) map[string]interface{} {

	pid := exp.EXPT["pid"].(string)
	eid := exp.EXPT["eid"].(string)

	rows,err := tbDbaseUtils.DBselectQuery("TemplateLookupByPidEid",db,
		"select guid,vers from experiment_templates " +
		"where pid='" + pid + "' and eid='" + eid + "'")

	if err != nil {
		if rows != nil {rows.Close()}
		return nil
	}
	var guid,vers string
	if rows.Next() {
		rows.Scan(&guid, &vers)
	} else {
		if rows != nil {rows.Close()}
		return nil
	}
	if rows != nil {rows.Close()}
	return TemplateLookup(db, exp, guid, vers)
}

//======================================================================
//# Lookup a template and create a class instance to return.
//======================================================================
func TemplateLookup(db *sql.DB, exp *ExperimentType,
			guid,vers string) map[string]interface{} {
	/*
	# A single arg is either an index or a "guid,vers" or "guid/vers" string.
	if (!defined($arg2)) {
		if ($arg1 =~ /^([-\w]*),([-\w]*)$/ ||
			$arg1 =~ /^([-\w]*)\/([-\w]*)$/) {
			$guid = $1;
			$vers = $2;
		} else {
			return undef;
		}
	}else if (! (($arg1 =~ /^[-\w]*$/) && ($arg2 =~ /^[-\w]*$/))) {
		return undef;
	}
	else {
		$guid = $arg1;
		$vers = $arg2;
	}
	*/

	rows,err := tbDbaseUtils.DBselectQuery("TemplateLookup",db,
		"select * from experiment_templates " +
				"where guid='" +guid+ "' and vers='" + vers + "'")
	if err != nil {
		if rows != nil {rows.Close()}
		return nil
	}

	templateMap := UnpackRowsIntoMap("experiment_templates",rows)
	if rows != nil {rows.Close()}
	exp.TEMPLATE = templateMap
	//# Filled lazily.
	// empty := map[string]interface{}
	// for k := range m {
	// delete(m, k)
	//}
	exp.INSTANCE = nil

	return exp.TEMPLATE
}