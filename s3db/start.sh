#!/usr/bin/env bash

# Fix for windows hosts manging run files
dos2unix /etc/service/*/run

# Fix permissions on run files
chmod +x /etc/service/*/run

# Define shutdown + cleanup procedure
cleanup() {
	echo ""
	echo "SIGTERM called!"
	echo "Container stop requested, running final dump + cleanup"
	/sync/sync --push
	echo "Good bye!"
	exit 0
}

# Trap SIGTERM
echo "Setting SIGTERM trap"
trap 'cleanup' SIGTERM

# Start Runit.
echo "Starting Runit."
exec runsvdir -P /etc/service &

sleep infinity &
wait
