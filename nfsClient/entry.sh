#! /bin/sh
###-e
#### ADD INITIAL FILES
set -m

#mkdir -p "$MOUNTPOINT"
echo "-t $FSTYPE -o $MOUNT_OPTIONS $SERVER:$SHARE $MOUNTPOINT"
rpcbind -f &
mount -t "$FSTYPE" -o "$MOUNT_OPTIONS" "$SERVER:$SHARE" "$MOUNTPOINT"
mount | grep nfs
fg

