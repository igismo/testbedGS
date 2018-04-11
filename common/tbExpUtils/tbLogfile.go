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
	"io/ioutil"
	"fmt"
	"os"
	//"log"
	"testbedGS/common/tbDbaseUtils"
	"time"
	"testbedGS/common/tbConfiguration"
	"strconv"
	"path/filepath"
	"io"
)

//======================================================================
//
//======================================================================
func ExpSetLogFile(db *sql.DB, exp *ExperimentType,
					logfile map[string]map[string]interface{}) bool {

	logid := logfile["LOGFILE"]["logid"].(string)

	// # Kill the old one. Eventually we will save them.
	oldlogfile := expGetLogFile(db, logfile)
	if oldlogfile != nil {
		LogDelete(db, oldlogfile, true);
	}
	logid = logfile["LOGFILE"]["logid"].(string)
	var logentry   =  map[string] string{
		"logfile" 		: logid,}

	ExpUpdate(db, exp, logentry)

	return true

}

//======================================================================
//
//======================================================================
func ExpCreateLogFile(db *sql.DB,exp * ExperimentType,
				prefix string) map[string]map[string]interface{} {

	pid := exp.EXPT["pid"].(string)
	eid := exp.EXPT["eid"].(string)
	logname := WorkDir(pid, eid) + "/" + prefix + ".log"
	fmt.Println("ExpCreateLogFile prefix=",prefix, "  logname=", logname)

	f , err  := ioutil.TempFile(WorkDir(pid, eid), prefix)
	if err != nil {
		fmt.Println("Failed to creaate temp file")
		return nil
	}

	// Create a Logfile.
	templogname := f.Name()
	logfiles := LogCreate(db, exp, templogname )

	// So tbops people can read the files ...
	if  false {// !chmod(0664, $logname) {
		fmt.Println("Could not chmod $logname to 0644: $!\n")
		return nil
	}

	fmt.Println("ExpCreateLogFile: link logname=", logname, " TO templogname=", templogname)
	err = os.Symlink(templogname, logname)
	if err != nil {
		fmt.Println("Failed to link log file to templogname" + templogname, " ERROR=", err)
	}
	return logfiles
}


//======================================================================
// Create a new logfile for the experiment. We are given the optional
// filename, otherwise generate one.
//======================================================================
func LogCreate(db *sql.DB, exp * ExperimentType,
	filename string) map[string]map[string]interface{} {
	fmt.Println("LogCreate: START file=", filename)
	gid_idx := exp.EXPT["gid_idx"].(string)

	// filename = TBMakeLogname("logfile") if (!defined($filename));
	// Plain secret key, which is used to reference the file.
	logid := TBGenSecretKey()
	t     := time.Now()
	tString := fmt.Sprintf("%d-%02d-%02d %02d:%02d:%02d",
		t.Year(), t.Month(), t.Day(), t.Hour(), t.Minute(), t.Second())

	_,_,err := tbDbaseUtils.DBtransaction("LogCreate",db,"insert into logfiles set logid='" +
		logid + "', isopen=0, filename='" + filename + "', " +
		" gid_idx='" + gid_idx + "', " + " date_created='" + tString +"'")
	if err != nil {
		return nil
	}

	return LogLookup(db, logid)
}

//======================================================================
func LogLookup(db *sql.DB, logid string) map[string]map[string]interface{} {
	/*
+--------------+-----------------------+------+-----+---------+-------+
| Field        | Type                  | Null | Key | Default | Extra |
+--------------+-----------------------+------+-----+---------+-------+
| logid        | varchar(40)           | NO   | PRI |         |       |
| filename     | tinytext              | YES  |     | NULL    |       |
| isopen       | tinyint(4)            | NO   |     | 0       |       |
| gid_idx      | mediumint(8) unsigned | NO   |     | 0       |       |
| date_created | datetime              | YES  |     | NULL    |       |
+--------------+-----------------------+------+-----+---------+-------+
	*/
	fmt.Println("LogLookup: START")
	rows,err := tbDbaseUtils.DBselectQuery("LogLookup",db,
		"select * from logfiles where logid='" +logid+ "'")
	if err != nil {
		if rows != nil {rows.Close()}
		return nil
	}
	var logMap = UnpackRowsIntoMap("logfiles",rows)
	if rows != nil {rows.Close()}

	var logfile = make(map[string]map[string]interface{},1)
	logfile["LOGFILE"] = logMap

	return logfile
}
//======================================================================
//# Refresh a LOG instance by reloading from the DB.
//======================================================================
func LogRefresh(db *sql.DB, logfile map[string]map[string]interface{}) bool {

	logid := logfile["LOGFILE"]["logid"].(string)

	rows,err := tbDbaseUtils.DBselectQuery("LogRefresh",db,
		"select * from logfiles where logid='" + logid + "'")
	if err != nil {
		if rows !=  nil {rows.Close()}
		return false
	}
	var logMap = UnpackRowsIntoMap("logfiles",rows)
	if rows !=  nil {rows.Close()}

	logfile["LOGFILE"] = logMap

	return true
}

//======================================================================
//# Mark a file open so that the web interface knows to watch it.
//======================================================================
func LogOpen(db *sql.DB, logfile map[string]map[string]interface{}) bool {

	logid := logfile["LOGFILE"]["logid"].(string)

	_,_,err := tbDbaseUtils.DBtransaction("LogOpen",db,
		"update logfiles set isopen=1 where logid='" + logid + "'")
	if err != nil {
		return false
	}

	return LogRefresh(db, logfile)
}
//======================================================================
//
//======================================================================
func expGetLogFile(db *sql.DB,
	logfile map[string]map[string]interface{}) map[string]map[string]interface{} {
	LogRefresh(db, logfile)
	logid := logfile["LOGFILE"]["logid"].(string)
	return LogLookup(db, logid)
}

//======================================================================
//# And close it ...
//======================================================================
func ExpCloseLogFile(db *sql.DB,
	logfile map[string]map[string]interface{})  bool {

	logfile = expGetLogFile(db, logfile);

	return LogfileClose(db, logfile)
}

//======================================================================
//# Mark file closed, which is used to stop the web interface from spewing.
//======================================================================
func LogfileClose(db *sql.DB, logfile map[string]map[string]interface{})  bool {

	logid := logfile["LOGFILE"]["logid"].(string)

	_,_, err := tbDbaseUtils.DBtransaction("LogfileClose",db,
		"update logfiles set isopen=0 where logid='" + logid +"'")
	if err != nil {
		fmt.Println("LogfileClose: Failed to Close")
		return false
	}

	return LogRefresh(db, logfile);
}

//======================================================================
//# AccessCheck. The user needs to be a member of the group that
// the logfile was created in.
//======================================================================
func  LogAccessCheck(user string) bool{
/* TODO - ....
	group = Group->Lookup($self->gid_idx());

	if (!defined($group))  return 0

	//# Membership in group.

	if (defined($group->LookupUser($user)))  return 1
*/
	return true
}
//======================================================================
//# Delete a logfile record. Optionally delete the logfile too.
//======================================================================
func LogDelete(db *sql.DB,logfile map[string]map[string]interface{},
										delete  bool) bool {

	logid := logfile["LOGFILE"]["logid"].(string)
	// filename := logfile["LOGFILE"]["filename"].(string)

	if delete == true {
		//unlink($filename);   TODO
	}

	_,_,err := tbDbaseUtils.DBtransaction("LogDelete",db,
		"delete from logfiles where logid='" + logid + "'")
	if err != nil {
		return false
	}

	return true
}

//======================================================================
//# Copy log files to long term storage.
//======================================================================
func ExpSaveLogFiles(db *sql.DB, exp *ExperimentType) {

	pid := exp.EXPT["pid"].(string)
	eid := exp.EXPT["eid"].(string)
	workdir := WorkDir(pid, eid)
	//userdir := UserDir(pid,eid)
	logdir  := TBExptLogDir(db, pid, eid);

	//# What the hell is this file! Very annoying.
	// if (-e "$workdir/.rnd") {mysystem("/bin/rm -f $workdir/.rnd");}

	err := CopyDir(workdir + "/", logdir)
	if err != nil {
		fmt.Println("Dir Copy ERROR" )
	}
	return
}

//======================================================================
//# Copy log files to user visible space. Maybe not such a good idea anymore?
//======================================================================
func ExpCopyLogFiles(exp *ExperimentType) bool {

	pid := exp.EXPT["pid"].(string)
	eid := exp.EXPT["eid"].(string)
	workdir := WorkDir(pid, eid)
	userdir := UserDir(pid,eid)

	CopyDir(workdir, userdir + "/tbdata")

	return true
}


//======================================================================
//# Return the working directory name for an experiment. This is where
//# the scripts work. The logs are copied over to the user's version of
//# the directory later.
//======================================================================
func TBExptLogDir(db *sql.DB, pid, eid string) string {

	rows, err := tbDbaseUtils.DBselectQuery("TBExptLogDir",db, "select idx from experiments " +
			"where pid='" + pid + "' and eid='" + eid + "'")
	if err != nil || rows == nil{
		if rows != nil {rows.Close()}
		return ""
	}
	var idx int64
	if rows.Next() {
		rows.Scan(&idx)
	}
	if rows != nil {rows.Close()}
	return tbConfig.TB + "/expinfo/" + pid + "/" + eid + "/" + strconv.FormatInt(idx,10)
}

// CopyDir recursively copies a directory tree, attempting to preserve permissions.
// Source directory must exist, destination directory must *not* exist.
// Symlinks are ignored and skipped.
func CopyDir(src string, dst string) (err error) {
	src = filepath.Clean(src)
	dst = filepath.Clean(dst)

	si, err := os.Stat(src)
	if err != nil {
		return err
	}
	if !si.IsDir() {
		return fmt.Errorf("source is not a directory")
	}

	_, err = os.Stat(dst)
	if err != nil && !os.IsNotExist(err) {
		return
	}
	if err == nil {
		return fmt.Errorf("destination already exists")
	}

	err = os.MkdirAll(dst, si.Mode())
	if err != nil {
		return
	}

	entries, err := ioutil.ReadDir(src)
	if err != nil {
		return
	}

	for _, entry := range entries {
		srcPath := filepath.Join(src, entry.Name())
		dstPath := filepath.Join(dst, entry.Name())

		if entry.IsDir() {
			err = CopyDir(srcPath, dstPath)
			if err != nil {
				return
			}
		} else {
			// Skip symlinks.
			if entry.Mode()&os.ModeSymlink != 0 {
				continue
			}

			err = CopyFile(srcPath, dstPath)
			if err != nil {
				return
			}
		}
	}

	return
}

// CopyFile copies the contents of the file named src to the file named
// by dst. The file will be created if it does not already exist. If the
// destination file exists, all it's contents will be replaced by the contents
// of the source file. The file mode will be copied from the source and
// the copied data is synced/flushed to stable storage.
func CopyFile(src, dst string) (err error) {
	in, err := os.Open(src)
	if err != nil {
		return
	}
	defer in.Close()

	out, err := os.Create(dst)
	if err != nil {
		return
	}
	defer func() {
		if e := out.Close(); e != nil {
			err = e
		}
	}()

	_, err = io.Copy(out, in)
	if err != nil {
		return
	}

	err = out.Sync()
	if err != nil {
		return
	}

	si, err := os.Stat(src)
	if err != nil {
		return
	}
	err = os.Chmod(dst, si.Mode())
	if err != nil {
		return
	}

	return
}
