TYPE=VIEW
query=select `m`.`lanid` AS `lanid`,`m`.`memberid` AS `memberid`,`m`.`attrvalue` AS `node_id`,`a`.`attrvalue` AS `iface` from (`tbdb`.`lan_member_attributes` `m` join `tbdb`.`lan_member_attributes` `a` on(((`m`.`lanid` = `a`.`lanid`) and (`m`.`memberid` = `a`.`memberid`) and (`a`.`attrkey` = \'iface\') and (`m`.`attrkey` = \'node_id\'))))
md5=81ab0ee46146eaca0a3b64c65a553b67
updatable=1
algorithm=0
definer_user=root
definer_host=localhost
suid=2
with_check_option=0
timestamp=2014-10-02 19:59:28
create-version=1
source=select m.lanid, m.memberid, m.attrvalue node_id, a.attrvalue iface\nfrom tbdb.lan_member_attributes as m\njoin tbdb.lan_member_attributes as a\non m.lanid=a.lanid and m.memberid=a.memberid\nand a.attrkey=\'iface\' and m.attrkey=\'node_id\'
client_cs_name=latin1
connection_cl_name=latin1_swedish_ci
view_body_utf8=select `m`.`lanid` AS `lanid`,`m`.`memberid` AS `memberid`,`m`.`attrvalue` AS `node_id`,`a`.`attrvalue` AS `iface` from (`tbdb`.`lan_member_attributes` `m` join `tbdb`.`lan_member_attributes` `a` on(((`m`.`lanid` = `a`.`lanid`) and (`m`.`memberid` = `a`.`memberid`) and (`a`.`attrkey` = \'iface\') and (`m`.`attrkey` = \'node_id\'))))
