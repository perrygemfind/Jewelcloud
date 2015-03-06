#!/bin/sh
if ps -ef | grep -v grep | grep "sync_cron.php" ; then  
        exit 0
else
	cd /home/mark112/public_html/waterfall/
        /usr/local/bin/php /home/mark112/public_html/waterfall/gemfind/sync_cron.php
	
	clear
	# Demo/Test source codoe
	#echo "Good morning, world."
	#/usr/local/bin/php /home/mark112/public_html/waterfall/shell/indexer.php --reindexall
	#echo "At the end of world." 
    exit 0
fi




