#!/bin/bash
# traptest.sh

trap 'echo "Caught EXIT"' EXIT
trap 'echo "Caught SIGINT"' SIGINT
trap 'echo "Caught SIGTERM"' SIGTERM
trap 'echo "Caught SIGKILL"' SIGKILL
trap 'echo "Caught SIGQUIT"' SIGQUIT

echo "Sleeping 600 seconds."
sleep 600 &
wait
