TYPE=VIEW
query=select `i`.`node_id` AS `node_id`,`i`.`iface` AS `iface`,`i`.`card` AS `card`,`i`.`port` AS `port`,`i`.`mac` AS `mac` from (`tbdb`.`interfaces` `i` join `tbdb`.`wires` `w` on(((`w`.`node_id1` = `i`.`node_id`) and (`w`.`port1` = `i`.`port`) and (`w`.`card1` = `i`.`card`) and (`w`.`type` <> \'Control\'))))
md5=4b6d08d3c9dae4289c44405d7909d793
updatable=1
algorithm=0
definer_user=root
definer_host=localhost
suid=2
with_check_option=0
timestamp=2014-10-02 19:59:28
create-version=1
source=select i.node_id, i.iface, i.card, i.port, i.mac\nfrom tbdb.interfaces as i join tbdb.wires as w on\nw.node_id1 = i.node_id and w.port1 = i.port and w.card1= i.card\nand w.type <> \'Control\'
client_cs_name=latin1
connection_cl_name=latin1_swedish_ci
view_body_utf8=select `i`.`node_id` AS `node_id`,`i`.`iface` AS `iface`,`i`.`card` AS `card`,`i`.`port` AS `port`,`i`.`mac` AS `mac` from (`tbdb`.`interfaces` `i` join `tbdb`.`wires` `w` on(((`w`.`node_id1` = `i`.`node_id`) and (`w`.`port1` = `i`.`port`) and (`w`.`card1` = `i`.`card`) and (`w`.`type` <> \'Control\'))))
