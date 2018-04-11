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
    "fmt"
	"testbedGS/common/tbDbaseUtils"
	"database/sql"
	"os"
	"strconv"
	"testbedGS/common/tbConfiguration"
	"log"
	"crypto/md5"
	"encoding/hex"
	"golang.org/x/crypto/bcrypt"
	//"testbedGS/common/tbJsonUtils"
	//"bytes"
	"reflect"
	"io/ioutil"
	//"strings"
	"strings"
)


// Virt Exp Tables hashes
//====================================================================================
type VirtualTableType struct {
	SLOTNAMES 		map[string]string // $slotnames
	Counter 		int
	TableName    	string
	TABLEHASH 		map[string]map[string]interface{}
	TABLEROW 		map[string]interface{}
	TABLELIST       []map[string]interface{}
}

type ExperimentType struct {
	// HASH 		map[string]map[string]interface{}
	EXPT		map[string]interface{}
	STATS		map[string]interface{}
	RSRC		map[string]interface{}
	//
	VARS 		map[string]string
	PROJECT 	map[string]interface{}
	GROUP 		map[string]interface{}
	TEMPLATE 	map[string]interface{}
	INSTANCE 	map[string]interface{}
	//
	VirtTables map[string] VirtualTableType
}

//====================================================================================
//# Set the cancel flag.
//====================================================================================
func ExpSetCancelFlag(db *sql.DB, exp *ExperimentType, flag int) error {

	pid := exp.EXPT["pid"].(string)
	eid := exp.EXPT["eid"].(string)

	TBSetCancelFlag(db, pid, eid, flag)

	return ExpRefresh(db, exp)
}

//====================================================================================
//# Set cancel flag,
//#
//# usage: SetCancelFlag(char *pid, char *eid, char *flag)
//#        returns 1 if okay.
//#        returns 0 if an invalid pid/eid or if an error.
//====================================================================================
func TBSetCancelFlag(db *sql.DB, pid, eid string, flag int) error {
	flagString := strconv.Itoa(flag)
	_,_, err :=tbDbaseUtils.DBtransaction("TBSetCancelFlag",db,
		"update experiments set canceled='" +
		flagString + "' where eid='" + eid + "' and pid='" + pid + "'")
	if err != nil {
		return err
	}

	return nil
}
//====================================================================================
// This needs to be restructured to comply with new world
// each exp tesk contains its own copy of all EXPT,STATS and RSRC so
// we just need to update maybe (but which way)
//====================================================================================
func ExpLookup(db *sql.DB, pid, eid, idxIn string) *ExperimentType {

	var idx = ""
	var gid = ""

	if len(idxIn) > 10 { // is this eid_uuid ?
		rows, err := tbDbaseUtils.DBselectQuery("ExpLookup",db,
			"select idx,gid from experiments where eid_uuid='" + idxIn + "'")
		if err != nil {
			if rows != nil {rows.Close()}
			return nil
		}

		for rows.Next() {
			rows.Scan(&idx,&gid)
		}
		if rows != nil {rows.Close()}
	} else {
		if idx != "" {
			idx = idxIn
		}
	}

	if idx == "" {
		// use pid eid to get
		rows, err := tbDbaseUtils.DBselectQuery("ExpLookup",db,"select idx,gid from experiments" +
			" where pid='" + pid + "' and eid='" + eid + "'")
		if err != nil {
			if rows != nil {rows.Close()}
			return nil
		}
		if rows.Next() {
			rows.Scan(&idx,&gid)
		}
		if rows != nil {rows.Close()}
	}
	if idx == "" {
		return nil
	}
	fmt.Println("ExperimentLookup: Look into experiments and experiment_templates ")
	rows,err :=
		tbDbaseUtils.DBselectQuery("ExpLookup",db, "select e.*,i.parent_guid,t.guid from experiments as e " +
			"left join experiment_templates as t on t.exptidx=e.idx " +
			"left join experiment_template_instances as i on i.exptidx=e.idx " +
			"where e.idx='" + idx + "'")
	if err != nil {
		fmt.Println("ExperimentLookup:  .... NOTHING FOUND")
		if rows != nil {rows.Close()}
		return nil
	}

	fmt.Println("ExperimentLookup: UNPACK INTO expMap")
	expMap := UnpackRowsIntoMap("experiments,template,template_instances", rows)
	if rows != nil {rows.Close()}
	fmt.Println("ExpLookup: Create exp table map=", expMap)
	var exp ExperimentType
	exp.EXPT  = expMap
	exp.VARS  = make(map[string]string)
	exp.STATS = make(map[string]interface{})

	// # An Instance?
	instance := exp.EXPT["parent_guid"].(string)
	template := exp.EXPT["guid"].(string)

	if instance != "" {
		exp.VARS["ISINSTANCE"] = instance
	}
	// # The experiment underlying a template.
	if template != "" {
		exp.VARS["ISTEMPLATE"] = template
	}

	fmt.Println("ExpLookup: Get experiment_stats")
	rows,err = tbDbaseUtils.DBselectQuery("ExpLookup",db,
		"select * from experiment_stats where exptidx='" + idx + "'")
	if err != nil {
		fmt.Println("ExpLookup: Get STATS failed")
		if rows != nil {rows.Close()}
		return nil
	}

	statsMap := UnpackRowsIntoMap("experiment_stats",rows)
	if rows != nil {rows.Close()}
	exp.STATS = statsMap
	//-------------------------------------------
	var rsrcidx = exp.STATS["rsrcidx"].(string)

	fmt.Println("ExpLookup: Get experiment_resources")
	rows,err = tbDbaseUtils.DBselectQuery("ExpLookup",db,
			"select * from experiment_resources where idx='" + rsrcidx + "'")
	if err != nil {
		fmt.Println("ExpLookup: Get RSRC failed")
		if rows != nil {rows.Close()}
		return nil
	}

	rsrcMap := UnpackRowsIntoMap("experiment_resources",rows)
	if rows != nil {rows.Close()}
	exp.RSRC = rsrcMap
	//-------------------------------------------
	exp.VARS["VIRTEXPT"] = ""
	fmt.Println("ExpLookup: Get projects")
	rows,err = tbDbaseUtils.DBselectQuery("ExpLookup",db,
		"select * from projects where pid='" + pid + "'")
	if err != nil {
		if rows != nil {rows.Close()}
		return nil
	}
	projectMap := UnpackRowsIntoMap("projects",rows)
	if rows != nil {rows.Close()}
	exp.PROJECT = projectMap
	//-------------------------------------------
	fmt.Println("ExpLookup: Get groupo")
	exp.GROUP   = GroupLookup(db, pid, gid)

	///# Add to cache.
	///$experiments{"$idx"} = $self;
	fmt.Println("ExpLookup: ALL DONE")
	return &exp
}

//====================================================================================

func ExpInitializeEnvVariables(db *sql.DB, exp *ExperimentType) int {
	fmt.Println("InitializeEnvVariables")

	ExpRefresh(db, exp)

	//`dpdb` tinyint(1) NOT NULL default '0',		(experiments)
	//`dpdbname` varchar(64) default NULL,			(experiments)
	//`dpdbpassword` varchar(64) default NULL,		(experiments)
	//`dpdbname` varchar(64) default NULL, 			(experiment_stats)

	if AddEnvVariable(db, exp,"DP_HOST", tbConfig.CONTROL, 1) != 0 {return -1 }
	// TODO add check for nil .....
	//dpdbname     := exp.EXPT["dpdbname"]// .(string)
	//if AddEnvVariable(db, exp,"DP_DBNAME", dpdbname.(string), 1) != 0 { return -1 }

	//dpdbpassword := exp.EXPT["dpdbpassword"]// .(string)
	//if AddEnvVariable(db, exp,"DP_PASSWORD", dpdbpassword.(string),1 ) != 0 {return -1}

	var dpdbuser string
	idx := exp.STATS["exptidx"]
	if idx != nil {
		dpdbuser = "E" + idx.(string)
	} else { dpdbuser = "E" + "0"}
	if AddEnvVariable(db, exp, "DP_USER", dpdbuser, 1) != 0 {return -1}

	return 0
}
//====================================================================================
// Add an environment variable.
//====================================================================================
func AddEnvVariable(db *sql.DB, exp *ExperimentType, name , value string,index int) int {

	// Look to see if the variable exists, since a replace will actually
	// create a new row cause there is an auto_increment in the table that
	// is used to maintain order of the variables as specified in the NS file.
	pid 	:= exp.STATS["pid"].(string)
	eid 	:= exp.STATS["eid"].(string)
	exptidx := exp.STATS["exptidx"]//.(string)
	rows, err :=tbDbaseUtils.DBselectQuery("AddEnvVariable",db,
		"select idx from virt_user_environment " +
		"where name='" + name + "' and pid='" +
			pid  + "' and eid='" +
				eid  + "'")
	if err != nil {
		if rows != nil {rows.Close()}
		return -1
	}

	if rows.Next() {
		var idx = index
		if index != 0 {
			idx = index
		} else { rows.Scan(&idx) }
		_,_,err :=tbDbaseUtils.DBtransaction("AddEnvVariable",db,"replace into virt_user_environment set " +
		"   name='$name', value='" + value + "', idx=" + strconv.Itoa(idx) + ", exptidx='" +
			exptidx.(string) + "', pid='" + pid  + "', eid='" + eid  + "'")
		if err != nil {
			if rows != nil {rows.Close()}
			return -1}
	} else {
		_,_,err :=tbDbaseUtils.DBtransaction("AddEnvVariable",db,"insert into virt_user_environment set " +
		"   name='" + name + "', value='" + value + "', idx=NULL, " +
		"   exptidx='" + exptidx.(string) +
			"', pid='" + pid  + "', eid='" + eid  + "'")
		if err != nil {
			if rows != nil {rows.Close()}
			return -1;
		}
	}
	if rows != nil {rows.Close()}
	return 0;
}

//====================================================================================
// Refresh a class instance by reloading from the DB.
// WE really need to supply aggregate exp record ExperimentTYPE ...
//                          161               220
//  experiments stats      exptidx          rsrcidx
//  experiments resources  exptidx  ------  idx
//  experiments             idx
//====================================================================================
func ExpRefresh(dbConnection *sql.DB, exp *ExperimentType) error {
	fmt.Println("ExpRefresh: Start STATS=", exp.STATS, " \nEXPT=", exp.EXPT)
	//---------------------------------------------
	pid := exp.EXPT["pid"].(string)
	eid := exp.EXPT["eid"].(string)
	rows, err := tbDbaseUtils.DBselectQuery("ExpRefresh",dbConnection,
		        "select * from experiments where pid='" + pid + "' and eid='" + eid + "'")
	if err != nil || rows == nil{
		if rows != nil {rows.Close()}
		return err
	}
	expMap := UnpackRowsIntoMap("experiments",rows)
	if rows != nil {rows.Close()}
	exp.EXPT = expMap
	fmt.Println("ExpRefresh: EXPT MAP=", expMap)
	//---------------------------------------------
	// TODO make sure this is correct
	exp.VARS["VIRTEXPT"]   = ""		// undef
	exp.VARS["ISINSTANCE"] = ""		// undef
	exp.VARS["ISTEMPLATE"] = ""		// undef

	//---------------------------------------------
	idx := exp.EXPT["idx"].(string)
	fmt.Println("ExpRefresh: experiment_stats")
	rows, err = tbDbaseUtils.DBselectQuery("ExpRefresh",dbConnection,
		"select * from experiment_stats where " +
		"exptidx='" +  idx + "'")
	if err != nil || rows == nil{
		if rows != nil {rows.Close()}
		return err
	}
	statsMap := UnpackRowsIntoMap("experiment_stats",rows)
	exp.STATS = statsMap
	if rows != nil {rows.Close()}
	//---------------------------------------------
	rsrcidx := exp.STATS["rsrcidx"].(string) // Do not move from here
	fmt.Println("ExpRefresh: experiment_resources rsrcidx=", rsrcidx)
	rows, err = tbDbaseUtils.DBselectQuery("ExpRefresh",dbConnection,"select * from " +
		"experiment_resources where idx='" + rsrcidx  + "'")
	if err != nil || rows == nil{
		if rows != nil {rows.Close()}
		return err
	}
	rsrcMap := UnpackRowsIntoMap("experiment_resources",rows)
	exp.RSRC = rsrcMap
	if rows != nil {rows.Close()}

	fmt.Println("ExpRefresh: DONE")
	return nil
}

//====================================================================================
// Write the environment strings into a little script in the user directory.
//====================================================================================
func ExpWriteEnvVariables(db *sql.DB, exp *ExperimentType) int {

	pid := exp.STATS["pid"].(string)
	eid := exp.STATS["eid"].(string)

	rows, err := tbDbaseUtils.DBselectQuery("ExpWriteEnvVariables",db,
		"select name,value from virt_user_environment " +
			"where  pid='" + pid + "' and eid='" + eid + "' order by idx")
	if err != nil || rows == nil{
		if rows != nil {rows.Close()}
		return -1
	}

	userdir := exp.EXPT["path"].(string)
	envfile := userdir + "/tbdata/environment";

	file1, err := os.OpenFile(envfile, os.O_RDWR|os.O_CREATE, 0666)
	if err != nil {
		fmt.Println("Could not open " + envfile + " for writing: $!\n")
		if rows != nil {rows.Close()}
		return -1
	}
	for rows.Next() {
		var name, value string
		rows.Scan(&name, &value)
		file1.WriteString(name + "=\"" + value + "\"\n")
	}
	file1.Close()
	if rows != nil {rows.Close()}
	return 0;
}
//====================================================================================
// Set Experiment State
//====================================================================================
func ExpSetState(db *sql.DB, exp *ExperimentType,  newstate string) int {

	pid := exp.EXPT["pid"].(string)
	eid := exp.EXPT["eid"].(string)

	_,_,err := tbDbaseUtils.DBtransaction("ExpSetState",db,
		"update experiments set state='" +
		newstate + "' where eid='" + eid + "' and pid='" + pid + "'")
	if err != nil {
		return -1;
	}

	exp.EXPT["state"] = newstate
	/*

	EventSendWarn(objtype   => libdb::TBDB_TBEVENT_EXPTSTATE(),
	objname   => "$pid/$eid",
	eventtype => $newstate,
	expt      => "$pid/$eid",
	host      => $BOSSNODE);

	*/
	return 0;
}

//====================================================================================
// Perform some updates ...
//for k := range m {
//keys = append(keys, k)
//}
//====================================================================================
func ExpUpdate(db *sql.DB, exp *ExperimentType, args map[string]string) error {

	pid := exp.EXPT["pid"].(string)
	eid := exp.EXPT["eid"].(string)

	all := ""
	for key := range args {
		value := args[key]
		if all == "" {
			all += key + "='" + value + "'"
		} else {
			all += "," + key + "='" + value + "'"
		}
	}

	query := "update experiments set " + all +
		      " where pid='" + pid + "' and eid='" + eid + "'"

	_,_,err := tbDbaseUtils.DBtransaction("ExpUpdate", db, query)
	if err != nil {
		return err
	}

	return ExpRefresh(db, exp);
}


//====================================================================================
func PasswordHash(dbx *sql.DB, user string) (*string, error) {

	rows, err := tbDbaseUtils.DBselectQuery("PasswordHash", dbx,
		"select usr_pswd from users where uid='" + user + "'")
	if err != nil {
		if rows != nil {rows.Close()}
		return nil, err
	}

	hash := new(string)
	// extract users pasword hash from query
	if rows.Next != nil {
		err = rows.Scan(hash)
		if err != nil {
			if rows != nil {rows.Close()}
			return nil, err
		}
	}
	if rows != nil {rows.Close()}
	return hash, nil

}

//====================================================================================
func GroupIndex(dbx *sql.DB, group string) (int64, error) {
	return GetIndex(dbx, "groups", "gid_idx", "gid", group)
}
//====================================================================================
func GetIndex(dbx *sql.DB, table, column, selector, key string) (int64, error) {

	query := "select " + column + " from " + table + " where selector='" + key + "'"

	rows, err := tbDbaseUtils.DBselectQuery("", dbx, query)
	if err != nil {
		if rows != nil {rows.Close()}
		return -1, err
	}

	if !rows.Next() {
		if rows != nil {rows.Close()}
		return -1, err
	}

	var idx int64
	err = rows.Scan(&idx)
	if rows != nil {rows.Close()}
	return idx, err

}
//====================================================================================
//# Set the experiments NS file using AddInputFile() above
//====================================================================================
func ExpSetNSFile(db *sql.DB, exp *ExperimentType, nsfile string) bool {
	return ExpAddInputFile(db, exp, nsfile, true)
}
//====================================================================================
//# Add an input file to the template. The point of this is to reduce
//# duplication by taking an md5 of the input file, and sharing that
//# record/file.
//====================================================================================

func ExpAddInputFile(db *sql.DB, exp *ExperimentType, inputfile string, isnsfile bool) bool {
	// TODO GORAN add NSfile!!!

	input_data_idx := "0"
	isnew := false

	// Read the content of the file into data_string
	b, err := ioutil.ReadFile(inputfile)
	if err != nil {
		fmt.Print(err)
		return false
	}
	data_string  := string(b) // convert content to a 'string'
	if len(data_string) == 0 { return false}

	exptidx   	:= exp.STATS["exptidx"].(string)
	rsrcidx   	:= exp.STATS["rsrcidx"].(string)

	data_string = tbDbaseUtils.Escape(data_string)
	newLen := len(data_string)
	if int64(newLen) >= DBLIMIT_NSFILESIZE() {
		fmt.Println("Input file is too big  ", newLen, " MAX=",DBLIMIT_NSFILESIZE())
		return false
	}

	//# Grab an MD5 of the file to see if we already have a copy of it.
	//# Avoids needless duplication.
	md5Value, err := tbDbaseUtils.Hash_file_md5(inputfile)
	// md5 = strings.TrimSuffix(md5, "\n")  // chomp($md5);

	tbDbaseUtils.DblockTables("ExpAddInputFile", db,"experiment_input_data write, " +
							" experiment_inputs write, experiment_resources write")

	query_result, err := tbDbaseUtils.DBselectQuery("ExpAddInputFile",db,
					"select idx from experiment_input_data where md5='" + md5Value + "'")
	if err != nil {
		if query_result != nil {query_result.Close()}
		tbDbaseUtils.DbunlockTables("ExpAddInputFile", db)
		return false
	}

	if query_result.Next() {
		query_result.Scan(&input_data_idx)
		isnew = false
		if query_result != nil {query_result.Close()}
	} else 	{
		if query_result != nil {query_result.Close()}
		insertid,_,err := tbDbaseUtils.DBtransaction("ExpAddInputFile", db,
					"insert into experiment_input_data (idx, md5, input) values " +
						"(NULL, '" + md5Value + "', '" + data_string + "')")
		if err != nil {
			tbDbaseUtils.DbunlockTables("ExpAddInputFile", db)
			if query_result != nil {query_result.Close()}
			return false
		}

		input_data_idx = string(insertid)
		isnew = true
	}
	if query_result != nil {query_result.Close()}

	_,_,err = tbDbaseUtils.DBtransaction("ExpAddInputFile", db,
				"insert into experiment_inputs (rsrcidx, exptidx, input_data_idx) values " +
				" (" + rsrcidx + "," + exptidx + ", " + input_data_idx + ")")
	if err != nil {
		if isnew {
			tbDbaseUtils.DBtransaction("ExpAddInputFile", db,
							"delete from experiment_input_data where idx='$input_data_idx'")
		}

		tbDbaseUtils.DbunlockTables("ExpAddInputFile", db)

		return false
	}

	if isnsfile && expTableUpdate(db, exp, "experiment_resources",
		"input_data_idx='" + input_data_idx + "'", "idx='" +rsrcidx+ "'") == false  {

		tbDbaseUtils.DbunlockTables("ExpAddInputFile", db)
		return false
	}
	tbDbaseUtils.DbunlockTables("ExpAddInputFile", db)

	return true
}
//====================================================================================
//# Ditto for update.
//====================================================================================
func expTableUpdate(db *sql.DB, exp *ExperimentType, table, sets, conditions string) bool {

	// this was ment to be used if the sets is passed as map
	//if (ref($sets) eq "HASH") {
	//	sets = join(",", map("$_='" . $sets->{$_} . "'", keys(%{$sets})));
	//}

	exptidx := exp.EXPT["idx"].(string)

	if conditions != "" {
		conditions = "and " +  conditions
	}

	_,_,err :=tbDbaseUtils.DBtransaction("ExpAddInputFile", db,
					"update " + table + " set " + sets  + "where exptidx='" + exptidx + "' " +  conditions )
	if err !=nil {
		fmt.Println("expTableUpdate: ERROR updating table ", table)
		return false
	}

	return true
}

//====================================================================================
//# Delete the input files, but only if not in use.
//====================================================================================
func ExpDeleteInputFiles(exp *ExpType) int {
/*
	rsrcidx := exp.Rsrcidx
	nsidx   := exp.input_data_idx();

	DBQueryWarn("lock tables experiment_input_data write, ".
		"            experiment_resources write, ".
		"            experiment_inputs write")


	//# Get all input files for this rsrc record.

	query_result = tbDbaseUtils.DBselectQuery("select input_data_idx from experiment_inputs ".
			"where rsrcidx='$rsrcidx'");
	goto bad
		if (! $query_result);
	goto done
		if (! $query_result->numrows);

	while (my ($input_data_idx) = $query_result->fetchrow_array()) {
		//	# Delete but only if not in use
		query_result = tbDbaseUtils.DBselectQuery("select count(rsrcidx) from experiment_inputs ".
			"where input_data_idx='$input_data_idx' and "."      rsrcidx!='$rsrcidx'");
		goto bad
			if (! $query_result);

		DBQueryWarn("delete from experiment_inputs "."where input_data_idx='$input_data_idx'")
			or goto bad;

		if (defined($nsidx) && $nsidx == $input_data_idx) {
			DBQueryWarn("update experiment_resources set input_data_idx=NULL "."where idx='$rsrcidx'")
			or goto bad;
		}
		next if ($query_result->numrows);

		DBQueryWarn("delete from experiment_input_data "."where idx='$input_data_idx'")
		or goto bad;
	}
	done:
		DBQueryWarn("unlock tables");
		return 0;

	bad:
		DBQueryWarn("unlock tables");
*/
		return 1;
}
//====================================================================================
// Grab an input file.
//====================================================================================
func ExpGetInputFile(db *sql.DB, idx string, nsfile *string) error {

	rows, err := tbDbaseUtils.DBselectQuery("ExpGetInputFile", db,
			"select input from experiment_input_data where idx='" + idx + "'")
	if err != nil {
		if rows != nil {rows.Close()}
		return err
	}
	if rows.Next() {
		rows.Scan(&nsfile)
	}
	if rows != nil {rows.Close()}
	return nil
}
//====================================================================================
func ExpGetNSFile(db *sql.DB, exp *ExperimentType, pref *string) error {

	input_data_idx := exp.RSRC["input_data_idx"].(string)

	return ExpGetInputFile(db, input_data_idx, pref);
}
//====================================================================================
//# Long term storage.
func ExpInfoDir(pid, eid, idx string) string {

	return tbConfig.TB + "/expinfo" + "/" + pid + "/" + eid + "/" + idx
}

//====================================================================================
//# Return the user and work directories. The workdir in on boss and where
//# scripts chdir to when they run. The userdir is across NFS on ops, and
//# where files are copied to.
//====================================================================================
func WorkDir(pid, eid string) string {
	return TBDB_EXPT_WORKDIR() + "/" + pid + "/" + eid
}
//====================================================================================
func UserDir(pid, eid string) string {
	//TODO
	// return exp.path();
	return tbConfig.TBDB_USERDIR + "/" + pid + "/exp/" + eid
}
//====================================================================================
//# Event/Web key filenames.
func EventKeyPath(pid, eid string)  string {
	return UserDir(pid, eid) + "/tbdata/eventkey";
}
//====================================================================================
func WebKeyPath(pid, eid string) string {
	return UserDir(pid, eid) +  "/tbdata/webkey"
}
//// OLD
//====================================================================================
// Long term storage.
func InfoDir(exp * ExpType) string {
	pid := exp.Pid
	eid := exp.Eid
	idx := exp.Idx
	return tbConfig.TBDB_EXPT_INFODIR + "/" + pid + "/" + eid + "/" +
							strconv.FormatInt(idx,10)
}

//====================================================================================
//# Experiment locking and state changes.
//====================================================================================
func ExpUnlock(db *sql.DB, exp *ExperimentType, newstate string)  error {

	pid := exp.EXPT["pid"].(string)
	eid := exp.EXPT["eid"].(string)
	sclause := ",state='" + newstate + "' "

	numrows,_, err := tbDbaseUtils.DBtransaction("ExpUnlock", db,
		"update experiments set expt_locked=NULL " + sclause +
		"where eid='" + eid + "' and pid='" + pid + "'")
	if err != nil {
		fmt.Println("ExpUnlock: ERROR locking experiments ")
		return err
	}

	if numrows == 0 {
		return err
	}

	exp.EXPT["state"] = newstate
	/* TODO
	if ($EVENTSYS) {
		EventSendWarn(objtype   => libdb::TBDB_TBEVENT_EXPTSTATE(),
		objname   => "$pid/$eid",
		eventtype => $newstate,
		expt      => "$pid/$eid",
		host      => $BOSSNODE);
	}
	*/

	return nil
}

//====================================================================================
//====================================================================================
func Lock(exp *ExperimentType, newstate string, unlocktables int) int {
	/*
	my $pid = $self->pid();
	my $eid = $se-lf->eid();
	my $sclause = (defined($newstate) ? ",state='$newstate' " : "")

	query_result,_,err := tbDbaseUtils.DBtransaction("", db,
				"update experiments set expt_locked=now() $sclause " +
				"where eid='$eid' and pid='$pid'")

	if (! $query_result || $query_result->numrows == 0) {
		if ($unlocktables)  $self->UnLockTables()
		return -1;
	}

	//# We do this before calling out to the event system to avoid livelock
	//# in case the event system goes down.

	if ($unlocktables)  $self->UnLockTables()

	if (defined($newstate)) {
		$self->{'EXPT'}->{'state'} = $newstate;

		EventSendWarn(objtype   => libdb::TBDB_TBEVENT_EXPTSTATE(),
		objname   => "$pid/$eid",
		eventtype => $newstate,
		expt      => "$pid/$eid",
		host      => $BOSSNODE);
	}
	*/
	return 0
}

//====================================================================================
//
//====================================================================================
func IsNumeric(s string) bool {
	_, err := strconv.ParseFloat(s, 64)
	return err == nil
}
func UnpackRowsIntoMap(rowName string, rows *sql.Rows) map[string]interface{} {
	colNames, _ := rows.Columns()
	fmt.Println("UNPACK ROW INTO MAP length=", len(rowName), "  MAP: ", rowName)
	rsrcMap := make(map[string]interface{})

	// Create a slice of interface{}'s to represent each column,
	// and a second slice to contain pointers to each item in the columns slice.
	columns := make([]interface{}, len(colNames))
	columnPointers := make([]interface{}, len(colNames))

	for i, _ := range columns {
		columnPointers[i] = &columns[i]
	}
	for rows.Next() {
		// Scan the result into the column pointers...
		if err := rows.Scan(columnPointers...); err != nil {
			fmt.Println("??? Scan columnPointers failed", err)
			return nil
		}

		for i, colName := range colNames {
			rsrcMap[colNames[i]] = colName
		}

		// Create our map, and retrieve the value for each column from the pointers slice,
		// storing it in the map with the name of the column as the key.
		items := reflect.ValueOf(columns)
		if items.Kind() == reflect.Slice {
			for i := 0; i < items.Len(); i++ {
				item := items.Index(i)
				v := reflect.Indirect(item)
				val := fmt.Sprint(v.Interface())
				//vType := ""

				if IsNumeric(val) == false {
					vvv := columnPointers[i].(*interface{})
					//vType = "STRING"
					//var v1 string
					//v1 = fmt.Sprint(v.Interface())

					var vstr *string
					val = fmt.Sprintf("%s", *vvv)
					vstr = &val
					if strings.Contains(*vstr,"%!s") {
						rsrcMap[colNames[i]] = val // nil
						//fmt.Println("YTYPE=", vType, "  VAL=", val," KEY=", colNames[i], " *val=", *vstr)
					} else {
						rsrcMap[colNames[i]] = val
						//fmt.Println("XTYPE=", vType, "  VAL=", val, " KEY=", colNames[i], " *val=", *vstr)
					}
				} else {
					//vType = "INTEGR"
					rsrcMap[colNames[i]] = val
					//fmt.Println("ZTYPE=", vType, "  VAL=", val, " KEY=", colNames[i])
				}


				// fmt.Println("TYPE=", vType, "  VAL=", val, " KEY=", colNames[i])
			}
		}
	}
	//fmt.Println("UNPACK ROWS INTO MAP DONE")
	return rsrcMap
}
type cType interface {
	colString() string
}
type intArrayT struct {
	value [] int
}
type stringT struct {
	value string
}
func (s intArrayT) intString() string {
	return IntToString1(s.value)
}
func (s stringT) intString() string {
	return s.value
}
//func (intArrayT) colString()
func getTypeString(vvv cType) string {
    return vvv.colString()
}
func IntToString1(a []int) string {
	//a := []int{1, 2, 3, 4, 5}
	b := ""
	//b:= vin
	for _, v := range a {
		b += strconv.Itoa(v)
	}

	return b
}
//====================================================================================
//# Get me a secret key!
//====================================================================================
func TBGenSecretKey() string {
	// key :=`/bin/dd if=/dev/urandom count=128 bs=1 2> /dev/null | /sbin/md5`
	// chomp($key);
	key := GenerateToken("goran")
	return key
}
// GenerateToken returns a unique token based on the provided email string
func GenerateToken(email string) string {
	hash, err := bcrypt.GenerateFromPassword([]byte(email), bcrypt.DefaultCost)
	if err != nil {
		log.Fatal(err)
	}
	fmt.Println("Hash to store:", string(hash))

	hasher := md5.New()
	hasher.Write(hash)
	return hex.EncodeToString(hasher.Sum(nil))
}
