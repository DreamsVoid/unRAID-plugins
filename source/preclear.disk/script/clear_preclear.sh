#!/bin/bash

is_running()
{
  [ -e "/proc/${1}/exe" ] && return 0 || return 1;
}

stop_kill()
{
  if ! is_running $1; then return 0; fi 

  kill -s SIGINT $1

  for (( i = 0; i < $(( $2 * 5 )); i++ )); do
    if ! is_running $1; then
      return 0; 
    fi
    sleep 0.2
  done

  kill -s SIGKILL $1

  for (( i = 0; i < $(( $2 * 5 )); i++ )); do
    if ! is_running $1; then
      return 0; 
    fi
    sleep 0.2
  done
}

get_serial()
{
  attrs=$(udevadm info --query=property --name="${1}" 2>/dev/null)
  serial_number=$(echo -e "$attrs" | awk -F'=' '/ID_SCSI_SERIAL/{print $2}')
  if [ -z "$serial_number" ]; then
    serial_number=$(echo -e "$attrs" | awk -F'=' '/ID_SERIAL_SHORT/{print $2}')
  fi
  echo $serial_number
}


for dir in $(find /tmp/.preclear -mindepth 1 -maxdepth 1 -type d ); do
  pidfile="$dir/pid"
  disk_name=$(basename $dir)
  serial=$( get_serial $disk_name )

  if [ -f "$pidfile" ]; then
    pid=$(cat $pidfile)

    if ! is_running $pid; then continue; fi

    children=$(ps -o pid= --ppid $pid 2>/dev/null)
    stop_kill $pid 10

    for cpid in $children; do
      ppid=$(ps -o ppid= -p $cpid 2>/dev/null)
      if [ "$ppid" == "$pid" ]; then
        stop_kill $cpid 10
      fi
    done
  fi

  while read dd_pid; do
    stop_kill $dd_pid 10
  done < <( ps -o "%p|" -o "cmd:100" --no-headers -p $(pidof dd) | grep /dev/$disk_name | cut -d '|' -f 1)

  rm -rf $dir
  if [ -n "$serial" ]; then

    session="preclear_disk_${serial}"

    docker=$(/usr/bin/docker container ls --filter='Name=${session}' --format='{{println .Names}}'|wc -l)
    if [ "$docker" -gt 0 ]; then
      /usr/bin/docker stop "$session"
    fi
    tmux kill-session -t "$session"

    rm -f "/tmp/preclear_stat_${disk_name}"
  fi
done

while read session; do
  echo "killing preclear session: $session"
  tmux send -t "$session" "C-c" ENTER 2>/dev/null
  sleep 2
  tmux kill-session -t "$session" >/dev/null 2>&1
done < <(tmux ls 2>/dev/null|grep "preclear_disk_"|cut -d ':' -f 1)