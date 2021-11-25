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


//======================================================================
//# Check the -c argument, and set some global variables at the same time.
//======================================================================
func  CheckCopyArgs() int {
/*
	if (!defined($copyarg))  return 0

	if ($copyarg =~ /^([-\w]+),([-\w]+)(?::([-\w]*))?$/) {
		#
		# pid,eid of an existing experiment.
		#
		$copypid  = $1;
		$copyeid  = $2;
		$copytag  = (defined($3) ? (($3 eq "") ? undef : $3) : undef);
		$copyfrom = "exp";

		$copy_experiment = Experiment->Lookup($copypid, $copyeid);

		tbdie("Could not get experiment index for $copypid/$copyeid")
		if (!defined($copy_experiment));

		if (! $copy_experiment->AccessCheck($this_user, TB_EXPT_READINFO)) {
		tberror("You do not have permission to copy $copy_experiment");
		exit(1);
		}

		#
		# If given a tag, must use the archive. To do that, we need the
		# experiment index of the experiment we are copying from.
		#
		if (defined($copytag)) {
			$copyidx  = $copy_experiment->idx();
			$copyfrom = "archive";
		}
	}
	elsif ($copyarg =~ /^(\d+)(?::([-\w]*))?$/) {
		$copyidx  = $1;
		$copytag  = (defined($2) ? (($2 eq "") ? undef : $2) : undef);

		$copy_experiment = Experiment->LookupByIndex($copyidx);

		#
		# Not a current experiment?
		#
		if (!defined($copy_experiment)) {
			my $query_result =
			DBQueryFatal("select pid_idx from experiment_stats ".
			"where exptidx='$copyidx'");

			if (! $query_result->numrows) {
			tbdie("No such experiment index: $copyidx");
			}
			my ($copy_pid_idx) = $query_result->fetchrow_array();
			my $copy_project = Project->Lookup($copy_pid_idx);

			if (! $copy_project->AccessCheck($this_user,
			TB_PROJECT_READINFO)) {
			tberror("You do not have permission to copy $copy_experiment");
			exit(1);
			}
			$copyfrom = "archive";
		}
		else {
			$copypid  = $copy_experiment->pid();
			$copyeid  = $copy_experiment->eid();
			$copyfrom = "exp";

			if (! $copy_experiment->AccessCheck($this_user, TB_EXPT_READINFO)) {
				tberror("You do not have permission to copy $copy_experiment");
				exit(1);
			}
		}
	}
	else {
		tbdie("Bad data in -c option: $copyarg");
	}
*/
	return 0
}
//====================================================================================
//# Parse command arguments. Once we return from getopts, all that should
//# left are the required arguments.
//====================================================================================
func  ParseArgs() {
	/*
my %options = ();
if (! getopts($optlist, \%options)) {
usage();
}

if (@ARGV > 1) {
usage();
}
if (@ARGV == 1) {
$tempnsfile = $ARGV[0];

# Note different taint check (allow /).
if ($tempnsfile =~ /^([-\w\.\/]+)$/) {
$tempnsfile = $1;
}
else {
tbdie("Bad data in nsfile: $tempnsfile");
}

#
# Called from ops interactively. Make sure NS file resides in an
# appropriate location.
#
# Use realpath to resolve any symlinks.
#
my $translated = `realpath $tempnsfile`;
if ($translated =~ /^([-\w\.\/]+)$/) {
$tempnsfile = $1;
}
else {
tbdie({type => 'primary', severity => SEV_ERROR,
error => ['bad_data', 'realpath', $translated]},
"Bad data returned by realpath: $translated");
}

#
# The file must reside in an acceptible location. Since this script
# runs as the caller, regular file permission checks ensure it is a
# file the user is allowed to use.  So we don't have to be too tight
# with the RE matching /tmp and /var/tmp files.  Note that
# /tmp/$guid-$nsref.nsfile is also allowed since this script is
# invoked directly from web interface which generates a name that
# should not be guessable.
#
if (! ($tempnsfile =~ /^\/tmp\/[-\w]+-\d+\.nsfile/) &&
! ($tempnsfile =~ /^\/tmp\/\d+\.ns/) &&
! ($tempnsfile =~ /^\/(var\/)?tmp\/php[-\w]+/) &&
! TBValidUserDir($tempnsfile, 0)) {
tberror({type => 'primary', severity => SEV_ERROR,
error => ['disallowed_directory', $tempnsfile]},
"$tempnsfile does not resolve to an allowed directory!");
# Note positive status; so error goes to user not tbops.
exit(1);
}
}


#
# Clone an experiment, either an existing experiment or an old one
# (using the archive).
#
if (defined($options{"c"})) {
$copyarg = $options{"c"};

if (! (($copyarg =~ /^([-\w]+),([-\w]+)(?::[-\w]*)?$/) ||
($copyarg =~ /^(\d+)(?::[-\w]*)?$/))) {
tbdie({type => 'primary', severity => SEV_ERROR,
error => ['bad_data', 'argument', $copyarg]},
"Bad data in argument: $copyarg");
}
}

#
# pid,eid,gid get passed along as shell commands args; must taint check.
#
if (defined($options{"p"})) {
$pid = $options{"p"};

if ($pid =~ /^([-\w]+)$/) {
$pid = $1;
}
else {
tbdie({type => 'primary', severity => SEV_ERROR,
error => ['bad_data', 'argument', $pid]},
"Bad data in argument: $pid.");
}
}
if (defined($options{"e"})) {
$eid = $options{"e"};

if ($eid =~ /^([-\w]+)$/) {
$eid = $1;
}
else {
tbdie({type => 'primary', severity => SEV_ERROR,
error => ['bad_data', 'argument', $eid]},
"Bad data in argument: $eid.");
}
if (! TBcheck_dbslot($eid, "experiments", "eid",
TBDB_CHECKDBSLOT_WARN|TBDB_CHECKDBSLOT_ERROR)) {
tbdie({type => 'primary', severity => SEV_ERROR,
error => ['bad_data', 'eid', $eid]},
"Improper experiment name (id)!");
}
}
if (defined($options{"g"})) {
$gid = $options{"g"};

if ($gid =~ /^([-\w]+)$/) {
$gid = $1;
}
else {
tbdie({type => 'primary', severity => SEV_ERROR,
error => ['bad_data', 'argument', $gid]},
"Bad data in argument: $gid.");
}
}
	if (defined($options{"x"})) {
		my $guid;
		my $vers;
		my $branch_guid;
		my $branch_vers;

		if ($options{"x"} =~ /^(\d*)\/(\d*)$/) {
			$guid = $1;
			$vers = $2;
		}
		elsif ($options{"x"} =~ /^(\d*)\/(\d*),(\d*)\/(\d*)$/) {
			$guid = $1;
			$vers = $2;
			$branch_guid = $3;
			$branch_vers = $4;
		}
		else {
			tbdie("Bad arguments for -x option");
		}

		$template = Template->Lookup($guid, $vers);
		if (!defined($template)) {
			tbdie("No such template $guid/$vers");
		}

		if (defined($branch_guid)) {
			$branch_template = Template->Lookup($branch_guid, $branch_vers);
			if (!defined($branch_template)) {
				tbdie("No such template $branch_guid/$branch_vers");
			}
		}

		if (defined($options{"y"})) {
			my $instance_idx = $options{"y"};

			if ($instance_idx =~ /^([\d]+)$/) {
				$instance_idx = $1;
			}
			else {
				tbdie({type => 'primary', severity => SEV_ERROR,
				error => ['bad_data', 'argument', $instance_idx]},
				"Bad data in argument: $instance_idx.");
			}
			$instance = Template::Instance->LookupByID($instance_idx);
			if (!defined($instance)) {
				tbdie("No such template instance $instance_idx");
			}
		}
	}
	if (defined($options{"N"})) {
		$noemail = 1;
	}
	if (defined($options{"X"})) {
		$quiet = 1;
		$xmlout = 1;
	}
*/
}
