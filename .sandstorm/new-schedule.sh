#!/bin/bash

FLAG_FILE=/var/action.txt

if [ ! -f $FLAG_FILE ] ; then
    echo "schedule" > $FLAG_FILE
fi
/opt/app/.sandstorm/launcher.sh
