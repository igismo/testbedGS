TYPE=VIEW
query=select `r`.`node_id` AS `node_id`,`m`.`uid` AS `uid`,`m`.`pid` AS `pid` from ((`tbdb`.`reserved` `r` join `tbdb`.`group_membership` `m` on(((`r`.`pid` = `m`.`pid`) and (`m`.`pid` = `m`.`gid`) and ((`m`.`trust` = \'group_root\') or (`m`.`trust` = \'project_root\'))))) join `tbdb`.`projects` `p` on(((`m`.`pid` = `p`.`pid`) and (`p`.`class` <> 0))))
md5=32c0ac49ae68a24cea361df2df533455
updatable=1
algorithm=0
definer_user=root
definer_host=localhost
suid=2
with_check_option=0
timestamp=2014-10-02 19:59:28
create-version=1
source=select r.node_id, m.uid, m.pid from tbdb.reserved as r\n    join tbdb.group_membership as m on r.pid = m.pid and m.pid=m.gid\n    and (m.trust=\'group_root\' or m.trust=\'project_root\')\n    join tbdb.projects as p on m.pid=p.pid and p.class <> 0
client_cs_name=latin1
connection_cl_name=latin1_swedish_ci
view_body_utf8=select `r`.`node_id` AS `node_id`,`m`.`uid` AS `uid`,`m`.`pid` AS `pid` from ((`tbdb`.`reserved` `r` join `tbdb`.`group_membership` `m` on(((`r`.`pid` = `m`.`pid`) and (`m`.`pid` = `m`.`gid`) and ((`m`.`trust` = \'group_root\') or (`m`.`trust` = \'project_root\'))))) join `tbdb`.`projects` `p` on(((`m`.`pid` = `p`.`pid`) and (`p`.`class` <> 0))))
