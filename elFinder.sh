#!/bin/bash
PORT=$1
cd ${PWD}/deploy
/usr/local/php5/bin/php -S localhost:$1
