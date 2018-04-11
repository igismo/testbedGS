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
)

//====================================================================================
//# Auxillary instance method to check whether user is an instructional account
//====================================================================================
func CourseAcct(db *sql.DB, userId string) string {

	// this_idx = $this->uid_idx();
	// my $basename = substr($this->uid(), 0, -2);
	var basename = userId

	rows,err := tbDbaseUtils.DBselectQuery("CourseAcct",db,
		"select a.pid from project_attributes as a " +
			"where a.attrkey='class_idbase' and a.attrvalue='" + basename + "'")
	if err != nil {
		if rows != nil {rows.Close()}
		return ""
	}
	var thisPid string
	for rows.Next() {
		rows.Scan(&thisPid)
	}
	if rows != nil {rows.Close()}
	return thisPid
}

// TODO
func CourseSwapperCClist() string {
	/*
	my $course_swaperr_cclist;
	if (defined($user_course_pid)) {
		my $project = Project->Lookup($pid);
		my $inst = User->Lookup($project->head_idx);
		$course_swaperr_cclist = $inst->name . " <" . $inst->email . ">";
		my $group = Group->Lookup($pid,$experiment->gid);
		my @group_roots;
		$group->MemberList(\@group_roots, $Group::MEMBERLIST_FLAGS_GETTRUST,
		'group_root');
		foreach (@group_roots) {
			$course_swaperr_cclist .= ", ". $_->name . " <" . $_->email . ">";
		}
	}
	*/
	return ""
}