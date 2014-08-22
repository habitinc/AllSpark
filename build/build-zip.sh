#!/bin/sh

# Helper script to generate a .zip of the whole plugin, suitable for 
# dropping into a Wordpress install. As arguments, pass it the plugin
# slug (e.g. my-great-plugin) and version (e.g. 1.2).

# ======================================================================
# Make a temporary workspace copy
# ======================================================================
tmp=`mktemp -d 2>/dev/null || mktemp -d -t 'allsparkbuild'`
workspace=`pwd`
workspacedir=$(basename $workspace)
cp -r $workspace $tmp/

# ======================================================================
# Remove development things from production build
# ======================================================================
cd $tmp
cd $workspacedir
rm -rf .git
rm -rf AllSpark/custom-update-server AllSpark/examples AllSpark/tests AllSpark/build
find . -name ".git*" -exec rm -rf {} \;
find . -name ".travis*" -exec rm -rf {} \;

# ======================================================================
# Zip up the project
# ======================================================================
cd ..
mv $workspacedir $1
zip -q -r $1-$2.zip $1/*
cp $1-$2.zip $workspace

# ======================================================================
# Clear temporary workspace
# ======================================================================
cd $workspace
rm -rf $tmp
