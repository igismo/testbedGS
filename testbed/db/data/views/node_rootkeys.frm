TYPE=VIEW
query=select `r`.`node_id` AS `node_id`,`r`.`uid` AS `uid`,`k`.`pubkey` AS `pubkey` from (`views`.`node_roots` `r` join `views`.`top_key` `k` on((`k`.`uid` = `r`.`uid`)))
md5=cf66a1a55924fb204f1c89e5003244ee
updatable=0
algorithm=0
definer_user=root
definer_host=localhost
suid=2
with_check_option=0
timestamp=2014-10-02 19:59:28
create-version=1
source=select r.node_id, r.uid, k.pubkey from views.node_roots as r\njoin views.top_key as k on k.uid=r.uid
client_cs_name=latin1
connection_cl_name=latin1_swedish_ci
view_body_utf8=select `r`.`node_id` AS `node_id`,`r`.`uid` AS `uid`,`k`.`pubkey` AS `pubkey` from (`views`.`node_roots` `r` join `views`.`top_key` `k` on((`k`.`uid` = `r`.`uid`)))
