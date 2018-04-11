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
	"testbedGS/common/tbConfiguration"
	"strconv"
	//"os/exec"
	"os"
	//"log"
	"testbedGS/common/tbDbaseUtils"
	"fmt"
	"testbedGS/common/tbExpUtils"
	"path/filepath"
	//"flag"
	"os/exec"
	"log"
	"strings"
	"bytes"
	"time"
)


type Group struct {
	pid string           // DeterTest
	gid string			 // DeterTest
	pid_idx int64			 // 10001
	gid_idx  int64		 // 10001
	gid_uuid string		 // 4b3763c0-82da-11e7-842e-000c29a32a57
	leader string		 // scuric
	leader_idx int       // 10002
	created string       // 2017-08-16 13:55:45
	description string   // Default Group
	unix_gid int         // 6002
	unix_name string     // DeterTest
	expt_count int       // 21
	expt_last  string    // 2017-10-27
	wikiname   string		// NULL
	mailman_password string // NULL
}

func TBGenSecretKey() string {
	// key=`/bin/dd if=/dev/urandom count=128 bs=1 2> /dev/null | /sbin/md5`;
	key := "1234567890"
	return key
}
//====================================================================================
// Create the directory structure. A template_mode experiment is the one
// that is created for the template wrapper, not one created for an
// instance of the experiment. The path changes slightly, although that
// happens down in the mkexpdir script.
//====================================================================================
//cmd := exec.Command(tbConfig.TBDIR + "/libexec/mkexpdir ", strconv.FormatInt(idx,10))
//cmd.Stdout = os.Stdout
//cmd.Stderr = os.Stderr
//log.Println(cmd.Run())
// cmd1 := exec.Command("ls","-l","/proj")
func expCreateDirectory(db * sql.DB, exp *tbExpUtils.ExperimentType,
	                    pid, eid string) error {
	// idx := exp.Idx
	//var expDir = tbConfig.TBDB_PROJDIR + "/" + pid + "/exp/"
	fmt.Println("expCreateDirectory: START")
	expDir := filepath.Join(tbConfig.TBDB_PROJDIR, pid + "/exp")
	eidDir := filepath.Join(expDir, eid)
	fmt.Println("expCreateDirectory: START expDir=", expDir, "  eidDir=", eidDir)
	// var eidlink   = "$projroot/$pid/exp/$eid";
	var workdir   = TBExptWorkDir(pid, eid)
	var expinfo   = tbExpUtils.ExpInfoDir(pid, eid, exp.EXPT["idx"].(string))
	fmt.Println("expCreateDirectory: workdir=", workdir , "  exinfo=", expinfo)
	err := os.MkdirAll(eidDir, 0770)  //os.ModePerm)
	//os.Mkdir("." + string(filepath.Separator) + "xyz",0770);
	if err != nil {
		return err
	}
	fmt.Println("expCreateDirectory: make all directories")
	for _,dir := range  tbConfig.ExpDirList {
		fmt.Println("expCreateDirectory: mkdir ", eidDir + string(filepath.Separator) + dir)
		dirPath := eidDir + string(filepath.Separator) + dir
		//if _, err := os.Stat(dirPath); os.IsNotExist(err) {
			os.MkdirAll(dirPath, 0770)
		//}
		//if err != nil  {
		//	fmt.Println("expCreateDirectory: FAILED doing mkdir ",
		//		eidDir + string(filepath.Separator) + dir, "  ERROR=", err)
		//	return err
		//}
	}
	fmt.Println("expCreateDirectory: call ExpUpdate eidDir=", eidDir)
	//# Update the DB. This leaves the decision about where the directory
	//# is created, in this script.
	arg := map[string]string {"path" : eidDir }
	err = tbExpUtils.ExpUpdate(db, exp, arg)
	if err != nil {
		fmt.Println("Could not update path for experiment");
	}
	fmt.Println("expCreateDirectory: Create Working Dir=", workdir)
	//# Create the working directory.
	err = os.MkdirAll(workdir, 0775)
	if err != nil {
		return err
	}
	fmt.Println("expCreateDirectory: Create expinfo  Dir=", expinfo)
	//# Create the expinfo directory.
	err = os.MkdirAll(expinfo, 0770)
	if err != nil {
		return err
	}

	//# expinfo dir should have the group ID of the primary project group.
	//# This is because at different times, users in different subgroups can
	//# create an experiment with the same name. If the directory has the
	//# group of the initial experiment with that name, then any other future
	//# experiment with that name but in a different subgroup will not be able
	//# to write the directory.

	//# If a group experiment, leave behind a symlink from the project experiment
	//# directory to the group experiment directory. This is convenient so that
	//# there is a common path for all experiments.
	fmt.Println("expCreateDirectory: DONE")

	return nil
}

//======================================================================
func expUpdate(argref *tbExpUtils.ExpType) {
	/*
	exptidx := argref.exptidx
	idx     := argref.idx

	query := "update experiment_runs set ".
	join(",", map("$_='" . $argref->{$_} . "'", keys(%{$argref})));
	query += " where exptidx='$exptidx' and idx='$idx'";

	DBQueryWarn(query)

	return Refresh($self)
	*/
}

//====================================================================================
//# Exit codes are important; they tell the web page what has happened so
//# it can say something useful to the user. expFatal errors are mostly done
//# with die(), but expected errors use this routine. At some point we will
//# use the DB to communicate the actual error.
//#
//# $status < 0 - expFatal error. Something went wrong we did not expect.
//# $status = 0 - Everything okay.
//# $status > 0 - Expected error. User not allowed for some reason.
//====================================================================================
func expCreateUsage() {
fmt.Println(
"Usage: batchexp [-q] [-i [-w]] [-n] [-f] [-N] [-E description] [-g gid]\n" +
"                [-S reason] [-L reason] [-a <time>] [-l <time>]\n" +
"                -p <pid> -e <eid> <nsfile>\n" +
"switches and arguments:\n" +
"-i       - swapin immediately; by default experiment is batched\n" +
"-w       - wait for non-batchmode experiment to preload or swapin\n" +
"-f       - preload experiment (do not swapin or queue yet)\n" +
"-q       - be less chatty\n"+
"-S <str> - Experiment cannot be swapped; must provide reason\n" +
"-L <str> - Experiment cannot be IDLE swapped; must provide reason\n" +
"-n       - Do not send idle email (internal option only)\n" +
"-a <nnn> - Auto swapout nnn minutes after experiment is swapped in\n" +
"-l <nnn> - Auto swapout nnn minutes after experiment goes idle\n" +
"-s       - Save disk state on swapout\n" +
"-E <str> - A pithy sentence describing your experiment\n" +
"-p <pid> - The project in which to create the experiment\n" +
"-g <gid> - The group in which to create the experiment\n" +
"-e <eid> - The experiment name (unique, alphanumeric, no blanks)\n" +
"-N       - Suppress most email to the user and testbed-ops\n" +
"<nsfile> - NS file to parse for experiment.\n")

}

//====================================================================================
// expEnvExpArgsMap(db *sql.DB, userId, pid,eid,gid,
// 				descr string) (expMap *map[string]string, envMap *map[string]string)
// Two maps are created:
//		expEnv - contains all key/value pairs that need to be saved in dBase tables
// 		expArgs   - contains all key/value pairs used by exp creation process
// This should be called by the Experiment Object to create map or expArgs as compiled
// from the user input and other configurations. The expArgs map[] is then sent to the
// Exp Creator Mgr for experiment creation. If it succedes it will create a database
// record and reply to the caller. If not nothing is created and the error msg is sent.
//====================================================================================
func expEnvExpArgsMap(db *sql.DB, userId, pid,eid,gid,
	descr string) (*map[string]string, *map[string]string) {

	var expArgs =make(map[string]string)
	var expEnv  =make(map[string]string)

	//# Verify user and get his DB uid and other info for later.
	//y $this_user = User->ThisUser();
	//user_dbid  = $this_user->dbid();
	//user_uid   = $this_user->uid();
	//user_name  = $this_user->name();
	//user_email = $this_user->email();
	fmt.Println(myName, "START expEnvexpArgsMap")//,expArgs)

	// Dump the eventkey into a file in the experiment directory.
	var Keyhash = TBGenSecretKey()
	var Eventkey = TBGenSecretKey()
	keyFilePermissions :=  os.O_RDWR|os.O_CREATE
	file1, _ := os.OpenFile(tbExpUtils.EventKeyPath(pid,eid), keyFilePermissions, 0666)
	file1.WriteString(Eventkey)
	file1.Close()
	// And dump the web key too.
	file2, _ := os.OpenFile(tbExpUtils.WebKeyPath(pid, eid),keyFilePermissions, 0666)
	file2.WriteString(Keyhash) //(webkey)
	file2.Close()

	expEnv["expt_head_uid"]     = userId  		// user_uid
	expEnv["expt_swap_uid"]     = userId  		// user_uid
	expEnv["creator_idx"]       = "0" 			// user_dbid= uid_idx
	expEnv["swapper_idx"]       = "0" 			// user_dbid= uid_idx
	expEnv["idx"]           	= "0"  			// $experiment->idx()  // exptidx
	expEnv["pid"]               = pid			// -p option
	expEnv["eid"]               = eid			// -e option
	expEnv["gid"]               = gid			// -g option
	expEnv["state"]             = "new"  		// EXPTSTATE_NEW()
	expEnv["priority"]          = "0"    		// TB_EXPTPRIORITY_LOW
	expEnv["idleswap"]          = "1"			//
	expEnv["idleswap_timeout"]  = "600" 		// -l <time> option
	expEnv["autoswap"]          = "1"			// -a <time> option
	expEnv["autoswap_timeout"]  = "600"			//
	expEnv["keyhash"]           = Keyhash		//
	expEnv["eventkey"]          = Eventkey		//
	expEnv["batchmode"]         = "1" 			// -i to set 0
	expEnv["noswap_reason"]     = "None Given"	// -S <reason> option
	expEnv["noidleswap_reason"] = "None Given"  // -L <reason> option
	expEnv["dpdb"]              = "0"           // tinyint(1) NOT NULL default '0',	(experiments)
	expEnv["dpdbname"]          =  "" 			// varchar(64) default NULL,		(experiments)
	expEnv["dpdbpassword"]      =  "" 			// varchar(64) default NULL,		(experiments)
	//`dpdbname` varchar(64) default NULL, 			(experiment_stats)
	expEnv["ipassign_args"]     =  ""
	//-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-==-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
	expArgs["expt_head_uid"]     = userId  		// user_uid
	expArgs["expt_swap_uid"]     = userId  		// user_uid
	expArgs["creator_idx"]       = "0" 			// user_dbid= uid_idx
	expArgs["swapper_idx"]       = "0" 			// user_dbid= uid_idx
	expArgs["idx"]           	= "0"  			// $experiment->idx()  // exptidx
	// GORAN added to have them around TODO NEED TO REMOVE LATER
	expArgs["user_course_pid"]  = tbExpUtils.CourseAcct(db, userId)
	expArgs["pid"]               = pid			// -p option
	expArgs["eid"]               = eid			// -e option
	expArgs["gid"]               = gid			// -g option
	expArgs["state"]             = "new"  		// EXPTSTATE_NEW()
	expArgs["priority"]          = "0"    		// TB_EXPTPRIORITY_LOW
	expArgs["idleswap"]          = "1"			//
	expArgs["idleswap_timeout"]  = "600" 		// -l <time> option
	expArgs["autoswap"]          = "1"			// -a <time> option
	expArgs["autoswap_timeout"]  = "600"			//
	expArgs["keyhash"]           = Keyhash		//
	expArgs["eventkey"]          = Eventkey		//
	expArgs["batchmode"]         = "1" 			// -i to set 0
	expArgs["noswap_reason"]     = "None Given"	// -S <reason> option
	expArgs["noidleswap_reason"] = "None Given" // -L <reason> option
	expArgs["nsfile"]            = eid + ".ns"  // TODO location of file
	expArgs["inputfile"]         = eid + ".xml" // TODO location of file
	expArgs["report"]            = "report"		//
	expArgs["quiet"]             = ""    		// -q option for 1 or -X option for 1
	expArgs["frontend"]          = ""			// -f option for 1
	expArgs["waitmode"]          = ""			// -w option
	expArgs["nonsfile"]          = "0" 			// -j option for 1
	//				// nons file: # Admin only option to activate experiment anyway.
	expArgs["logname"]           = "logname"	// ???
	expArgs["zeemode"]           = ""			// -z option for 1
	expArgs["zeeopt"]            = ""			// -z option == "-p"
	expArgs["noemail"]           = "0"  		// -N option for 1
	expArgs["xmlout"]            = "0"  		// -X option (xmlout,quiet = 1)
	expArgs["swappable"]         = "1"			// must have -S option
	expArgs["idle_ignore"]       = "0"			// -n option = 1
	expArgs["lockdown"]          = "0"  		// -k option for 1
	expArgs["batchstate"]        = tbExpUtils.BATCHSTATE_UNLOCKED()
	expArgs["linktest_level"]    = "0"			// -t <tst> option
	expArgs["savedisk"]          = "0"			// -s option
	expArgs["tempnsfile"]        = ""           // if only 1 arg at cmd line
	//				// instance (-y <inst> option ) needs template to be defined with -x option
	expArgs["instance_idx"]      = ""			// -y <instance> option to define instance
	expArgs["template"]          = ""          	// -x option (define Template->Lookup($guid, $vers)
	expArgs["guid"]              = "" 			// -x $1;
	expArgs["vers"]              = ""			// -x $2;
	expArgs["branch_template"]   = ""			//
	expArgs["branch_guid"]       = "" 			// -x $3;
	expArgs["branch_vers"]       = ""			// -x $4;
	//				// Template->Lookup($branch_guid, $branch_vers)
	expArgs["expt_name"]         = descr		// -E <descr> option
	expArgs["copyarg"]           = ""			// -c <xxx> option = xxx

	//# All of these are for experiment dup and branch. Really mucks things up.
	//# These globals are set when we parse the -c argument, but used later
	// TODO - SHOULD THIS BE DONE AT THE CREATOR MASTER (CheckCopyexpArgs())
	/* 	// -c option for copyarg
	 copyfrom           //# Copy from where, archive or current experiment.
	 copyidx            //# The index of the experiment copied.
	 copypid            //# The pid of the experiment copied.
	 copyeid            //# The eid of the experiment copied.
	 copytag            //# The archive tag to us.
	 copydir            //# Directory extracted from archive, to delete.
	*/

	fmt.Println("END expEnvexpArgsMap") //, expArgs)
	return &expArgs, &expEnv
}
//====================================================================================
// ENTRY POINT TO HANDLE EXPERIMENT CREATION
// Since this is a library call from the exp object, we will pass in pointer to exp
// structure, map or expArgs and pid,eid .... The expArgs map is created in the expexpArgs()
//# Create an experiment. The experiment is either run immediately, or placed into
//# the batch system to be run later. If no NS file is supplied, an experiment shell
//# is created (this is currently an admin only option).
//# A batch experiment is essentially preloaded (frontend mode) and then
//# dropped into the batch queue, unless the user requested only preload.
//#
//# There are two maps that need to be supplied as input:
//# expEnv - contains all key/value pairs that need to be saved in dBase tables
//# expArgs   - contains all key/value pairs used by exp creation process
// TODO : this will be executed by experiment Agent who will supply the db ??
//====================================================================================
func expBatchExp(db *sql.DB, userId, pid, eid, nsfile string,
		expArgs map[string]string, expEnv map [string]string) (*tbExpUtils.ExperimentType, string) {
	fmt.Println("START expBatchExp: MAP=\n")//, expArgs)
	var committed = false
	repfile := "report"

	// var descr = expArgs["expt_name"]

	if expArgs["user_course_pid"] != "" {
		//var course_swaperr_cclist= tbExpUtils.CourseSwapperCClist()
	}
	fmt.Println("expBatchExp: START 1 gid=", expArgs["gid"] )
	// group = *map[string]map[string]interface{}
	var group = tbExpUtils.GroupLookup(db, pid, expArgs["gid"])

	//# Set error reporting info
	// tblog_set_info($pid,$eid,$UID);
	fmt.Println("expBatchExp: START 2 GROUP=")//, group)

	// # Now create the experiment; we get back an instance of ExperimentType.
	exp, err := expCreateExp(db, group, eid, expArgs, expEnv)
	if err != "" {
		//errReply := "expBatchExp: Could not create a new experiment record! " + err
		return nil, err
	}
	fmt.Println("expBatchExp: START 3")
	// Create a directory structure for the experiment.
	err1 := expCreateDirectory(db, exp, pid, eid )
	if err1 != nil {
		return nil, "expBatchExp: Failed to create directory structure"
	}

	if expArgs["instance_idx"] != "" {
		//# Need to cross-mark the instance right away so that it is flagged.
		idx := expArgs["instance_idx"]
		var arg1 = map[string ]string {"exptidx":  idx}

		err2 := tbExpUtils.ExpInstanceUpdate(db, exp, arg1)
		if err2 != "" {
			fmt.Println("expBatchExp: Could not update experiment instance record!")
			return nil, err2
		}
	} else if expArgs["template"] != "" {
		//# Tell the template with the new experiment index.
		idx := exp.EXPT["idx"].(string)
		var arg1 = map[string ]string {"exptidx":  idx}
		err1 := tbExpUtils.ExpTemplateUpdate(db, exp, arg1)
		if err1 != nil {
			fmt.Println("expBatchExp: Could not update template record!")
		}
	}

	// Grab the working directory path, and thats where we work.
	// The user's experiment directory is off in /proj space.
	// TODO unify all of these into a single location vs /usr/testbed/proj  and /proj
	workdir := tbExpUtils.WorkDir(pid, eid)
	// userdir := tbExpUtils.UserDir(pid,eid)
	fmt.Println("expBatchExp: workdir=", workdir)
	// chdir("$workdir") or expFatal("Could not chdir to $workdir: $!");

	//# Create a new archive, which might actually be a branch of an existing one
	//# when doing an experiment branch (fork).
	if expArgs["branch_template"] != "" {
		fmt.Println("branch_template")
		/*
		my $archive_eid = $branch_template- > eid();
		if (libArchive::TBForkExperimentArchive($pid, $eid,$pid, $archive_eid, undef) < 0) {
			fmt.Println("Could not create experiment archive!")
		}
		*/
	} else {
		fmt.Println("expBatchExp: Create Exp Archive")
		if tbExpUtils.TBCreateExperimentArchive(pid, eid) < 0 {
			fmt.Println("Could not create experiment archive!")
			return nil, "Archivin failed"
		}
	}

	//# Okay, if copying/branching an experiment, we have to go find the
	//# NS file, extracting the special (currently by convention) archive
	//# directory into the new experiment. This will set the tempnsfile
	//# variable needed below.
	fmt.Println("expBatchExp: NOW SOME STRANGE LOGIC THAT NEEDS TO BE REVISITED" )
	tempNsFile := ""
	if expArgs["copyarg"] != "" {
		tempNsFile = tbExpUtils.CopyInArchive(expArgs);
	}

	//# And dump the web key too.
	//	open(KEY, ">" . $experiment->WebKeyPath()) or
	//fatal("Could not create webkey file: $!");
	//print KEY $webkey;
	//close(KEY);

	//# the user is forced to do a modify first (to give it a topology).
	// TODO Abort if tempnsfile not specified and nonsfile = 0 (default) ???
	// TODO: we have to have one or the other - revisit 2 pars into 1 ??
	if tempNsFile == "" {
		fmt.Println("expBatchExp: tempNsFile == blank")
		if expArgs["nonsfile"] == "1" { // we also have ns file ???
			tbExpUtils.ExpUnlock(db, exp, tbExpUtils.EXPTSTATE_NEW())
			return nil, "Failed tempNsFile and nsfile"
		}
	}

	if tempNsFile == "" {tempNsFile = tbConfig.TBDIR + "/tmp/" + expArgs["inputfile"]}

	fmt.Println("expBatchExp Get the NS file")
	if expArgs["nonsfile"] == "0" { // if we have ns file spec
		//# Now we can get the NS file!
		dest := workdir + "/" + expArgs["nsfile"]
		fmt.Println("expBatchExp: COPY ", tempNsFile, "  TO  ",  dest)
		err4 := tbExpUtils.ExpFileCopy(tempNsFile, dest)
		if err4 != nil{
			return nil, "Could not copy " + tempNsFile + "  to  "+ nsfile
		}
	}
	// # We created this file below so kill it.
	if expArgs["copyarg"] != "" {
		// unlink($tempnsfile);  TODO close the file ??
	}
	fmt.Println("expBatchExp tempnsfile=", tempNsFile)
	//++++++++++++++++//++++++++++++++++//++++++++++++++++//++++++++++++++++//++++++++++++++++
	// Run parse in impotent mode on the NS file.
	// This has no effect but will display any errors.
	//==========================================================
	if expArgs["nonsfile"] == "" {
		// TODO parser !!
		fmt.Println("expBatchExp: START PARSER IMPOTENT MODE .... TODO")
		ret := 0
		// /ret := system("$parser -n $zeeopt $pid $gid $eid $nsfile")
		if ret != 0 {
			fmt.Println("expBatchExp: NS Parse failed!")
			return nil, "expBatchExp: NS Parse failed!"
		}
	}
	fmt.Println("expBatchExp get statistics")
	// Gather statistics; start the clock ticking.
	thisUser := exp.EXPT["expt_swap_uid"].(string)

	if expArgs["frontend"] != "" || expArgs["batchmode"] != "" {
		if expPreSwap(db,exp, thisUser, TBDB_STATS_PRELOAD, expArgs["state"]) != 0 {
			fmt.Println("Preswap failed! frontend or batchmode");
			return nil, "NS PreSwap batchmode failed!"
		}
	} else {
		if expPreSwap(db,exp, thisUser, TBDB_STATS_START, expArgs["state"]) != 0 {
			fmt.Println("Preswap failed!, not frontend and not batchmode")
			return nil, "NS PreSwap failed!"
		}
	}
	logname := ""
	fmt.Println("expBatchExp almost done")
	var logfile map[string]map[string]interface{}
	if expArgs["template"] == "" { // Not a template
		fmt.Println("expBatchExp: Exp is NOT a template")
		logfile = tbExpUtils.ExpCreateLogFile(db, exp, "startexp");

		if logfile == nil {
			Log.Error(&Log, "Could not create logfile!");
		}

		logname = logfile["LOGFILE"]["filename"].(string)

		//# We want it to spew to the web.
		tbExpUtils.ExpSetLogFile(db, exp, logfile)

		//# Mark it open since we are going to start using it right away.
		tbExpUtils.LogOpen(db, logfile);

		fmt.Println("Waiting for experiment to finish \n")

		// TODO TBdbfork()   call EventFork()
	}

	committed = true

	//======================================================================
	//# The guts of starting an experiment!
	//# A batch experiment is essentially preloaded (frontend mode) and then
	//# dropped into the batch queue, unless the user requested only preload.
	//======================================================================
	fmt.Println("expBatchExp:  AND NOW START THE NEXY PHASE -=-=-=-=-=-=-=-=-=-=-=-=-=-=")
	if tbExpUtils.ExpSetState(db, exp, EXPTSTATE_PRERUN) != 0 {
		//expFatal("Failed to set experiment state to " + tbExpUtils.EXPTSTATE_PRERUN())
		return nil, "Failed to set experiment state to " + tbExpUtils.EXPTSTATE_PRERUN()
	}
	fmt.Println("expBatchExp:  check if nonsfile == 0, RUN  expPreRun ")
	if expArgs["nonsfile"] == "0" {
		fmt.Println("expBatchExp:  RUN  expPreRun nsfile=", nsfile)
		if expPreRun(db, exp, nsfile, expArgs["xmlfile"], expArgs["zeeopt"]) == false {
			fmt.Println("expBatchExp:  ERROR ERROR expPreRun failed")
			return nil, "tbprerun failed!"
		}
	}
	fmt.Println ("expBatchExp: Set state to SWAPPED")
	if tbExpUtils.ExpSetState(db, exp, EXPTSTATE_SWAPPED) != 0 {
		return nil, "Failed to set experiment state to " + tbExpUtils.EXPTSTATE_SWAPPED()
	}

	//# If not in frontend mode (preload only) continue to swapping exp in.
	fmt.Println ("expBatchExp: Check frontend and batchmode")
	// if (! ($frontend || $batchmode)) {
	if (expArgs["frontend"] != "" || expArgs["batchmode"] != "") == false {
		fmt.Println ("expBatchExp: frontend or batchmode")
		if tbExpUtils.ExpSetState(db, exp, EXPTSTATE_ACTIVATING) != 0 {
			//expFatal("Failed to set experiment state to "+ tbExpUtils.EXPTSTATE_ACTIVATING()
			// TODO cleanup
			return nil, "expBatchExp: Failed to set experiment state to "+ tbExpUtils.EXPTSTATE_ACTIVATING()
		}

		if expArgs["nonsfile"] == "0" {
			fmt.Println ("expBatchExp: nonsfile = 0")
			if expSwap(exp,"", "","") != 0 {
				return nil, "expBatchExp: tbswap in failed!"
			}
		}

		if tbExpUtils.ExpSetState(db, exp, EXPTSTATE_ACTIVE) != 0 {
			// expFatal(committed, "Failed to set experiment state to " + tbExpUtils.EXPTSTATE_PRERUN())
			return nil, "expBatchExp: Failed to set experiment state to " + tbExpUtils.EXPTSTATE_ACTIVE()
		}

		//fmt.Println ("expBatchExp: 2 nodes andno vlan")
		//# Look for the unsual case of more than 2 nodes and no vlans.
		/* TODO later
		my @localnodes = ();
		expFatal("Could not get local node list for $pid/$eid")
		if ($experiment->LocalNodeListNames(\@localnodes));

		if (@localnodes && scalar(@localnodes) > 2) {
			my $vlans_result =
			DBQueryexpFatal("select pid from virt_lans ".
			"where pid='$pid' and eid='$eid'");

			if (!$vlans_result->numrows && !$noemail) {
				SENDMAIL("$user_name <$user_email>",
				"WARNING: Experiment Configuration: $pid/$eid",
				"This experiment has zero network links defined.\n".
				"Please check your NS file to verify this is what you ".
				"want!\n",
				$TBOPS,
				"Cc: $TBOPS", ($nsfile));
			}
		}
		*/
	}

	//# We append this report in the email message below.
	fmt.Println ("expBatchExp: expReport")
	if expReport(exp, repfile, "-b") != 0 {
		expFatal(committed, "tbreport failed!");
		return nil, "expBatchExp: tbreport failed!"
	}

	//# Latest log is always called the same thing.
	if logname != "" {
		mysystem("cp", "-fp", logname,  "workdir/" + tbExpUtils.EXPTLOGNAME())
	}
	fmt.Println ("expBatchExp: ExpSaveLogFiles")
	//# Save a copy of the files for testbed information gathering (long term).
	tbExpUtils.ExpSaveLogFiles(db, exp);

	//# Make a copy of the work dir in the user visible space so the user
	//# can see the log files.
	tbExpUtils.ExpCopyLogFiles(exp);

	//# Tell the archive library to add all files to the archive.
	if tbExpUtils.TBExperimentArchiveAddUserFiles(db, exp) < 0 {
		//expFatal("Failed to add user archive files to the archive!");
	}
	//# Do a SavePoint on the experiment files. In template mode, let the wrapper
	//# deal with this. Avoids duplication of work.
	fmt.Println ("expBatchExp: do a SavePoint")
	if expArgs["template"] == ""  {
		fmt.Println ("Doing a savepoint on the experiment archive ...\n")
		//if TBExperimentArchiveSavePoint($pid, $eid, "startexp") < 0 {
		// the ab ove function just returns 0, nothing done ....
			//expFatal({type => 'secondary', severity => SEV_SECONDARY,
			//error => ['archive_op_failed', 'savepoint', undef, undef]},
			//"Failed to do a savepoint on the experiment archive!");
		//}
	}

	//# Gather statistics. This is not likely to fail, but if it does I want to
	//# bail cause the inconsistent records are a pain in the ass to deal with!
	fmt.Println ("expBatchExp: Gather Statistics")
	flags := ""
	if (expArgs["frontend"] != "" || expArgs["batchmode"] != "") {
		if tbExpUtils.SwapPostSwap(db, exp, userId, TBDB_STATS_PRELOAD, flags) != true {
			expFatal(committed, "Postswap failed!")
		}
	} else if tbExpUtils.SwapPostSwap(db, exp, userId, TBDB_STATS_START, flags) != true {
			expFatal(committed, "Postswap failed!")
	 }

	//# Set accounting stuff, but on success only, and *after* gathering swap stats!
	tbExpUtils.SwapSetSwapInfo(db, exp)
	fmt.Println ("expBatchExp: close log file ??")
	//# Close up the log file so the webpage stops.
	if expArgs["template"] == "" {
		fmt.Println("Experiment $pid/$eid has been successfully created!\n")
		tbExpUtils.ExpCloseLogFile(db, logfile)
	}
	fmt.Println ("expBatchExp: unlock batch exp, if any")
	//# Must unlock and drop batch experiments into the queue before exit.
	if expArgs["batchmode"] != "" && expArgs["frontend"] == "" {
		tbExpUtils.ExpUnlock(db, exp, tbExpUtils.EXPTSTATE_QUEUED());
	} else {
		tbExpUtils.ExpUnlock(db, exp, "");
	}

	//# Clear the cancel flag now that the operation is complete. Must be
	//# done after we change the experiment state (above).
	tbExpUtils.ExpSetCancelFlag(db, exp, tbExpUtils.EXPTCANCEL_CLEAR())

	//# In template_mode we are done; the caller finishes up.
	if expArgs["template"] == "" { return exp, ""}

	//# Dump the report file and the log file to the user via email.
	//expt_created = $experiment->created()

	// TODO - some more work here
	fmt.Println ("expBatchExp: FINAL DONE - more work needed")
	return exp, ""
}

//======================================================================
//
//======================================================================
func expSwap(exp *tbExpUtils.ExperimentType, which,options,flags string) int {

	pid := exp.EXPT["pid"].(string)
	eid := exp.EXPT["eid"].(string)
	//idx := exp.HASH["EXPT"]["idx"].(string)
	var op string

	if which == tbExpUtils.TBDB_STATS_SWAPIN() {
		op = "in";
	} else if which == tbExpUtils.TBDB_STATS_SWAPOUT() {
		op = "out";
	} else if which == tbExpUtils.TBDB_STATS_SWAPMODIFY() {
		op = "modify";
	} else if which == tbExpUtils.TBDB_STATS_SWAPUPDATE() {
		op = "update";
	}

	fmt.Println( "Running 'tbswap " + op + " $options " + pid+ " " + eid +"'\n")
	// TODO LATER
	//system("$TBSWAP $op $options $pid $eid");
	// if ($?) return -1

	return 0
}

//======================================================================
// Initialiaize bookkeeping for a swap operation.
//======================================================================
func expPreSwap(db *sql.DB,exp *tbExpUtils.ExperimentType, thisUser, which, estate string) int {

	exptidx  := exp.EXPT["idx"].(string)
	rsrcidx  := exp.STATS["rsrcidx"].(string)
	lastrsrc := exp.STATS["lastrsrc"].(string)
	uid_idx  := exp.EXPT["swapper_idx"].(string)
	// isactive := false
	// if estate == EXPTSTATE_ACTIVE {
	//	isactive = true
	//}

	// We should never get here with a lastrsrc in the stats record; it
	// indicates something went wrong, and we need to clean up the DB
	// state by hand.

	if lastrsrc != "0" { // TODO shows as nil vs "0"
		fmt.Println("expPreSwap: Inconsistent lastrsrc in stats record lastrsrc=",
								lastrsrc, " rsrcidx=", rsrcidx)
		// XPT_RESOURCESHOSED = 1;
		//return -1;
	}

	// Generate a new resource record, but watch for the unused one that
	// we got when the experiment was first created.


	// In SWAPIN, copy over the thumbnail. This is temporary; I think
	// the thumbnail is going to end up going someplace else.
	// For swapmod, its gonna get overwritten in tbprerun.
	// Ditto above for input_data_idx.

	thumbdata :=  "NULL"
	//  (defined($self->thumbnail()) ? DBQuoteSpecial($self->thumbnail()) : "NULL");

	input_data_idx :=  0 // Input_data_idx //  else
	// (defined($self->input_data_idx()) ? $self->input_data_idx() : "NULL");

	byswapmod := "0"
	byswapin  := "0"
	if which == TBDB_STATS_SWAPMODIFY {byswapmod = "1" }
	if which == TBDB_STATS_SWAPIN     {byswapin   = "1" }
	t     := time.Now()
	tString := fmt.Sprintf("%d-%02d-%02d %02d:%02d:%02d",
		t.Year(), t.Month(), t.Day(), t.Hour(), t.Minute(), t.Second())

	newrsrc,_,err := tbDbaseUtils.DBtransaction("expPreSwap",db,
			"insert into experiment_resources (idx, uid_idx, tstamp, exptidx, lastidx, " +
			"byswapmod, byswapin, input_data_idx, thumbnail) " +
			"values (0, '" + uid_idx + "', '" + tString + "','" + exptidx +
			"', '" + rsrcidx + "', '" +  byswapmod + "', '" + byswapin + "', '" +
				strconv.Itoa(input_data_idx) + "', '" + thumbdata + "')")
	if err != nil {
		return -1
	}

	newrsrc,_,err = tbDbaseUtils.DBtransaction("expPreSwap",db,"update experiment_stats set " +
		"  rsrcidx='" + strconv.FormatInt(newrsrc,10) +  "',lastrsrc='" +
			rsrcidx + "'  where exptidx='" + exptidx + "'")
	if err != nil {
		return -1
	}

	tbExpUtils.ExpRefresh(db, exp) // goto failed;

	// TODO rsrcidx = newrsrc
	fmt.Println("expPreSwap: newrsrc=",newrsrc)
	/*
	#
	# Update the timestamps in the current resource record to reflect
	# the official start of the operation.
	#
	if ($which eq $EXPT_SWAPIN || $which eq $EXPT_START) {
		DBQueryWarn("update experiment_resources set ".
			"  swapin_time=UNIX_TIMESTAMP(now()) where idx='$rsrcidx'")
			or goto failed;
	}
	elsif ($which eq $EXPT_SWAPOUT && ! $self->swapout_time()) {
		# Do not overwrite it; means a previously failed swapout, but for
		# accounting purposes, we want the original time.
		DBQueryWarn("update experiment_resources set swapout_time=UNIX_TIMESTAMP(now()) ".
			"where idx='$rsrcidx'")
		or goto failed;
	}
	elsif ($which eq $EXPT_SWAPMOD && $isactive) {
		DBQueryWarn("update experiment_resources set swapin_time=UNIX_TIMESTAMP(now()) ".
			"where idx='$rsrcidx'")
		or goto failed;
	#
	# If this swapmod fails, the record is deleted of course.
	# But if it succeeds, we will also change the previous record
	# to reflect the swapmod time. See PostSwap() below.
	#
	}

	# Old swap gathering stuff.
	$self->GatherSwapStats(thisUser, $which, 0, libdb::TBDB_STATS_FLAGS_START()) == 0
	or goto failed;

	# We do these here since even failed operations implies activity.
	# No worries if they fail; just informational.
	thisUser->BumpActivity();
	$self->GetProject()->BumpActivity();
	$self->GetGroup()->BumpActivity();
	$self->Refresh() == 0
	or goto failed;
	return 0;

	failed:
	$self->SwapFail($which, 55);
	return -1;
	*/
	return 0
}

//======================================================================
func expCleanup(committed bool) {
/*
	//# Failed early (say, in parsing). No point in keeping any of the
	//# stats or resource records. Just a waste of space since the
	//# testbed_stats log indicates there was a failure and why (sorta,
	//# via the exit code).
	if (!$committed) {
		//# Completely remove all trace of the archive.
		libArchive::TBDestroyExperimentArchive($pid, $eid);

		# Clear the experiment record and cleanup directories

		$experiment->Delete(1)
			if (defined($experiment));
		return;
	}

	//#
	//# Gather statistics.
	//#
	if ($frontend) {
		$experiment->SwapFail($this_user, TBDB_STATS_PRELOAD, $errorstat);
	}
	else {
		$experiment->SwapFail($this_user, TBDB_STATS_START, $errorstat);
	}

	//# Must clean up the experiment if it made it our of NEW state.
	my $estate = $experiment->state();
	if ($estate ne EXPTSTATE_NEW) {
		# We do not know exactly where things stopped, so if the
		# experiment was activating when the signal was delivered,
		# run tbswap on it.
		if ($estate eq EXPTSTATE_ACTIVE ||
					($estate eq EXPTSTATE_ACTIVATING && $signaled)) {
			if ($experiment->Swap("out", "-force") != 0) {
				print "tbswap out -force failed!\n";
			}
			$experiment->SetState(EXPTSTATE_SWAPPED);
		}

		if ($experiment->End("-f") != 0) {
			print "tbend failed!\n";
		}
	}
	$experiment->SetState(EXPTSTATE_TERMINATED);

	//# Old swap gathering stuff.
	$experiment->GatherSwapStats($this_user, TBDB_STATS_TERMINATE, 0);

	//# Clear the logfile so the webpage stops.
	$experiment->CloseLogFile();

	if (!$ENV{'TBAUDITON'}) {
		# Figure out the error if possible
		my $error_data = tblog_find_error();
       #
        # Send a message to the testbed list.
        #
        tblog_email_error($error_data,
                          "$user_name <$user_email>",
                          "Config Failure", "$pid/$eid",
                          "$user_name <$user_email>",
                          defined($course_swaperr_cclist) ? ", $course_swaperr_cclist" : "",
                          "Cc: $TBOPS",
                          "",
                          ($logname, "assign.log", "wanassign.log", $nsfile))
            unless $noemail;

	}

	//# Back up the work dir for post-mortem debugging.
	system("/bin/rm -rf  ${workdir}-failed");
	system("/bin/mv -f   $workdir ${workdir}-failed");

	//# Clear the record and cleanup.
	$experiment->Delete();
*/
}

//======================================================================
//# We need this END block to make sure that we clean up after a expFatal
//# exit in the library. This is problematic, cause we could be exiting
//# cause the mysql server has gone whacky again.
//======================================================================
func expFatal(committed bool, xyz string) {
	expCleanup(committed);
}

//======================================================================
//# Return the log directory name for an experiment. This is where
//# we keep copies of the files for later inspection.
//======================================================================
func TBExptWorkDir(pid,eid string) string {
	return tbExpUtils.TBDB_EXPT_WORKDIR() + "/" + pid + "/" + eid
}

//======================================================================
//
//======================================================================
func expReport(exp *tbExpUtils.ExperimentType, filename, options string) int{

	var pid = exp.EXPT["pid"].(string)
	var eid = exp.EXPT["eid"].(string)

	fmt.Println( "Running 'tbreport $options $pid $eid'\n")
	// TODO LATER
	mysystem("/usr/testbed/bin/tbreport " +
			options +" "+ pid + " " + eid ) //>> filename

	//if ($?)  return -1
	return 0;
}

func mysystem(cmd string, expArgs ...string) {

	fmt.Printf("Run Command: " + cmd)

	out, err := exec.Command(cmd , expArgs...).Output()
	if err != nil {
		log.Fatal(err)
	}
	fmt.Printf("The date is %s\n", out)

	///return system(command ...);
}


func ExampleCmd_Run() {
	  	cmd := exec.Command("sleep", "1")
	  	log.Printf("Running command and waiting for it to finish...")
	  	err := cmd.Run()
	  	log.Printf("Command finished with error: %v", err)
}

func ExampleCommand() {
	cmd := exec.Command("tr", "a-z", "A-Z")
	cmd.Stdin = strings.NewReader("some input")
	var out bytes.Buffer
	cmd.Stdout = &out
	err := cmd.Run()
	if err != nil {
			log.Fatal(err)
		}
	fmt.Printf("in all caps: %q\n", out.String())
}
func ExampleCmd_Output() {
	out, err := exec.Command("date").Output()
	if err != nil {
			log.Fatal(err)
	}
	fmt.Printf("The date is %s\n", out)
}

func ExampleCommand_environment() {
	cmd := exec.Command("prog")
	cmd.Env = append(os.Environ(),
			"FOO=duplicate_value", // ignored
			"FOO=actual_value",    // this value is used
		)
	if err := cmd.Run(); err != nil {
			log.Fatal(err)
	}
}