#!/usr/bin/env bash
#
# Commit-level checkpoints, so a lane can roll back to a known-good state.
#
# Multiple agents work these repos at once. When one lane breaks something, the
# question is always "what was the last state that actually worked, and how do I
# get back to it without throwing away everyone else's work?" A checkpoint is a
# pushed annotated tag answering exactly that.
#
#   ./scripts/checkpoint.sh save  "ai-action-layer"   # tag HEAD as good
#   ./scripts/checkpoint.sh list                      # newest first
#   ./scripts/checkpoint.sh show  <tag>               # what was in it
#   ./scripts/checkpoint.sh back  <tag>               # branch off it (safe)
#   ./scripts/checkpoint.sh diff  <tag>               # what changed since
#
# `back` never moves a branch or discards commits: it creates a new branch at
# the checkpoint. Recovering must not be able to destroy the thing you are
# recovering from.

set -euo pipefail

PREFIX="checkpoint"
BRANCH="$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo detached)"

die() { echo "error: $*" >&2; exit 1; }

usage() {
    sed -n '3,24p' "$0" | sed 's/^# \{0,1\}//'
    exit "${1:-0}"
}

require_clean() {
    [ -n "$(git status --porcelain)" ] || return 0

    local dirty
    dirty="$(git status --porcelain | wc -l | tr -d ' ')"

    echo "note: ${dirty} uncommitted file(s) present. A checkpoint records the" >&2
    echo "      COMMIT, so those changes are NOT saved by it." >&2

    # Only prompt when a human is actually there. These repos are worked by
    # agents in non-interactive shells, and a dirty tree is the normal state
    # when several lanes are running - blocking on a prompt nobody can answer
    # would hang the very automation this is meant to protect.
    if [ ! -t 0 ]; then
        echo "      (non-interactive: continuing)" >&2
        return 0
    fi

    printf "continue anyway? [y/N] " >&2
    read -r reply
    case "$reply" in [yY]*) ;; *) die "aborted" ;; esac
}

cmd_save() {
    local label="${1:-}"
    [ -n "$label" ] || die "a label is required, e.g. checkpoint.sh save ai-action-layer"

    # Slug the label so tags stay predictable and shell-safe.
    label="$(echo "$label" | tr '[:upper:] ' '[:lower:]-' | tr -cd 'a-z0-9-')"
    local tag="${PREFIX}/$(date +%Y%m%d-%H%M)/${label}"

    require_clean

    local sha subject
    sha="$(git rev-parse --short HEAD)"
    subject="$(git log -1 --format=%s)"

    git tag -a "$tag" -m "Checkpoint: ${label}

Branch:  ${BRANCH}
Commit:  ${sha} ${subject}
Saved:   $(date '+%Y-%m-%d %H:%M:%S %z')

Known-good state. Return to it with:
  ./scripts/checkpoint.sh back ${tag}"

    echo "checkpoint saved: ${tag}  ->  ${sha}"

    if git remote get-url origin >/dev/null 2>&1; then
        if git push origin "$tag" >/dev/null 2>&1; then
            echo "pushed to origin (survives this machine)"
        else
            echo "warning: could not push. It exists locally only:" >&2
            echo "         git push origin ${tag}" >&2
        fi
    fi
}

cmd_list() {
    local tags
    tags="$(git tag -l "${PREFIX}/*" --sort=-creatordate)"
    [ -n "$tags" ] || { echo "no checkpoints yet - create one with: $0 save <label>"; return 0; }

    printf "%-46s %-9s %s\n" "CHECKPOINT" "COMMIT" "SAVED"
    while IFS= read -r tag; do
        printf "%-46s %-9s %s\n" \
            "$tag" \
            "$(git rev-list -n1 --abbrev-commit "$tag")" \
            "$(git log -1 --format=%ad --date=format:'%Y-%m-%d %H:%M' "$tag")"
    done <<< "$tags"
}

cmd_show() {
    local tag="${1:-}"
    [ -n "$tag" ] || die "which checkpoint? try: $0 list"
    git rev-parse -q --verify "refs/tags/${tag}" >/dev/null || die "no such checkpoint: ${tag}"
    git show --no-patch "$tag"
}

cmd_diff() {
    local tag="${1:-}"
    [ -n "$tag" ] || die "which checkpoint? try: $0 list"
    git rev-parse -q --verify "refs/tags/${tag}" >/dev/null || die "no such checkpoint: ${tag}"
    echo "Changes since ${tag}:"
    git diff --stat "${tag}..HEAD"
}

cmd_back() {
    local tag="${1:-}"
    [ -n "$tag" ] || die "which checkpoint? try: $0 list"
    git rev-parse -q --verify "refs/tags/${tag}" >/dev/null || die "no such checkpoint: ${tag}"

    local target="restore/$(basename "$tag")-$(date +%H%M)"

    echo "Creating ${target} at ${tag}."
    echo "Your current branch (${BRANCH}) is untouched, and nothing is discarded."
    git checkout -b "$target" "$tag"

    cat <<EOF

You are on ${target}, at the checkpoint.

  - Inspect or cherry-pick what you need.
  - To go back:            git checkout ${BRANCH}
  - To keep this instead:  merge or PR ${target} into ${BRANCH}

Deliberately NOT done for you: resetting ${BRANCH}. Other lanes may have
commits on it, and a reset would take theirs down with yours.
EOF
}

case "${1:-}" in
    save) shift; cmd_save "$@" ;;
    list) shift; cmd_list "$@" ;;
    show) shift; cmd_show "$@" ;;
    diff) shift; cmd_diff "$@" ;;
    back) shift; cmd_back "$@" ;;
    -h|--help|help|"") usage 0 ;;
    *) echo "unknown command: $1" >&2; usage 1 ;;
esac
