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
	"os"
	"io"
	"testbedGS/common/tbConfiguration"
	"database/sql"
	"fmt"
)

//# Branch an experiment archive. Only an existing experiment can be
//# branched, but that will probably change later.
func TBForkExperimentArchive(pid, eid, copypid, copyeid, copytag string) int {

 //var archive map[string]string
 //var copyview map[string]string

 /* TODO
	if (!Archive::doarchiving(db exp)) return 0

 	rval := TBExperimentArchive(copypid, copyeid, &archive, &copyview);

	if rval > 0 {return 0}
	if rval < 0 {return -1}

	experiment = Experiment->Lookup($pid, $eid);

	if (!defined($experiment)) return -1

	var archive_idx = $archive->idx();
	var rsrcidx     = $experiment->rsrcidx();
	var archive_tag = "F${rsrcidx}";
	var newview     = $experiment->idx();


	if ($archive->Fork($newview, $archive_tag, $copyview) < 0) return -1

	$experiment->TableUpdate("experiment_resources", "archive_tag='$archive_tag'", "idx='$rsrcidx'") == 0
	or goto bad;

	$experiment->TableUpdate("experiment_stats", "archive_idx='$archive_idx'") == 0
	or goto bad;

	return 0

bad:
	//# Its a shared resource, but ArchiveDestroy() checks.
	if (defined($archive)) $archive->Destroy(1, $newview)
	return -1;
 */
 return 0
}

//====================================================================================
//# Get the archive index for an experiment. The index is kept in the historical
//# experiment_stats table, not the current experiments table. That is cause
//# we keep the archive and its DB info around forever with the stats.
//====================================================================================
func TBExperimentArchive(pid, eid string, archivep, viewp *map[string]string) int {
/*
	my $query_result =
	DBQueryWarn("select s.archive_idx,e.idx from experiments as e ".
	"left join experiment_stats as s on s.exptidx=e.idx ".
	"where e.pid='$pid' and e.eid='$eid'");

	return -1
	if (!$query_result || $query_result->numrows == 0);

	my ($archive_idx,$exptidx) = $query_result->fetchrow_array();

	# Need to deal with no archive yet!
	return 1
	if (!defined($archive_idx) || $archive_idx == 0);

	my $archive = Archive->Lookup($archive_idx);
	return -1
	if (!defined($archive));

	$$archivep = $archive
	if (defined($archivep));
	$$viewp = "$exptidx"
	if (defined($viewp));
*/
	return 0;
}


//# Create a new archive for an experiment. This has to update the
//# experiment_stats table with the newly created archive index.
//# Then we have to set the current tag for the experiment in the
//# resources table for the experiment.
//# Returns zero on success, -1 on failure./
//#
func TBCreateExperimentArchive(pid, eid string)  int {
	/* TODO
	if (!Archive::doarchiving(db, exp))  return 0

	my $experiment = Experiment->Lookup($pid, $eid);

	if (!defined($experiment))  return -1

	my $exptidx   = $experiment->idx();
	my $rsrcidx   = $experiment->rsrcidx();
	my $group     = $experiment->GetGroup();
	my $unix_name = $group->unix_name();
	my $view      = "$exptidx";

	my $archive = Archive->Create($view, $unix_name);

	if (!defined($archive))  return -1

	my $archive_idx = $archive->idx();

	$experiment->TableUpdate("experiment_stats", "archive_idx='$archive_idx'") == 0
	or goto bad;
	*/
	return 0;

//bad:
//$archive->Destroy(1, $view);
//return -1;
}

//
//# Copy in what we need from another experiment (or archive).
//#
func CopyInArchive(args map[string]string) string {
	eid := args["eid"]

	var tempnsfile = "/tmp/" + eid + ".ns";
/* TODO
	if args["copyfrom"] == "exp" {
		#
		# Grab a copy from the DB since we save all current NS files there.
		#
		my $nsfile;
		$copy_experiment->GetNSFile(\$nsfile) == 0
		or tbdie("Could not get NS file for $copy_experiment");

		tbdie("No nsfile in DB for $copy_experiment")
		if (!defined($nsfile) || $nsfile eq "");

		open(NS, "> $tempnsfile")
		or tbdie("Could not write ns code to $tempnsfile!\n");
		$nsfile =~ s/\r//g;
		print NS $nsfile;
		print NS "\n";
		close(NS);
		chmod(0664, "$tempnsfile");
	}
	else {
		// # Look in the resources table.
		my $query_result =
		DBQueryFatal("select d.input from experiment_stats as s ".
		"left join experiment_inputs as i on ".
		"     i.exptidx=s.exptidx and i.rsrcidx=s.rsrcidx ".
		"left join experiment_input_data as d on ".
		"     d.idx=i.input_data_idx ".
		"where s.exptidx='$copyidx'");

		if (! $query_result->numrows) {
			tbdie("No such experiment index: $copyidx");
		}
		my ($nsfile) = $query_result->fetchrow_array();

		open(NS, "> $tempnsfile")
		or tbdie("Could not write ns code to $tempnsfile!\n");
		$nsfile =~ s/\r//g;
		print NS $nsfile;
		print NS "\n";
		close(NS);
		chmod(0664, "$tempnsfile");
	}
*/
	return tempnsfile
}

// Copy the src file to dst. Any existing file will be overwritten and will not
// copy file attributes.
//srcFolder := "copy/from/path"
//destFolder := "copy/to/path"
//cpCmd := exec.Command("cp", "-rf", srcFolder, destFolder)
//err := cpCmd.Run()
func ExpFileCopy(src, dst string) error {
	source := src // tbConfig.TBDB_USERDIR + src
	in, err := os.Open(source)
	if err != nil {
		return err
	}
	defer in.Close()
	target := dst // tbConfig.TBDB_EXPT_WORKDIR + dst
	fmt.Println("ExpFileCopy: COPY ", source, "  TO  ", target)
	out, err := os.Create(target)
	if err != nil {
		return err
	}
	defer out.Close()

	_, err = io.Copy(out, in)
	if err != nil {
		return err
	}
	out.Chmod(0664)
	return out.Close()
}

//======================================================================
//# Add all files from the experiment directory to the archive.
//======================================================================
func TBExperimentArchiveAddUserFiles(db *sql.DB, exp *ExperimentType) int {

	pid := exp.EXPT["pid"].(string)
	eid := exp.EXPT["eid"].(string)

	var archive, view map[string]string

	if doarchiving(db, exp) == false  { return -1 }

	rval := TBExperimentArchive(pid, eid, &archive, &view)

	if (rval > 0) { return 0 }

	if (rval < 0)  {return -1 }

	 var userdir string

	if exp.EXPT["isTemplate"].(string) != "" {
		//# XXX Fix this
		TemplateLookupByPidEid(db,exp)

		userdir = exp.TEMPLATE["path"].(string)
	} else {
		userdir = UserDir(pid, eid)
	}

	exists, _ := pathExists(userdir)
	if exists == true  {
		rval = ArchiveAdd(exp, userdir+ "/.", view, 1, 1);

		if rval != 0  {return rval}
	}
	return 0;
}

// exists returns whether the given file or directory exists or not
func pathExists(path string) (bool, error) {
	_, err := os.Stat(path)
	if err == nil { return true, nil }
	if os.IsNotExist(err) { return false, nil }
	return true, err
}
//======================================================================
//# On or off
//======================================================================
var ALLOWEDPID = map[string]int {"testbed": 1,}

func doarchiving(db *sql.DB, exp *ExperimentType) bool {

	pid := exp.EXPT["pid"].(string)
	//eid := exp.HASH["EXPT"]["eid"].(string)


	if tbConfig.USEARCHIVE == false {
		return false
	}

	project := ProjectLookup(db, exp, pid);

	if project == nil {
		return false
	}

	//# The experiment might be the one underlying a template.
	template := TemplateLookupByPidEid(db, exp)

	exp.TEMPLATE = template
	if (exp.EXPT["isInstance"].(string) != "" ||
		exp.EXPT["template"].(string) != "") &&
		(ALLOWEDPID[pid] == 1 ||
		exp.PROJECT["allow_workbench"].(string) != "" ) { return true }

return true
}
//======================================================================
//# Add a file to an archive. Returns -1 if any error. Otherwise return 0.
//# All this does is copy the file (and its directory structure) into the
//# temporary store. Later, after all the files are in the tree, must
//# commit it to the repo.
//======================================================================
func  ArchiveAdd(exp *ExperimentType, pathname string,
	view map[string]string,exact,special int) int {
	/*
my ($self, $pathname, $view, $exact, $special) = @_;

return -1
if (! ref($self));

$view = $defaultview
if (!defined($view));

$exact = 0
if (!defined($exact));

$special = 0
if (!defined($special));

# This returns a taint checked value in $pathname.
if (ValidatePath(\$pathname) != 0) {
print STDERR "ArchiveAdd: Could not validate pathname $pathname\n";
return -1;
}

#
# Strip leading /dir from the pathname, we need it below.
#
my ($rootdir, $sourcedir, $sourcefile);
my $rsyncopt = "";

if ($special) {
#
# What does this do?
# Basically, we copy the last part (directory) to / of the checkin.
# eg: cp /proj/pid/exp/eid... /exp of the checkins.
# This avoids pid/eid tokens in the archive.
#
# Last part of path must be a directory.
#
if (! -d $pathname) {
print STDERR "ArchiveAdd: Must be a directory: $pathname\n";
return -1;
}
$rootdir    = "exp";
$sourcedir  = $pathname;
$sourcefile = "./";
}
elsif ($pathname =~ /^[\/]+(\w+)\/([-\w\/\.\+\@,~]+)$/) {
$rootdir    = $1;
$sourcedir  = $1;
$sourcefile = $2;
$rsyncopt   = "-R";
}
else {
print STDERR "ArchiveAdd: Illegal characters in pathname $pathname\n";
return -1;
}

#
# See if the archive exists and if it does, get the pathname to it.
#
my $directory = $self->directory();
if (! -d $directory || ! -w $directory) {
print STDERR "ArchiveAdd: $directory cannot be written!\n";
return -1;
}
my $checkin   = "$directory/checkins/$view";

#
# If the target rootdir exists and is not writable by the current
# user, then run a chown over the whole subdir. This will avoid
# avoid permission problems later during the rsync/tar ops below.
#
if (-e "$checkin/$rootdir" && ! -o "$checkin/$rootdir") {
mysystem("$SUCHOWN $checkin/$rootdir") == 0 or return -1
}

#
# Copy the file in. We use tar on individual files (to retain the
# directory structure and mode bits, etc). On a directory, use either
# tar or rsync, depending on whether we want an exact copy (removing
# files in the target that are not present in the source).
#
if (! -e "$checkin/$rootdir") {
mysystem("$MKDIR $checkin/$rootdir") == 0 or return -1
}

if (-f "/${sourcedir}/${sourcefile}" || !$exact) {
mysystem("$TAR cf - -C /$sourcedir $sourcefile | ".
"$TAR xf - -U -C $checkin/$rootdir");
mysystem("$CHMOD 775 $checkin/$rootdir/$sourcefile");
}
else {
mysystem("cd /$sourcedir; ".
"$RSYNC $rsyncopt -rtgoDlz ".
"  --delete ${sourcefile} $checkin/$rootdir");
}
if ($?) {
print STDERR "ArchiveAdd: Could not copy in $pathname\n";
return -1;
}
	*/
return 0;
}
