#!/bin/bash
PORT=$1
URLPREFIX=$2
el_dir=/opt/wakari/elFinder/
user_project_dir=${el_dir}/deploy${URLPREFIX}

log="/tmp/elFinder.log"
echo "init log" > $log
id >> $log
echo $1 >> $log
echo $2 >> $log
echo $el_dir >> $log
echo $user_project_dir >> $log

echo ${user_project_dir}
if [ ! -d ${user_project_dir} ] 
    then
        echo "Creating dir"
        mkdir -p ${user_project_dir}
        ln -s ${el_dir}/deploy/index.html ${user_project_dir}/index.html
        ln -s ${el_dir}/deploy/php ${user_project_dir}/php
fi
cd ${el_dir}/deploy
/usr/bin/php -S 0.0.0.0:$PORT 
