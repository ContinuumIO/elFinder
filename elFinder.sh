#!/bin/bash
PORT=$1
URLPREFIX=$2
el_dir=/opt/wakari/elFinder/
user_project_dir=${el_dir}/deploy${URLPREFIX}

echo ${user_project_dir}
if [ ! -d ${user_project_dir} ] 
    then
        echo "Creating dir"
        mkdir -p ${user_project_dir}
        cp ${el_dir}/deploy/index.html ${user_project_dir}/index.html
        cp -r ${el_dir}/deploy/php ${user_project_dir}/php
fi
cd ${el_dir}/deploy
/usr/local/php5/bin/php -S 0.0.0.0:$PORT 
