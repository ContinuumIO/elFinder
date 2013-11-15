#!/bin/bash
PORT=$1
URLPREFIX=$2
el_dir=/opt/wakari/elFinder
user_project_dir=${el_dir}/deploy${URLPREFIX}
arr=`echo $URLPREFIX | tr "/" "\n"`

#Can't seem to get this as a bash array without the copy -- $arr[0]
j=0
for i in $arr
do
    data[$j]=$i
    j=$((j+1))
done

for i in $data
do
    echo "> $i"
done

user=${data[0]}
project=${data[1]}

log="/var/log/wakari/appLogs/$user/$project/$user/filemanager.log"
echo $log
#echo "init log" > $log
id >> $log
echo $1 
echo $2 
echo $el_dir 
echo $user_project_dir 

echo "PORT"
echo $PORT, $URLPREFIX, $el_dir
echo ${user_project_dir}

if [ ! -d ${user_project_dir} ]
    then
        echo "Creating dir"
        mkdir -p ${user_project_dir}
        ln -s ${el_dir}/deploy/index.html ${user_project_dir}/index.html
        ln -s ${el_dir}/deploy/php ${user_project_dir}/php
fi

# export the PROJECT_HOME env var
p=`echo ${URLPREFIX} | cut -d '/' -f 1-3`
p_home='/projects'${p}
export PROJECT_HOME=${p_home}

cd ${el_dir}/deploy
/usr/bin/php -S 0.0.0.0:$PORT 
