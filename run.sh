gstlogtime=`date +\%Y\%m\%d\%H\%M\%S`
gstlogfile=/root/DBZIS/log/generateSyncTasks/${gstlogtime}.log
nohup /root/DBZIS/dbzis -c > ${gstlogfile} 2>&1 &
sleep 2
search_dir=/root/DBZIS/source
for entry in "$search_dir"/*
do
  entry=${entry#"/root/DBZIS/source/"}
  entry=${entry%".json"}
  source="${entry}"
  logtime=`date +\%Y\%m\%d\%H\%M\%S`
  logfile=/root/DBZIS/log/syncExe/${source}-${logtime}.log
  nohup /root/DBZIS/dbzis -ss ${source} > ${logfile} 2>&1 &
  echo $source
  sleep 1
done
