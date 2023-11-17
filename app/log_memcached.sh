#!/bin/bash

PID=$$

while read line; do
echo "${PID} Memcached Log : ${line}"
done
