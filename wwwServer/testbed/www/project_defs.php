<?php
#
# EMULAB-COPYRIGHT
# Copyright (c) 2006, 2007 University of Utah and the Flux Group.
# All rights reserved.
#
# A cache of groups to avoid lookups. Indexed by pid_idx;
#
$project_cache = array();

class Project
{
    var $project;
    var $group;
    var $_grouplist;            # All subgroups
    var $tempdata;              # For temporary data values ...

    #
    # Constructor by lookup on unique index.
    #
    ## GORAN function Project($pid_idx) {
    function __construct($pid_idx) {
        $safe_pid_idx = addslashes($pid_idx);

        $query_result =
            DBQueryWarn("select * from projects ".
                        "where pid_idx='$safe_pid_idx'");

        if (!$query_result || !mysql_num_rows($query_result)) {
            $this->project = NULL;
            return;
        }
        $this->project   = mysql_fetch_array($query_result);
        $this->group     = null;
        $this->_grouplist = null;
    }

    # Hmm, how does one cause an error in a php constructor?
    function IsValid() {
        return !is_null($this->project);
    }

    # Lookup by pid_idx.
    static function Lookup($pid_idx) {
        global $project_cache;

        # Look in cache first
        if (array_key_exists("$pid_idx", $project_cache))
            return $project_cache["$pid_idx"];
        
        $foo = new Project($pid_idx);

        if (! $foo->IsValid()) {
            # Try lookup by plain uid.
            $foo = Project::LookupByPid($pid_idx);
            
            if (!$foo || !$foo->IsValid())
                return null;

            # Return here, already in the cache.
            return $foo;
        }
        # Insert into cache.
        $project_cache["$pid_idx"] = $foo;
        return $foo;
    }

    # Backwards compatable lookup by pid. Will eventually flush this.
    static function LookupByPid($pid) {
        $safe_pid = addslashes($pid);

        $query_result =
            DBQueryWarn("select pid_idx from projects where pid='$safe_pid'");

        if (!$query_result || !mysql_num_rows($query_result)) {
            return null;
        }
        $row = mysql_fetch_array($query_result);
        $idx = $row['pid_idx'];

        $foo = new Project($idx); 

        if ($foo->IsValid())
            return $foo;
        
        return null;
    }
    
    #
    # Refresh an instance by reloading from the DB.
    #
    function Refresh() {
        if (! $this->IsValid())
            return -1;

        $pid_idx = $this->pid_idx();

        $query_result =
            DBQueryWarn("select * from projects where pid_idx='$pid_idx'");
    
        if (!$query_result || !mysql_num_rows($query_result)) {
            $this->project = NULL;
            return -1;
        }
        $this->project   = mysql_fetch_array($query_result);

        if ($this->group) {
            $foo = $this->group;
            $foo->Refresh();
        }
        $this->_grouplist = null;
        return 0;
    }

    # accessors
    function field($name) {
        return (is_null($this->project) ? -1 : $this->project[$name]);
    }

    function pid_idx()       { return $this->field("pid_idx"); }
    function pid()           { return $this->field("pid"); }
    function created()       { return $this->field("created"); }
    function expires()       { return $this->field("expires"); }
    function name()          { return $this->field("name"); }
    function URL()           { return $this->field("URL"); }
    function org_type()      { return $this->field("org_type"); }
    function research_type() { return $this->field("research_type"); }
    function funders()       { return $this->field("funders"); }
    function addr()          { return $this->field("addr"); }
    function head_uid()      { return $this->field("head_uid"); }
    function head_idx()      { return $this->field("head_idx"); }
    function why()           { return $this->field("why"); }
    function status()        { return $this->field("status"); }
    function date_archived() { return $this->field("date_archived"); }
    function ispublic()      { return $this->field("public"); }
    function public_whynot() { return $this->field("public_whynot"); }
    function expt_count()    { return $this->field("expt_count"); }
    function expt_last()     { return $this->field("expt_last"); }
    function pcremote_ok()   { return $this->field("pcremote_ok"); }
    function default_user_interface()
                             { return $this->field("default_user_interface"); }
    function allow_workbench(){ return $this->field("allow_workbench"); }
    function is_class()      { return $this->field("class"); }

    function unix_gid() {
        $group = $this->DefaultGroup();
        
        return $group->unix_gid();
    }
    function unix_name() {
        $group = $this->DefaultGroup();

        return $group->unix_name();
    }
    # Temporary data storage ... useful.
    function SetTempData($value) {
        $this->tempdata = $value;
    }
    function GetTempData() {
        return $this->tempdata;
    }

    #
    # At some point we will stop passing pid and start using pid_idx.
    # Use this function to avoid having to change a bunch of code twice.
    #
    function URLParam() {
        return $this->pid();
    }

    #
    # Class function to create new project and return object.
    #
    static function NewProject($args, &$error) {
        global $suexec_output, $suexec_output_array;

        #
        # Generate a temporary file and write in the XML goo.
        #
        $xmlname = tempnam("/tmp", "newproj");
        if (! $xmlname) {
            TBERROR("Could not create temporary filename", 0);
            $error = "Transient error(1); please try again later.";
            return null;
        }
        if (! ($fp = fopen($xmlname, "w"))) {
            TBERROR("Could not open temp file $xmlname", 0);
            $error = "Transient error(2); please try again later.";
            return null;
        }

        fwrite($fp, "<project>\n");
        foreach ($args as $name => $value) {
            fwrite($fp, "<attribute name=\"$name\">");
            fwrite($fp, "  <value>" . htmlspecialchars($value) . "</value>");
            fwrite($fp, "</attribute>\n");
        }
        fwrite($fp, "</project>\n");
        fclose($fp);
        chmod($xmlname, 0666);

        $retval = SUEXEC("www", "www", "webnewproj $xmlname",
                         SUEXEC_ACTION_IGNORE | SUEXEC_ACTION_MAIL_TBLOGS);

        if ($retval) {
            if ($retval < 0) {
                $error = "Transient error(3, $retval); please try again later.";
                SUEXECERROR(SUEXEC_ACTION_CONTINUE);
            }
            else {
                $error = $suexec_output;
            }
            return null;
        }

        #
        # Parse the last line of output. Ick.
        #
        unset($matches);
        
        if (!preg_match("/^Project\s+([-\w]+)\/(\d+)\s+/",
                        $suexec_output_array[count($suexec_output_array)-1],
                        $matches)) {
            $error = "Transient error(4, $retval); please try again later.";
            SUEXECERROR(SUEXEC_ACTION_CONTINUE);
            return null;
        }
        $pid_idx = $matches[2];
        $newproj = Project::Lookup($pid_idx);
        if (! $newproj) {
            $error = "Transient error(5); please try again later.";
            TBERROR("Could not lookup new project $pid_idx", 0);
            return null;
        }
        # Unlink this here, so that the file is left behind in case of error.
        # We can then create the project by hand from the xmlfile, if desired.
        unlink($xmlname);
        return $newproj;
    }

    #
    # Class function to return a list of pending (unapproved) projects.
    #
    static function PendingProjectList() {
        $result     = array();

        $query_result =
            DBQueryFatal("select pid_idx, ".
                         " DATE_FORMAT(created, '%m/%d/%y') as day_created ".
                         " from projects ".
                         "where status='unapproved' order by created desc");
                             
        while ($row = mysql_fetch_array($query_result)) {
            $pid_idx = $row["pid_idx"];
            $created = $row["day_created"];

            if (! ($project = Project::Lookup($pid_idx))) {
                TBERROR("Project::PendingProjectList: ".
                        "Could not load project $pid_idx!", 1);
            }
            $project->SetTempData($created);
            
            $result[] = $project;
        }
        return $result;
    }
    
    function AccessCheck($user, $access_type) {
        $group = $this->DefaultGroup();
        
        return $group->AccessCheck($user, $access_type);
    }

    # Return the user trust within the project, which is really for the
    # default group.
    function UserTrust($user) {
        $group = $this->DefaultGroup();
        
        return $group->UserTrust($user);
    }

    #
    # Load the default group for a project lazily.
    #
    function LoadDefaultGroup() {
        if ($this->group) {
            return $this->group;
        }
        
        # Note: pid_idx=gid_idx for the default group
        $gid_idx = $this->pid_idx();

        if (! ($group = Group::Lookup($gid_idx))) {
            TBERROR("Project::LoadDefaultGroup: ".
                    "Could not load group $gid_idx!", 1);
        }
        $this->group = $group;
        return $group;
    }
    function DefaultGroup() { return $this->LoadDefaultGroup(); }
    function Group()        { return $this->DefaultGroup(); }

    #
    # Lookup a project subgroup by its name.
    #
    function LookupSubgroupByName($name) {
        $pid = $this->pid();

        return Group::LookupByPidGid($pid, $name);
    }

    #
    # Load all subgroups for this project.
    #
    function LoadSubGroups() {
        if ($this->_grouplist)
            return 0;
        
        $pid_idx = $this->pid_idx();
        $result  = array();

        $query_result =
            DBQueryFatal("select gid_idx from groups ".
                         "where pid_idx='$pid_idx'");

        while ($row = mysql_fetch_array($query_result)) {
            $gid_idx = $row["gid_idx"];

            if (! ($group = Group::Lookup($gid_idx))) {
                TBERROR("Project::LoadSubGroups: ".
                        "Could not load group $gid_idx!", 1);
            }
            $result[] = $group;
        }
        $this->_grouplist = $result;
        return 0;
    }
    function SubGroups() {
        $this->LoadSubGroups();
        return $this->_grouplist;
    }

    #
    # Return user object for leader.
    #
    function GetLeader() {
        $head_idx = $this->head_idx();

        if (! ($leader = User::Lookup($head_idx))) {
            TBERROR("Could not find user object for $head_idx", 1);
        }
        return $leader;
    }

    #
    # Add *new* member to project group; starts out with trust=none.
    #
    function AddNewMember($user) {
        $group = $this->DefaultGroup();

        return $group->AddNewMember($user);
    }

    #
    # Check if user is a member of this project (well, group)
    #
    function IsMember($user, &$approved) {
        $group = $this->DefaultGroup();

        return $group->IsMember($user, $approved);
    }

    #
    # Lookup an experiment within a project.
    #
    function LookupExperiment($eid) {
        return Experiment::LookupByPidEid($this->pid(), $eid);
    }

    #
    # How many PCs is project using. 
    #
    function PCsInUse() {
        $pid = $this->pid();
        
        $query_result =
            DBQueryFatal("select count(r.node_id) from reserved as r ".
                         "left join nodes as n on n.node_id=r.node_id ".
                         "left join node_types as nt on nt.type=n.type ".
                         "where nt.class='pc' and r.pid='$pid'");
    
        if (mysql_num_rows($query_result) == 0) {
            return 0;
        }
        $row = mysql_fetch_row($query_result);
        return $row[0];
    }
    
    #
    # Member list for a group.
    #
    function MemberList() {
        $pid_idx = $this->pid_idx();
        $result  = array();

        $query_result =
            DBQueryFatal("select uid_idx from group_membership ".
                         "where pid_idx='$pid_idx' and gid_idx=pid_idx");

        while ($row = mysql_fetch_array($query_result)) {
            $uid_idx = $row["uid_idx"];

            if (! ($user = User::Lookup($uid_idx))) {
                TBERROR("Project::MemberList: ".
                        "Could not load user $uid_idx!", 1);
            }
            $result[] = $user;
        }
        return $result;
    }

    #
    # List of subgroups for a project member (not including default group).
    #
    function GroupList($user) {
        $pid_idx = $this->pid_idx();
        $uid_idx = $user->uid_idx();
        $result  = array();

        $query_result =
            DBQueryFatal("select gid_idx from group_membership ".
                         "where pid_idx='$pid_idx' and pid_idx!=gid_idx and ".
                         "      uid_idx='$uid_idx'");

        while ($row = mysql_fetch_array($query_result)) {
            $gid_idx = $row["gid_idx"];

            if (! ($group = Group::Lookup($gid_idx))) {
                TBERROR("Project::GroupList: ".
                        "Could not load group $gid_idx!", 1);
            }
            $result[] = $group;
        }
        return $result;
    }

    #
    # List of experiments for a project, or just the count.
    #
    function ExperimentList($listify = 1) {
        $group = $this->DefaultGroup();

        return $group->ExperimentList($listify);
    }

    #
    # Vote results for a project
    #
    function VoteResults() {
        $votes = array(
            'yes'   => 0,
            'no'    => 0,
            'total' => 0,
        );

            $pid_idx = $this->pid_idx();
        $query_result = DBQueryFatal(
            'select vote, count(*) as count ' .
            '  from votes ' .
            " where pid_idx = '$pid_idx' " .
            ' group by vote'
        );

        # tally the total and the number of each type of vote
        while ($row = mysql_fetch_array($query_result)) {
            $votes['total'] += $row['count'];

            if (is_null($row['vote'])) continue;
            $votes[$row['vote'] ? 'yes' : 'no'] = $row['count'];
        }

        return $votes;
    }

    #
    # Returns true when an agreement has been reached
    #
    function AgreementReached() {
        $votes = $this->VoteResults();

        if ($votes['total'] == 0) return 0;
        return $votes['yes'] / $votes['total'] >= 0.5 && $votes['no'] == 0;
    }

    #
    # Returns the age of the project in days (can be non-integer)
    #
    function Age() {
        $age = -1;

        $pid_idx = $this->pid_idx();
        $query_result = DBQueryFatal(
            'select unix_timestamp(NOW()) - unix_timestamp(created) ' .
            '  from projects ' .
            " where pid_idx = '$pid_idx'"
        );

        if (mysql_num_rows($query_result) == 1) {
            $row = mysql_fetch_array($query_result);
            $age = $row[0] / 86400;
        }

        return $age;
    }

    #
    # Change the leader for a project. Done *only* before project is
    # approved.
    #
    function ChangeLeader($leader) {
        $group   = $this->DefaultGroup();
        $idx     = $this->pid_idx();
        $uid     = $leader->uid();
        $uid_idx = $leader->uid_idx();

        DBQueryFatal("update projects set ".
                     "  head_uid='$uid',head_idx='$uid_idx' ".
                     "where pid_idx='$idx'");

        $this->project["head_uid"] = $uid;
        $this->project["head_idx"] = $uid_idx;
        return $group->ChangeLeader($leader);
    }
    
    #
    # Change various fields.
    #
    function SetRemoteOK($ok) {
        $idx    = $this->pid_idx();
        $safeok = addslashes($ok);

        DBQueryFatal("update projects set pcremote_ok='$safeok' ".
                     "where pid_idx='$idx'");

        $this->project["pcremote_ok"] = $ok;
        return 0;
    }

    function SetAllowWorkbench($onoff) {
        $idx    = $this->pid_idx();
        $onofff = ($onoff ? 1 : 0);

        DBQueryFatal("update projects set allow_workbench='$onoff' ".
                     "where pid_idx='$idx'");

        return 0;
    }

    function Show($token = null) {
        global $TBPROJ_DIR;
        global $MAILMANSUPPORT, $USERNODE;

        $pid                    = $this->pid();
        $proj_idx               = $this->pid_idx();
        $proj_created           = $this->created();
        $proj_name              = $this->name();
        $proj_URL               = $this->URL();
        $proj_public            = YesNo($this->ispublic());
        $proj_funders           = $this->funders();
        $proj_research_type     = $this->research_type();
        $proj_org_type          = $this->org_type();
        $proj_head_idx          = $this->head_idx();
        # These are now booleans, not actual counts.
        $proj_why               = $this->why();
        $status                 = $this->status();
        $expt_count             = $this->expt_count();
        $expt_last              = $this->expt_last();
        $allow_workbench        = $this->allow_workbench();

        if (! ($head_user = User::Lookup($proj_head_idx))) {
            TBERROR("Could not lookup object for user $proj_head_idx", 1);
        }

        # DETER: if a vote token is included, append it to the user/project link
        if (is_null($token)) {
            $showuser_url = CreateURL("showuser", $head_user);
            $showproj_url  = CreateURL("showproject", $this);
        }
        else {
            $showuser_url = CreateURL("showuser", $head_user, 'token', $token);
            $showproj_url  = CreateURL("showproject", $this, 'token', $token);
        }
        $proj_head_uid = $head_user->uid();

        if (!$expt_last) {
            $expt_last = "&nbsp;";
        }

        # display a short label
        function short_text($title, $text) {
?>
<tr>
    <td width=\"30%\"><? echo $title ?>:</td>
    <td><? echo $text ?></td>
</tr>
<?php
        }

        # display text with a colspan of two
        function long_text($title, $text) {
            # clean up the text for HTML display
            $text = preg_replace('/</', '&lt;', $text);
            $text = preg_replace('/\n/', "</p>\r<p>", $text);

?>
<tr><td colspan="2"><? echo $title ?>:</td></tr>
<tr>
    <td colspan="2">
<p><? echo $text ?></p>
    </td>
</tr>
<?php
        }

        function url($title, $url) {
            return "<a href=\"$url\">$title</a>";
        }


        echo "<center>
              <h3>Project Profile</h3>
              </center>
              <table align=center cellpadding=2 border=1>\n";
    
        #
        # Generate the table.
        # 
        short_text('Name', url("$pid ($proj_idx)", $showproj_url));
        short_text('Description', $proj_name);
        short_text('Project Head', url($proj_head_uid, $showuser_url));
        short_text('URL', url($proj_URL, $proj_URL));

        if ($MAILMANSUPPORT) {
            $mmurl   = "gotommlist.php?pid=$pid";
            $link = url("${pid}-users", $mmurl);
            $admin_link = url('(admin access)', "$mmurl&wantadmin=1");

            short_text('Project Mailing List', "$link $admin_link");

            if (ISADMIN()) {
                $mmurl   = "gotommlist.php?listname=${pid}-admin&asadmin=1";
                $link = url("${pid}-admin", $mmurl);

                $mmurl   = "gotommlist.php?listname=${pid}-admin&wantadmin=1";
                $admin_link = url("(admin access)", $mmurl);

                short_text('Project Admin Mailing List', "$link $admin_link");
            }
        }

        short_text('Publicly Visible', $proj_public);
        short_text('Funders', $proj_funders);
        short_text('Research Type', $proj_research_type);
        short_text('Organization Type', $proj_org_type);

        # Fine-grained Datapository access: show node_ids over all sub-groups.
        # Should probably do likewise in individual sub-group pages.
        # "dp_projects" node_attributes are lists of group gid_idxs.
        $query_result =
            DBQueryFatal("select distinct g.gid_idx, a.node_id ".
                         "  from groups as g, node_attributes as a ".
                         "where g.pid_idx='$proj_idx' ".
                         "  and a.attrkey='dp_projects' ".
                         "  and FIND_IN_SET(g.gid_idx, a.attrvalue) ".
                         "order by g.gid_idx, a.node_id");
        $proj_dp_nodes = "";
        while ($row = mysql_fetch_array($query_result)) {
            $node_id = $row["node_id"];

            if ($proj_dp_nodes) $proj_dp_nodes .= ", ";
            $proj_dp_nodes .= $node_id;
        }
        if ($proj_dp_nodes)
            short_text('Datapository Access', $proj_dp_nodes);

        short_text('Created', $proj_created);
        short_text('Experiments Created', $expt_count);
    
        if ($expt_count > 0)
            short_text('Date of last experiment', $expt_last);

        short_text('Status', $status);


        /* Allow Workbench is deprecated.  I am leaving this in as an example of a toggle.
        if (ISADMIN()) {
            $YesNo = YesNo($allow_workbench);
            $flip  = ($allow_workbench ? 0 : 1);
            $toggle_url = url(
                $YesNo,
                "toggle.php?pid=$pid&type=workbench&value=$flip"
            );

            //short_text('Allow Workbench', "$toggle_url (Click to toggle)");
        }
        */

        $proj_why = preg_replace('/</', '&lt;', $proj_why);
        $proj_why = nl2br($proj_why);
        long_text('Purpose', $proj_why);
        echo "</table>\n";
    }

    function ShowGroupList() {
        $groups    = $this->SubGroups();

        echo "<center><h3>Project Groups</h3></center>\n";
        echo "<table id='grouplist' align=center border=1>\n";
        echo "<thead class='sort'>";
        echo "<tr>
               <th>GID</th>
               <th>Description</th>
               <th>Leader</th>
              </tr></thead>\n";

        foreach ($groups as $group) {
            $gid         = $group->gid();
            $desc        = $group->description();
            $leader      = $group->leader();
            $leader_user = $group->GetLeader();

            $showuser_url  = CreateURL("showuser", $leader_user);
            $showgroup_url = CreateURL("showgroup", $group);

            echo "<tr>
                   <td><A href='$showgroup_url'>$gid</a></td>
                   <td>$desc</td>
                   <td><A href='$showuser_url'>$leader</A></td>
                 </tr>\n";
        }
        echo "</table>\n";

        echo "<script type='text/javascript' language='javascript'>
               sorttable.makeSortable(getObjbyName('grouplist'));
             </script>\n";
    }

    function ShowStats() {
        $pid_idx  = $this->pid_idx();

        $query_result =
            DBQueryFatal("select * from project_stats ".
                         "where pid_idx='$pid_idx'");

        if (! mysql_num_rows($query_result)) {
            return;
        }
        $row = mysql_fetch_assoc($query_result);

        #
        # Not pretty printed yet.
        #
        echo "<table align=center border=1>\n";
    
        foreach($row as $key => $value) {
            echo "<tr>
                      <td>$key:</td>
                      <td>$value</td>
                  </tr>\n";
        }
        echo "</table>\n";
    }

}
