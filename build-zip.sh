#!/bin/sh

# Helper script to generate a .zip of the whole plugin, suitable for 
# dropping into a Wordpress install. As arguments, pass it the plugin
# slug (e.g. my-great-plugin) and release tag (e.g. origin/tags/1.2).

# ======================================================================
# Make a temporary workspace copy
# ======================================================================
rel=$(basename $2)
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
rm -rf AllSpark/custom-update-server AllSpark/examples AllSpark/tests
find . -name ".git*" -exec rm -rf {} \;
find . -name ".travis*" -exec rm -rf {} \;
find . -name "*.sh" -exec rm -rf {} \; 

# ======================================================================
# Zip up the project
# ======================================================================
cd ..
mv $workspacedir $1
zip -q -r $1-$rel.zip $1/*
cp $1-$rel.zip $workspace

# ======================================================================
# Clear temporary workspace
# ======================================================================
cd $workspace
rm -rf $tmp
