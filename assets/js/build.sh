#!/bin/sh
#
# MIT License
# 
# Copyright (c) 2023 Kai Thoene
# 
# Permission is hereby granted, free of charge, to any person obtaining a copy
# of this software and associated documentation files (the "Software"), to deal
# in the Software without restriction, including without limitation the rights
# to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
# copies of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:
# 
# The above copyright notice and this permission notice shall be included in all
# copies or substantial portions of the Software.
# 
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
# IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
# FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
# AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
# LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
# OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
# SOFTWARE.
#
#----------
# Get Startup Variables
ME="$0"
MYNAME=`basename "$ME"`
MYDIR=`dirname "$ME"`
MYDIR=`cd "$MYDIR"; pwd`
WD=`pwd`
#
#----------
# Library Script Functions
#
error() {
  echo "\033[1;31mE\033[0;1m $MYNAME: ${*}\033[0m" >&2
}

info() {
  echo "\033[1;36mI\033[0m $MYNAME: ${*}\033[0m" >&2
}

debug() {
  echo "\033[1;34mD\033[0m $MYNAME: ${*}\033[0m" >&2
}

warn() {
  echo "\033[1;33mW\033[0m $MYNAME: ${*}\033[0m" >&2
}

log() {
  LOGFILE="${MYNAME}.log"
  case "$1" in
    DEBUG|INFO|WARN|ERROR|CRIT) STAGE="$1"; shift;;
    *) STAGE="----";;
  esac
  STAGE="`echo "$STAGE     " | sed -e 's/^\(.\{5\}\).*$/\1/'`"
  TIMESTAMP="`date +%Y%m%d-%H:%M:%S.%N | sed -e 's/\.\([0-9]\{3\}\)[0-9]*$/.\1/'`"
  echo "${TIMESTAMP} ${STAGE} ${*}" >> "$LOGFILE"
  SIZE=`du -b "$LOGFILE" | cut -f1`
  if [ $SIZE -gt 2000000 ]; then
    INDEX=10
    while [ $INDEX -ge 1 ]; do
      INDEXPLUS=`echo "1+$INDEX" | bc`
      SUBLOGFILE="${LOGFILE}.$INDEX"
      [ -f "$SUBLOGFILE" ] && {
        if [ $INDEX -ge 10 ]; then
          rm -f "$SUBLOGFILE"
        else
          mv "$SUBLOGFILE" "${LOGFILE}.$INDEXPLUS"
        fi
      }
      INDEX=$INDEXPLUS
    done
  fi
}

cmd_exists() {
  type "$1" 2>&1 > /dev/null
  return $?
}

infofile() {
  while [ -n "$1" ]; do
    [ -r "$1" -a -s "$1" ] && {
      sed 's,.*,\x1b[1;36mI\x1b[0m '"$MYNAME"': \x1b[1;32m&\x1b[0m,' "$1" >&2
    }
    shift
  done
}

check_tool() {
  while [ -n "$1" ]; do
    type "$1" > /dev/null 2>&1 || return 1
    shift
  done
  return 0
}

check_tools() {
  while [ -n "$1" ]; do
    check_tool "$1" || {
      error "Cannot find program '$1'!"
      exit 1
    }
    shift
  done
  return 0
}

getyesorno() {
  # Returns 0 for YES. Returns 1 for NO.
  # Returns 2 for abort.
  DEFAULT_ANSWER="$1"
  USER_PROMPT="$2"
  unset READ_OPTS
  echo " " | read -n 1 >/dev/null 2>&1 && READ_OPTS='-n 1'
  #--
  unset OK_FLAG
  while [ -z "$OK_FLAG" ]; do
    read -r $READ_OPTS -p "QUESTION -- $USER_PROMPT" YNANSWER
    [ $? -ne 0 ] && return 2
    if [ -z "$YNANSWER" ]; then
      YNANSWER="$DEFAULT_ANSWER"
    else
      echo
    fi
    case "$YNANSWER" in
      [yY])
        YNANSWER=Y
        return 0
        ;;
      [nN])
        YNANSWER=N
        return 1
        ;;
    esac
  done
}  # getyesorno

read_string() {
  # Usage: read_string PROMPT VARIABLE
  # Returns 0 for YES. Returns 1 for NO.
  USER_PROMPT="$1"
  VARIABLE="$2"
  #--
  unset OK_FLAG
  while [ -z "$OK_FLAG" ]; do
    read -r -p "QUESTION -- $USER_PROMPT" $VARIABLE
    [ $? -ne 0 ] && return 1
    # VALUE=`eval echo \\\${$VARIABLE}`
    # echo "$VARIABLE=$VALUE RC=$RC"
    # [ -z "$VALUE" ] && return 1
    return 0
  done
}  # read_string

open34() {
	OPEN34_TMPFILE=`mktemp -p "$MYDIR" "$MYNAME-34-XXXXXXX"`
	exec 3>"$OPEN34_TMPFILE"
	exec 4<"$OPEN34_TMPFILE"
	rm -f "$OPEN34_TMPFILE"
}  # open34

close34() {
	exec 3>&-
	exec 4<&-
}  # close34

open56() {
	OPEN56_TMPFILE=`mktemp -p "$MYDIR" "$MYNAME-56-XXXXXXX"`
	exec 5>"$OPEN56_TMPFILE"
	exec 6<"$OPEN56_TMPFILE"
	rm -f "$OPEN56_TMPFILE"
}  # open56

close56() {
	exec 5>&-
	exec 6<&-
}  # close56

do_check_cmd() {
  echo "$*"
  "$@" || {
    error "Cannot do this! CMD='$*'"
    exit 1
  }
}

do_check_cmd_no_echo() {
  "$@" || {
    error "Cannot do this! CMD='$*'"
    exit 1
  }
}

do_cmd() {
  echo "$*"
  "$@"
}

do_check_cmd_output_only_on_error() {
  echo "$*"
  open34
  "$@" >&3 2>&1
  DO_CHECK_CMD_RC=$?
	[ $DO_CHECK_CMD_RC != 0 ] && cat <&4
	close34
	[ $DO_CHECK_CMD_RC != 0 ] && {
    error "Cannot do this! CMD='$*'"
    exit $DO_CHECK_CMD_RC
  }
  return 0
}

do_by_xterm() {
  TMPFILE_PARAM=`mktemp -p "$MYDIR" "$MYNAME-XTERM-XXXXXXX"`
  while [ -n "$1" ]; do
    echo -n "\"$1\" " >> "$TMPFILE_PARAM"
    shift
  done
  XTERM_CMD=`cat "$TMPFILE_PARAM"`
  rm -f "$TMPFILE_PARAM"; unset TMPFILE_PARAM
  TMPFILE_LOG=`mktemp -p "$MYDIR" "$MYNAME-XTERM-XXXXXXX"`
  TMPFILE_RC=`mktemp -p "$MYDIR" "$MYNAME-XTERM-XXXXXXX"`
  xterm -l -lf "$TMPFILE_LOG" -e /bin/sh -c "$XTERM_CMD; echo $? > \"$TMPFILE_RC\""
  XTERM_RC=`cat "$TMPFILE_RC"`
  rm -f "$TMPFILE_RC"; unset TMPFILE_RC
  infofile "$TMPFILE_LOG"; rm -f "$TMPFILE_LOG"; unset TMPFILE_LOG
  [ "$XTERM_RC" = 0 ] && return 0
  [ -n "$XTERM_RC" ] && return "$XTERM_RC"
  return 1
} # do_by_xterm

cmdpath() {
  CMD="$*"
  case "$CMD" in
    /*)
      [ -x "$CMD" ] && FOUNDPATH="$CMD"
      ;;
    */*)
      [ -x "$CMD" ] && FOUNDPATH="$CMD"
      ;;
    *)
      IFS=:
      for DIR in $PATH; do
        if [ -x "$DIR/$CMD" ]; then
          FOUNDPATH="$DIR/$CMD"
          break
        fi
      done
      unset IFS
      ;;
  esac
  if [ -n "$FOUNDPATH" ]; then
    echo "$FOUNDPATH"
  else
    return 1
  fi
}  # cmdpath

is_glibc() {
	ldd --version 2>&1 | head -1 | grep -iE '(glibc|gnu)' > /dev/null 2>&1
} # is_glibc

unset TMPFILE
unset TMPDIR
unset OPEN34_TMPFILE
at_exit() {
  [ -n "$TMPFILE" ] && [ -f "$TMPFILE" ] && rm -f "$TMPFILE"
  [ -n "$TMPDIR" ] && [ -d "$TMPDIR" ] && rm -rf "$TMPDIR"
  [ -n "$OPEN34_TMPFILE" ] && [ -f "$OPEN34_TMPFILE" ] && rm "$OPEN34_TMPFILE"
} # at_exit

trap at_exit EXIT HUP INT QUIT TERM
#
#----------
  #if TMPDIR=`mktemp -p . -d`; then
  #  trap at_exit EXIT HUP INT QUIT TERM && \
  #  (
  #    cd "$TMPDIR"
  #    echo "DISTRIBUTION=$DISTNAME"
  #  )
  #else
  #  echo "ERROR -- Cannot create temporary directory! CURRENT-DIR=`pwd`" >&2
  #  return 1
  #fi
#
#----------
# Internal Script Variables
#

#
#----------
# Internal Script Functions
#
clean() {
  (
    cd "$MYDIR"
    for FN in *.[jJ][sS]; do
      BN=`echo "$FN" | sed -e 's/\.[jJ][sS]$//'`
      BN2=`echo "$FN" | sed -e 's/\.min\.[jJ][sS]$//'`
      [ ! "${BN}" = "${BN2}.min" -a -f "${BN}.min.js" ] && { rm -f "${BN}.min.js"; }
    done
    info "Javascript minimal files cleaned."
  )
  return 0
}

install() {
  (
    cd "$MYDIR"
    COUNTER=0
    for FN in *.[jJ][sS]; do
      [ -r "$FN" ] && {
        BN=`echo "$FN" | sed -e 's/\.[jJ][sS]$//'`
        BN2=`echo "$FN" | sed -e 's/\.min\.[jJ][sS]$//'`
        if [ ! "${BN}" = "${BN2}.min" ]; then
          rm -f "${BN}.min.js"
          uglifyjs "$FN" -o "${BN}.min.js" || { error "Cannot uglify Javascript file! FILE='$FN'"; exit 1; }
          COUNTER=`echo "1+$COUNTER" | bc`
        fi
      }
    done
    [ $COUNTER -gt 0 ] && info "$COUNTER Javascript files uglified."
  )
  return 0
}

usage() {
  cat >&2 <<EOF
Usage: $MYNAME [OPTIONS] COMMAND [...]
Commands:
  install -- Install project.
  build   -- Same as install.
Options:
  -h, --help -- Print this text.
EOF
}
#
#----------
# Read options.
#
SCRIPT_ARGS_HERE="false"
open56
while [ "${#}" != "0" ]; do
  SCRIPT_OPTION="true"
	case "${1}" in
    --clean) info "CLEAN"; exit $?;;
		--quit) SCRIPT_OPT_QUIT=true; continue;;
    --invalid) log CRIT "'${1}' invalid. Use ${1}=... instead"; exit 1; continue;;
		--help) usage; exit 0;;
    --all) SCRIPT_OPT_BUILD_ALL=true; continue;;
		--*) log CRIT "invalid option '${1}'"; usage 1; exit 1;;
		# Posix getopt stops after first non-option
		-*);;
		*) echo "$1" >&5; SCRIPT_OPTION="false"; SCRIPT_ARGS_HERE="true";;  # Put normal args to tempfile.
	esac
  if [ "$SCRIPT_OPTION" = "true" ]; then
    flag="${1#?}"
    while [ -n "${flag}" ]; do
      case "${flag}" in
        h*) usage; exit 0;;
        a) SCRIPT_OPT_BUILD_ALL=true;;
        c*) info "CLEAN"; exit $? ;;
        C*) info "BIG-CLEAN"; exit $? ;;
        q*) SCRIPT_OPT_QUIT=true;;
        Q*) exit 0;;
        *) log CRIT "invalid option -- '${flag%"${flag#?}"}'"; usage 1; exit 1;;
      esac
      flag="${flag#?}"
    done
  fi
	shift
done
#
#----------------------------------------------------------------------
# START
#
[ "$SCRIPT_OPT_QUIT" = true ] && {
  info "QUIT"
  exit 0
}
#
check_tools uglifyjs
#
cat <&6 | while read ARG; do
  case "$ARG" in
    install|build) install; RC=$?; [ $RC = 0 ] || exit $RC;;
    clean) clean; RC=$?; [ $RC = 0 ] || exit $RC;;
    *) error "Unknown command! CMD='$ARG'"; exit 10;;
  esac
done
close56
#
if [ "$SCRIPT_ARGS_HERE" = "false" ]; then
  usage
fi
