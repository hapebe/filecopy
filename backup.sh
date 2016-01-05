#!/bin/bash
sudo mount -t cifs -o username=hb@erp.local,password=?,iocharset=utf8,sec=ntlm //192.168.80.240/e$ /mnt/dc01-e
sudo mount -t ext4 /dev/sdb1 /mnt/bak
sudo chmod 777 /mnt/bak
cd /home/hostmaster/backup-experiment
php /var/www/d7u/sites/all/modules/filecopy/filecopy.php
sudo umount /mnt/bak
sudo umount /mnt/dc01-e
