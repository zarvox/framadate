#!/bin/bash

FLAG_FILE=/var/action.txt

if [ ! -f $FLAG_FILE ] ; then
    echo "poll" > $FLAG_FILE
fi
/opt/app/.sandstorm/launcher.sh
