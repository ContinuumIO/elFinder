#!/bin/bash
PORT=$1
el_dir=/opt/wakari/elFinder/
cd $el_dir/deploy
/usr/bin/php -S 0.0.0.0:$1
