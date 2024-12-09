#!/bin/bash

mkdir -p "/tmp/GitHub/unraid.patch/source/unraid.patch/usr/local/emhttp/plugins/unraid.patch/"

cp /usr/local/emhttp/plugins/unraid.patch/* /tmp/GitHub/unraid.patch/source/unraid.patch/usr/local/emhttp/plugins/unraid.patch -R -v -p
find . -maxdepth 9999 -noleaf -type f -name "._*" -exec rm -v "{}" \;

