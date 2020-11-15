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
package main

import (
	"database/sql"
	"testbedGS/common/tbDbaseUtils"
	"testbedGS/common/tbExpUtils"
	"fmt"
	"strconv"
	"io/ioutil"
	"time"
	"strings"
)
// "BACKUP/source/golang.org/x/tools/go/gcimporter15/testdata"

//====================================================================================
//
//====================================================================================
func NewUUID() []byte {
	// 	"github.com/leonelquinteros/gorand"
	// "github.com/satori/go.uuid"
	uuid, _ := ioutil.ReadFile("/proc/sys/kernel/random/uuid")
	return uuid
}
//====================================================================================
// Create a new experiment. This installs the new record in the DB,
// and returns an instance. There is some bookkeeping along the way.
//====================================================================================
func expCreateExp(db *sql.DB, group map[string]interface{}, eid string,
				expArgs map[string]string, expEnv map[string]string) (*tbExpUtils.ExperimentType, string) {
	fmt.Println("expCreateExp: "," pid=",string(expArgs["pid"]),"  GIDSTRING=", group["gid"].(string))
	var pid     = string(expArgs["pid"])
	var gid     = group["gid"].(string)

	var pid_idx = group["pid_idx"].(string)
	var gid_idx = group["gid_idx"].(string)
	var uuid = ""

	// FIRST LOCK ALL RELEVANT TABLES TO AVOID RACE CONDITION
	tbDbaseUtils.DbunlockTables("expCreateExp", db);
	_,err := tbDbaseUtils.DblockTables("expCreateExp", db," experiments write, " +
		"            experiment_stats write, " +
		"            experiment_resources write, " +
		"            emulab_indicies write, " +
		"            testbed_stats read")
	if err != nil {
		tbDbaseUtils.DbunlockTables("expCreateExp", db);
		return nil, "Failed to lock the tables"
	}

	eRows,err := tbDbaseUtils.DBselectQuery("expCreateExp",db,"select pid,eid from experiments " +
		"where eid='" + eid + "' and pid='" + pid + "'")
	eRows.Close()
	if err != nil {
		tbDbaseUtils.DbunlockTables("expCreateExp", db);
		fmt.Println("Experiment " + eid + "in project " + pid + " already exists!")
		return nil, "Experiment " + eid + " in project " + pid + " already exists!"
	}

	// GET NEXT EXPTIDX FOR THE EXPERIMENT
	exptidx, errMsg := expGetNextIdx(db)
	if errMsg != "" {
		tbDbaseUtils.DbunlockTables("expCreateExp", db);
		fmt.Println(errMsg)
		return nil, "Failed to get exptidx"
	}

	//# And a UUID (universally unique identifier).
	if expArgs["eid_uuid"] != "" {
		uuid = expArgs["eid_uuid"]
		delete(expArgs,"eid_uuid")
	} else {
		uuid = string(NewUUID())
	}

	//# Lets be real sure that the UUID is really unique.
	queryUUID,err := tbDbaseUtils.DBselectQuery("expCreateExp",db,
		"select pid,eid from experiments where eid_uuid='" + uuid + "'")
	if errMsg != "" {
		if queryUUID != nil {queryUUID.Close()}
		tbDbaseUtils.DbunlockTables("expCreateExp", db);
		fmt.Println("Failed to read experiments table")
		return nil, "Failed to read experiments table"
	}

	if  queryUUID != nil && queryUUID.Next() {
		queryUUID.Close()
		tbDbaseUtils.DbunlockTables("expCreateExp", db);
		fmt.Println("Experiment uuid " + uuid + " already exists; this is bad!");
		return nil, "Experiment uuid " + uuid + "already exists; this is bad!"
	}
	if queryUUID != nil {queryUUID.Close()}

	//# Insert the record. This reserves the pid/eid for us.
	//# Some fields special cause of quoting.
	// TODO : DELETE ALL OTHER EXTRA FIELDS FROM ARGS
	fmt.Println("expCreateExp: Insert EXPERIMENT record. This reserves the pid/eid for us.")
	description := expEnv["expt_name"]
	delete(expEnv, "expt_name")
	noswap_reason := expEnv["noswap_reason"]
	delete(expEnv, "noswap_reason")
	noidleswap_reason := expEnv["noidleswap_reason"]
	delete(expEnv, "noidleswap_reason")
	delete(expEnv, "idx") //# we override this below

	all := ""
	for key := range expEnv {
		value := expEnv[key]
		// We get this from group map - TODO - make this right ....
		if key =="gid" {continue}
		if key =="eid" {continue}
		if key =="pid" {continue}
		if all == "" {
			all += key + "='" + value + "'"
		} else {
			all += "," + key + "='" + value + "'"
		}
	}

	t     := time.Now()
	tString := fmt.Sprintf("%d-%02d-%02d %02d:%02d:%02d",
		         t.Year(), t.Month(), t.Day(), t.Hour(), t.Minute(), t.Second())
	var query = "insert into experiments set " + all
	///# Append the rest
	uuidTrimmed :=strings.TrimSuffix(uuid, "\n") // make sure no trailing CR/LF
	query += ",expt_created='" + tString + "'"
	query += ",expt_locked=now(),pid='"+pid+"',eid='"+eid+"',eid_uuid='"+uuidTrimmed+"'"
	query += ",pid_idx='"+pid_idx+"',gid='"+gid+"',gid_idx='"+gid_idx+"'"
	query += ",expt_name='" + description + "'"
	query += ",noswap_reason='" + noswap_reason+ "'"
	query += ",noidleswap_reason='" + noidleswap_reason+ "'"
	query += ",idx=" + strconv.FormatInt(exptidx,10)

	_,_,err1 := tbDbaseUtils.DBtransaction("expCreateExp",db,query)
	if err1 != nil {
		tbDbaseUtils.DBtransaction("expCreateExp",db,
			   "delete from experiments where pid='" + pid + "' and eid='" +eid+ "'");
		tbDbaseUtils.DbunlockTables("expCreateExp", db);

		return nil, "Error inserting experiment record for " + pid + "/" + eid
	}

	creator_uid := string(expEnv["expt_head_uid"])
	creator_idx := string(expEnv["creator_idx"])
	batchmode   := string(expEnv["batchmode"])

	//# Create an experiment_resources record for the above record.
	//=============================================================
	fmt.Println("expCreateExp: Create an experiment_resources record")
	rsrcidx,_,err2 := tbDbaseUtils.DBtransaction("expCreateExp",db,"insert into experiment_resources "+
			"(tstamp, exptidx, uid_idx) values ('" + tString +
				"', " + strconv.FormatInt(exptidx,10) + ", " + creator_idx + ")")
	if err2 != nil {
		tbDbaseUtils.DBtransaction("expCreateExp",db,
			"delete from experiments where pid='" + pid + "' and eid='" +eid + "'")
		tbDbaseUtils.DbunlockTables("expCreateExp", db);
		return nil, "Error inserting experiment_resources record" + pid + "/" + eid
	}
	//# Now create an experiment_stats record to match.
	//=======================================================
	//eid_uuidTrimmed := strings.TrimSuffix(eid_uuid, "\n")
    valuesString := "'" + eid + "', '" + pid + "', '" + creator_uid +
		"', '" + creator_idx + "','" + gid + "', '" + tString + "', " +
		batchmode + ", " + strconv.FormatInt(exptidx,10) + ", " +
		strconv.FormatInt(rsrcidx, 10) + ", " + pid_idx + ", " +
		gid_idx + ", '"+ uuidTrimmed + "', '" + tString + "')"

	fmt.Println("expCreateExp: Create an experiment_stats record")
	_,_,err3 := tbDbaseUtils.DBtransaction("expCreateExp",db,"insert into experiment_stats " +
		"(eid,pid,creator,creator_idx,gid,created,batch,exptidx,rsrcidx,pid_idx, gid_idx," +
			"eid_uuid, last_activity) values(" + valuesString)
	if err3 != nil {
		tbDbaseUtils.DBtransaction("expCreateExp",db,"delete from experiments where pid='" +
									pid +"' and eid='" + eid + "'")
		tbDbaseUtils.DBtransaction("expCreateExp",db,"delete from experiment_resources " +
									"where idx=" + string(rsrcidx) )
		tbDbaseUtils.DbunlockTables("expCreateExp", db);
		fmt.Println("Error inserting experiment stats record for " + pid + "/" + eid)
		return nil, "Error inserting experiment stats record for " + pid + "/" + eid
	}

	//# Safe to unlock; all tables consistent.
	_,err4 := tbDbaseUtils.DbunlockTables("expCreateExp", db);
	if err4 != nil {
		tbDbaseUtils.DBtransaction("expCreateExp",db,
							"delete from experiments where pid='" + pid + "' and eid='" + eid + "'");
		tbDbaseUtils.DBtransaction("expCreateExp",db,
								"delete from experiment_resources where idx=" + string(rsrcidx))
		tbDbaseUtils.DBtransaction("expCreateExp",db,
						"delete from experiment_stats where exptidx=" + string(exptidx));
		fmt.Println("Error unlocking tables!");
		return nil,"Failed to unlock tables"
	}
	fmt.Println("ALMOST THERE - START ExpLookup")
	expReturn := tbExpUtils.ExpLookup(db, pid, eid, "");

	return expReturn, ""

}
//====================================================================================
//
//====================================================================================
func expGetNextIdx(db *sql.DB) (int64 , string) {
	var exptidx int64
	//-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	// Grab the next highest index to use. We used to use an auto_increment
	// field in the table, but if the DB is ever "dropped" and recreated,
	// it will reuse indicies that are crossed referenced in the other two tables.
	fmt.Println("START expGetNextIdx")

	tbDbaseUtils.DbuseDB("expGetNextIdx", db, "tbdb")

	queryIdx,err := tbDbaseUtils.DBselectQuery("expGetNextIdx",db,
		"select idx from emulab_indicies where name='next_exptidx'")
	if err != nil {
		if queryIdx != nil {queryIdx.Close()}
		fmt.Println("expGetNextIdx select idx from emulab_indicies failed")
		tbDbaseUtils.DbunlockTables("expGetNextIdx", db);
		return -1, "failed to read emulab_indicies"
	}

	fmt.Println("After select: exptidx=", exptidx, "  queryIDX=", queryIdx)
	//# Seed with a proper value.
	exptidx = 0;
	if 	queryIdx.Next() {queryIdx.Scan(&exptidx)} else{
		fmt.Println("expGetNextIdx no Next for queryIdx")
	}

	fmt.Println("After Scan: exptidx=", exptidx, "  queryIDX=", queryIdx)
	//#
	//for queryIdx.Next() {
	//	queryIdx.Scan(&exptidx)
	//}
	if  exptidx == 0{
		if queryIdx != nil {queryIdx.Close()}
		fmt.Println("??????????  WE SHOULD ONLY BE HERE FIRST TIME ARROUND ???????")
		queryMax,err := tbDbaseUtils.DBselectQuery("expGetNextIdx",
			         db,"select MAX(exptidx) + 1 from experiment_stats");;
		if err != nil {
			if queryMax != nil {queryMax.Close()}
			tbDbaseUtils.DbunlockTables("expGetNextIdx", db)
			return -1, "Failed to read experiment_stats table"
		}
		if  queryMax != nil && queryMax.Next()  {
			queryMax.Scan(&exptidx)
		} else {
			fmt.Println("MAX index exptid ... queryMax.Next FAILED")
		}
		if queryMax != nil {queryMax.Close()}

		fmt.Println("MAX index exptid=", exptidx)
		if exptidx == 0 {
			exptidx = 1
		}

		_,_, err1 := tbDbaseUtils.DBtransaction("expGetNextIdx",db,
								"insert into emulab_indicies (name, idx) " +
			"values ('next_exptidx', " + strconv.FormatInt(exptidx, 10) + ")")
		//_, err1 := tbDbaseUtils.TbDbQuery(db,"update emulab_indicies set " +
		//	"idx=" + strconv.FormatInt(exptidx, 10) + " where name='next_exptidx'")
		if err1 != nil {
			tbDbaseUtils.DbunlockTables("expGetNextIdx", db);
			// GORAN return -1, "FAiled to insert into emulab_indicies"
		}
	} else {
		fmt.Println("??????????  WE ARE AT THE GOOD PLACE ")
		if queryIdx != nil && queryIdx.Next() {
			queryIdx.Scan(&exptidx)
		}
		if queryIdx != nil {queryIdx.Close()}
	}

	nextidx := exptidx + 1

	_,_, err2 := tbDbaseUtils.DBtransaction("expGetNextIdx",db,"update emulab_indicies set idx='" +
		strconv.FormatInt(nextidx, 10) + "' where name='next_exptidx'")
	if err2 != nil {
		tbDbaseUtils.DbunlockTables("expGetNextIdx", db)
		return -1, "Failed to update emulab_indicies table"
	}

	var exptidxTables = []string   {"experiments", "experiment_stats",
									"experiment_resources", "testbed_stats",}
	//# Lets be really sure!
	for _,table := range  exptidxTables {
		slot := "exptidx"
		if table == "experiments" {
			slot = "idx"
		}

		queryIdx,err := tbDbaseUtils.DBselectQuery("expGetNextIdx",db,
			"select * from " + table + " where " +
							slot + "=" + strconv.FormatInt(exptidx,10))
		if err != nil {
			if queryIdx != nil {queryIdx.Close()}
			tbDbaseUtils.DbunlockTables("expGetNextIdx", db)
			return -1, "Failed to read table:" + table
		}

		if queryIdx != nil && queryIdx.Next() {
			tbDbaseUtils.DbunlockTables("expGetNextIdx", db)
			return -1, "Experiment index " + strconv.FormatInt(exptidx, 10) +
								" exists in " + table + " ...this is bad!"
		}
		if queryIdx != nil {queryIdx.Close()}
	}

	return exptidx, ""
}
//====================================================================================
//  Once all creation is OK, go and create a DB record
//====================================================================================
func expDbRecordCreate(db *sql.DB, exp *tbExpUtils.ExpType, group *Group) (*tbExpUtils.ExpType) {

	//-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	// LOCK: make all tables consistent.
	//-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	// The pid/eid has to be unique, so lock the table for the check/insert.
	tbDbaseUtils.DBtransaction("expDbRecordCreate",db,
		"lock tables experiments write, experiment_stats write, "+
			" experiment_resources write,  emulab_indicies write, "+
			" testbed_stats read")

	queryExp, _ := tbDbaseUtils.DBselectQuery("expDbRecordCreate",db, "select pid,eid from experiments "+
		"where eid='"+ exp.Eid + "' and pid='"+ exp.Pid + "'")

	if queryExp != nil && queryExp.Next() == true {
		if queryExp != nil {queryExp.Close()}
		tbDbaseUtils.DbunlockTables("expDbRecordCreate", db);
		fmt.Println("Experiment eid in project pid already exists!")
		return nil
	}
	if queryExp != nil {queryExp.Close()}
	//-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	// EXPERIMENTS: Add experiment to the experiments table
	//-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	query := "insert into experiments set "

	// join(",", map("$_='" . $argref->{$_} . "'", keys(%{$argref})));
	// ..... insert all other ExpArgs fields

	t     := time.Now()
	tString := fmt.Sprintf("%d-%02d-%02d %02d:%02d:%02d",
		t.Year(), t.Month(), t.Day(), t.Hour(), t.Minute(), t.Second())
	// Append the rest
	query += ",expt_created=FROM_UNIXTIME('" + tString + "')"
	query += ",expt_locked=now(),pid='" +group.pid+ "',eid='" + exp.Expt_name + "',eid_uuid='" + group.gid_uuid+ "'"
	query += ",pid_idx='" + strconv.FormatInt(group.pid_idx,10) + "',gid='" +  group.gid +
				"',gid_idx='" + strconv.FormatInt(group.gid_idx,10) + "'"
	query += ",expt_name='" + group.description + "'"
	query += ",noswap_reason='" + exp.Noswap_reason + "'"
	query += ",noidleswap_reason='" + exp.Noidleswap_reason + "'"
	query += ",idx=" + strconv.FormatInt(exp.Instance_idx,10) // WAS exptidx

	_,_,err := tbDbaseUtils.DBtransaction("expDbRecordCreate",db,query)
 	if err != nil {
		tbDbaseUtils.DbunlockTables("expDbRecordCreate", db)
		return nil // "Error inserting experiment record for"
	}

	//-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	// EXPERIMENT_RESOURCES: Create an experiment_resources record for the above record.
	//-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	lastIdx,_,err := tbDbaseUtils.DBtransaction("expDbRecordCreate", db,
			"insert into experiment_resources " + "(tstamp, exptidx, uid_idx) + " +
			 "values (FROM_UNIXTIME('" + tString  + "), " + strconv.FormatInt(exp.Instance_idx,10) +
				", " + strconv.Itoa(exp.Creator_idx) + ")")
	if err != nil   {
		tbDbaseUtils.DBtransaction("expDbRecordCreate",db,
					"delete from experiments where pid='$pid' and eid='$eid'");
		tbDbaseUtils.DbunlockTables("expDbRecordCreate", db)

		return nil
	}

	//-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	// EXPERIMENT_STATS: Now create an experiment_stats record to match.
	//-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	lastIdx,_,err6 := tbDbaseUtils.DBtransaction("expDbRecordCreate",db,"insert into experiment_stats " +
			"(eid, pid, creator, creator_idx, gid, created, " +
			" batch, exptidx, rsrcidx, pid_idx, gid_idx, eid_uuid, last_activity, lastrsrc) " +
			"values('$eid', '$pid', '" + exp.Expt_head_uid + "', '" + strconv.Itoa(exp.Creator_idx) + "'," +
			" '$gid', FROM_UNIXTIME('" + tString + "'), " + strconv.Itoa(exp.Batchmode) + ", " +
			strconv.FormatInt(exp.Instance_idx,10) + ", " + strconv.FormatInt(lastIdx,10) + ", " +
			"$pid_idx, $gid_idx, '" + group.gid_uuid + "', '" +  tString + "', 0")
	if err6 != nil {
		tbDbaseUtils.DBtransaction("expDbRecordCreate",db,
						"delete from experiments where pid='$pid' and eid='$eid'");
		tbDbaseUtils.DBtransaction("expDbRecordCreate",db,"delete from experiment_resources where idx=" +
									strconv.FormatInt(lastIdx,10))
		tbDbaseUtils.DbunlockTables("expDbRecordCreate", db)

		return nil
	}

	//-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	// UNLOCK: Safe to unlock; all tables consistent.
	//-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	lastIdx,_,err5 := tbDbaseUtils.DBtransaction("expDbRecordCreate",db,"unlock tables")
	if err5 != nil {
		tbDbaseUtils.DBtransaction("expDbRecordCreate",db,
								"delete from experiments where pid='$pid' and eid='$eid'");
		tbDbaseUtils.DBtransaction("expDbRecordCreate",db,"delete from experiment_resources where idx=" +
									strconv.FormatInt(lastIdx,10))
		tbDbaseUtils.DBtransaction("expDbRecordCreate",db,
								"delete from experiment_stats where exptidx=$exptidx")
		tbDbaseUtils.DbunlockTables("expDbRecordCreate", db)

		return nil
	}
	tbDbaseUtils.DbunlockTables("expDbRecordCreate", db)
	return exp
}
