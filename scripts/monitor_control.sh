#!/bin/bash

# EDI Monitor Control Script
# Provides easy management of the EDI file monitoring service

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
DAEMON_SCRIPT="$PROJECT_DIR/monitor_daemon.php"
PID_FILE="/var/run/edi_monitor.pid"

function show_usage() {
    echo "EDI Monitor Control Script"
    echo ""
    echo "Usage: $0 {start|stop|restart|status|test|single|logs}"
    echo ""
    echo "Commands:"
    echo "  start    Start the monitor daemon"
    echo "  stop     Stop the monitor daemon"
    echo "  restart  Restart the monitor daemon"
    echo "  status   Show daemon status"
    echo "  test     Test SFTP connection"
    echo "  single   Run single monitoring cycle"
    echo "  logs     Show recent log entries"
    echo ""
}

function get_pid() {
    if [ -f "$PID_FILE" ]; then
        cat "$PID_FILE"
    fi
}

function is_running() {
    local pid=$(get_pid)
    if [ -n "$pid" ] && kill -0 "$pid" 2>/dev/null; then
        return 0
    else
        return 1
    fi
}

function start_monitor() {
    if is_running; then
        echo "Monitor is already running (PID: $(get_pid))"
        return 1
    fi
    
    echo "Starting EDI File Monitor..."
    cd "$PROJECT_DIR"
    php "$DAEMON_SCRIPT" --daemon
    
    sleep 2
    
    if is_running; then
        echo "Monitor started successfully (PID: $(get_pid))"
        return 0
    else
        echo "Failed to start monitor"
        return 1
    fi
}

function stop_monitor() {
    local pid=$(get_pid)
    
    if ! is_running; then
        echo "Monitor is not running"
        # Clean up stale PID file
        [ -f "$PID_FILE" ] && rm "$PID_FILE"
        return 0
    fi
    
    echo "Stopping EDI File Monitor (PID: $pid)..."
    kill -TERM "$pid"
    
    # Wait for graceful shutdown
    local count=0
    while is_running && [ $count -lt 10 ]; do
        sleep 1
        count=$((count + 1))
    done
    
    if is_running; then
        echo "Forcing monitor shutdown..."
        kill -KILL "$pid"
        sleep 1
    fi
    
    if ! is_running; then
        echo "Monitor stopped successfully"
        [ -f "$PID_FILE" ] && rm "$PID_FILE"
        return 0
    else
        echo "Failed to stop monitor"
        return 1
    fi
}

function restart_monitor() {
    echo "Restarting EDI File Monitor..."
    stop_monitor
    sleep 2
    start_monitor
}

function show_status() {
    echo "EDI File Monitor Status:"
    echo "========================"
    
    if is_running; then
        local pid=$(get_pid)
        echo "Status: RUNNING (PID: $pid)"
        
        # Show process info
        if command -v ps >/dev/null; then
            echo "Process: $(ps -p $pid -o pid,ppid,etime,cmd --no-headers 2>/dev/null || echo 'Process info unavailable')"
        fi
        
        # Show memory usage
        if [ -f "/proc/$pid/status" ]; then
            echo "Memory: $(grep VmRSS /proc/$pid/status 2>/dev/null || echo 'Memory info unavailable')"
        fi
    else
        echo "Status: STOPPED"
        [ -f "$PID_FILE" ] && echo "Warning: Stale PID file exists"
    fi
    
    # Show log file info
    local log_file="$PROJECT_DIR/logs/file_monitor.log"
    if [ -f "$log_file" ]; then
        echo "Log file: $log_file"
        echo "Last modified: $(date -r "$log_file" 2>/dev/null || echo 'Unknown')"
        echo "Size: $(du -h "$log_file" 2>/dev/null | cut -f1 || echo 'Unknown')"
    else
        echo "Log file: Not found"
    fi
}

function test_connection() {
    echo "Testing SFTP connection..."
    cd "$PROJECT_DIR"
    php "$DAEMON_SCRIPT" --test
}

function run_single() {
    echo "Running single monitoring cycle..."
    cd "$PROJECT_DIR"
    php "$DAEMON_SCRIPT" --single
}

function show_logs() {
    local log_file="$PROJECT_DIR/logs/file_monitor.log"
    
    if [ -f "$log_file" ]; then
        echo "Recent log entries:"
        echo "==================="
        tail -n 20 "$log_file"
    else
        echo "Log file not found: $log_file"
    fi
}

# Main script logic
case "${1:-}" in
    start)
        start_monitor
        ;;
    stop)
        stop_monitor
        ;;
    restart)
        restart_monitor
        ;;
    status)
        show_status
        ;;
    test)
        test_connection
        ;;
    single)
        run_single
        ;;
    logs)
        show_logs
        ;;
    *)
        show_usage
        exit 1
        ;;
esac

exit $?