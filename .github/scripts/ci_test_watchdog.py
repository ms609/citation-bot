#!/usr/bin/env python3
"""Run ParaTest with absolute and no-output deadlines on Linux CI runners.

Forward the normal console output without keeping a second raw log (CI can have
credentials). Diagnostic lines contain only allowlisted ParaTest file paths and
numeric test progress. The 80-minute deadline includes PHP/Composer startup and
post-test coverage/report generation.
"""

import argparse
import os
import re
import selectors
import signal
import subprocess
import sys
import time

WORKER = re.compile(r"Process\s+(\d+)\s+executing:\s+(tests/[A-Za-z0-9_./-]+\.php)")
PROGRESS = re.compile(r"(\d+)\s*/\s*(\d+)\s*\(\s*(\d+)%\)")


def describe(workers: dict[int, str], progress: str) -> str:
    names = ", ".join(f"worker {number}: {path}" for number, path in sorted(workers.items()))
    return f"Last progress: {progress or 'unknown'}; last assigned files: {names or 'unknown'}"


def stop_group(process: subprocess.Popen[bytes], grace: float) -> None:
    """Stop the entire Composer / ParaTest worker group, not only its shell."""
    try:
        os.killpg(process.pid, signal.SIGTERM)
    except ProcessLookupError:
        pass
    try:
        process.wait(timeout=grace)
    except subprocess.TimeoutExpired:
        pass
    # The parent might exit on TERM while a worker survives. Kill the group
    # even when the original parent has already gone away.
    try:
        os.killpg(process.pid, signal.SIGKILL)
    except ProcessLookupError:
        pass
    process.wait()


def run(command: list[str], idle: float, maximum: float, warn: float, grace: float) -> int:
    process = subprocess.Popen(
        command,
        stdin=subprocess.DEVNULL,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        start_new_session=True,  # makes process-group termination possible
        bufsize=0,
    )
    assert process.stdout is not None
    start = last_output = last_warning = time.monotonic()
    workers: dict[int, str] = {}
    progress = ""
    suffix = ""
    received_signal = 0

    def interrupt(signum: int, _frame: object) -> None:
        nonlocal received_signal
        received_signal = signum

    old_term = signal.signal(signal.SIGTERM, interrupt)
    old_int = signal.signal(signal.SIGINT, interrupt)
    try:
        with selectors.DefaultSelector() as selector:
            selector.register(process.stdout, selectors.EVENT_READ)
            while selector.get_map():
                now = time.monotonic()
                elapsed = now - start
                silent = now - last_output
                if received_signal:
                    print("CI test watchdog: termination requested; stopping test workers.", file=sys.stderr, flush=True)
                    stop_group(process, grace)
                    return 128 + received_signal
                if elapsed >= maximum or silent >= idle:
                    reason = (f"80-minute maximum exceeded ({elapsed:.0f}s)" if elapsed >= maximum
                              else f"no test output for {silent:.0f}s (limit {idle:.0f}s)")
                    print(f"::error::CI test watchdog: {reason}. {describe(workers, progress)}", file=sys.stderr, flush=True)
                    stop_group(process, grace)
                    return 124
                if silent >= warn and now - last_warning >= warn:
                    print(f"::warning::CI test watchdog: no test output for {silent:.0f}s. "
                          f"{describe(workers, progress)}", file=sys.stderr, flush=True)
                    last_warning = now

                for key, _ in selector.select(timeout=min(1.0, maximum - elapsed, idle - silent)):
                    chunk = os.read(key.fd, 65536)
                    if not chunk:
                        selector.unregister(key.fileobj)
                        continue
                    sys.stdout.buffer.write(chunk)
                    sys.stdout.buffer.flush()
                    now = last_output = last_warning = time.monotonic()
                    # Keep only a short overlap; never store raw test output on disk.
                    new_text = chunk.decode("utf-8", errors="replace")
                    candidate = suffix + new_text
                    for match in WORKER.finditer(candidate):
                        if match.end() > len(suffix):
                            workers[int(match.group(1))] = match.group(2)[:220]
                    for match in PROGRESS.finditer(candidate):
                        if match.end() > len(suffix):
                            progress = f"{match.group(1)}/{match.group(2)} ({match.group(3)}%)"
                    suffix = candidate[-260:]
    finally:
        signal.signal(signal.SIGTERM, old_term)
        signal.signal(signal.SIGINT, old_int)
        process.stdout.close()

    status = process.wait()
    return status if 0 <= status <= 255 else 128 - status if status < 0 else 1


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--idle-seconds", type=float, default=1200)
    parser.add_argument("--max-seconds", type=float, default=4800)
    parser.add_argument("--warn-seconds", type=float, default=300)
    parser.add_argument("--grace-seconds", type=float, default=30)
    parser.add_argument("command", nargs=argparse.REMAINDER)
    arguments = parser.parse_args()
    command = arguments.command
    if command and command[0] == "--":
        command = command[1:]
    if not command or min(arguments.idle_seconds, arguments.max_seconds,
                          arguments.warn_seconds, arguments.grace_seconds) <= 0:
        parser.error("a command and positive deadlines are required")
    return run(command, arguments.idle_seconds, arguments.max_seconds,
               arguments.warn_seconds, arguments.grace_seconds)


if __name__ == "__main__":
    sys.exit(main())
