TYPE=VIEW
query=select `k`.`uid` AS `uid`,`k`.`idx` AS `idx`,`k`.`pubkey` AS `pubkey` from `tbdb`.`user_pubkeys` `k` where (`k`.`idx` = (select `l`.`idx` from `tbdb`.`user_pubkeys` `l` where ((`l`.`pubkey` like \'ssh-rsa%\') and (`l`.`uid` = `k`.`uid`) and (`l`.`external` = 0) and (not((`l`.`comment` like \'%emulab.pem%\')))) order by `l`.`stamp` desc limit 1))
md5=32434ff064b6a2ae54b208e39303de7b
updatable=0
algorithm=0
definer_user=root
definer_host=localhost
suid=2
with_check_option=0
timestamp=2014-10-02 19:59:28
create-version=1
source=select k.uid, k.idx, k.pubkey from tbdb.user_pubkeys as k\nwhere k.idx=\n(select l.idx from tbdb.user_pubkeys as l where l.pubkey like \'ssh-rsa%\'\n    and l.uid=k.uid and l.external=0 and not l.comment like \'%emulab.pem%\'\n    order by stamp desc limit 1)
client_cs_name=latin1
connection_cl_name=latin1_swedish_ci
view_body_utf8=select `k`.`uid` AS `uid`,`k`.`idx` AS `idx`,`k`.`pubkey` AS `pubkey` from `tbdb`.`user_pubkeys` `k` where (`k`.`idx` = (select `l`.`idx` from `tbdb`.`user_pubkeys` `l` where ((`l`.`pubkey` like \'ssh-rsa%\') and (`l`.`uid` = `k`.`uid`) and (`l`.`external` = 0) and (not((`l`.`comment` like \'%emulab.pem%\')))) order by `l`.`stamp` desc limit 1))
