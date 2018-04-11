TYPE=VIEW
query=(select `g`.`pid` AS `pid`,`f`.`uid` AS `uid` from `tbdb`.`user_features` `f` join `tbdb`.`group_membership` `g` where ((`g`.`uid` = `f`.`uid`) and (`g`.`pid` = `g`.`gid`) and (`f`.`feature` = \'ClassIncomplete\'))) union (select `p`.`pid` AS `pid`,`g`.`uid` AS `uid` from `tbdb`.`group_membership` `g` join `tbdb`.`projects` `p` where ((`g`.`pid` = `g`.`gid`) and (`g`.`pid` = `p`.`pid`) and (`p`.`class` = 1) and (`g`.`trust` in (\'group_root\',\'project_root\'))))
md5=70e469b986f30630d286659e757fe255
updatable=0
algorithm=0
definer_user=root
definer_host=localhost
suid=2
with_check_option=0
timestamp=2014-10-02 19:59:28
create-version=1
source=(select g.pid, f.uid from tbdb.user_features as f,\n tbdb.group_membership as g where g.uid=f.uid and g.pid=g.gid\n and f.feature=\'ClassIncomplete\') union\n(select p.pid, g.uid from tbdb.group_membership as g, tbdb.projects as p \n where g.pid=g.gid and g.pid=p.pid and p.class=1 and\n g.trust in (\'group_root\',\'project_root\'))
client_cs_name=latin1
connection_cl_name=latin1_swedish_ci
view_body_utf8=(select `g`.`pid` AS `pid`,`f`.`uid` AS `uid` from `tbdb`.`user_features` `f` join `tbdb`.`group_membership` `g` where ((`g`.`uid` = `f`.`uid`) and (`g`.`pid` = `g`.`gid`) and (`f`.`feature` = \'ClassIncomplete\'))) union (select `p`.`pid` AS `pid`,`g`.`uid` AS `uid` from `tbdb`.`group_membership` `g` join `tbdb`.`projects` `p` where ((`g`.`pid` = `g`.`gid`) and (`g`.`pid` = `p`.`pid`) and (`p`.`class` = 1) and (`g`.`trust` in (\'group_root\',\'project_root\'))))
