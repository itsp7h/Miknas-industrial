#!/usr/bin/env bash
#
# PreToolUse(Bash) guard: keeps `main` a review-only branch.
#
# `main` is the production branch (steelerp.p7h.me); `development` is staging.
# The rule this enforces: code reaches `main` only through a pull request from
# `development`, and only the repository owner reviews and merges it. Anything
# that would put a commit on `main` — or merge a PR — from a terminal is denied
# here rather than left to be remembered.
#
# Reads the PreToolUse payload on stdin and, when the command matches, prints a
# deny decision as JSON. Exits 0 either way: a non-zero exit would surface as a
# hook error rather than as a clean refusal.
set -uo pipefail

payload=$(cat)
cmd=$(printf '%s' "$payload" | jq -r '.tool_input.command // empty' 2>/dev/null)
[ -z "$cmd" ] && exit 0

# ---------------------------------------------------------------------------
# Only the executable part of the command is matched, never text the command
# merely carries. A commit message or PR body that *describes* pushing to main
# is prose, not a push — and an earlier version of this hook blocked its own
# PR for saying so. Two kinds of payload are stripped first:
#
#   1. heredoc bodies — `git commit -F - <<'MSG' … MSG`, `gh pr create
#      --body-file <<'MD' … MD`. The line opening the heredoc is kept, since
#      the real command lives on it; the body is dropped.
#   2. quoted values of message-ish flags — -m, --message, --title, --body.
#
# Quoted arguments elsewhere are left intact, so `git push origin "main"` is
# still caught.
# ---------------------------------------------------------------------------
stripped=$(printf '%s' "$cmd" | awk '
    skip {
        if ($0 ~ "^[[:space:]]*" term "[[:space:]]*$") skip = 0
        next
    }
    {
        print
        if (match($0, /<<-?[[:space:]]*['"'"'"]?[A-Za-z_][A-Za-z0-9_]*['"'"'"]?/)) {
            term = substr($0, RSTART, RLENGTH)
            sub(/^<<-?[[:space:]]*/, "", term)
            gsub(/['"'"'"]/, "", term)
            skip = 1
        }
    }
')

# Collapse newlines and runs of whitespace so a multi-line or oddly spaced
# command cannot slip past the patterns below.
norm=$(printf '%s' "$stripped" \
    | tr '\n\t' '  ' \
    | sed -E "s/(-m|--message|-t|--title|-b|--body)[= ]+'[^']*'/ /g; \
              s/(-m|--message|-t|--title|-b|--body)[= ]+\"[^\"]*\"/ /g" \
    | tr -s ' ')

deny() {
    jq -nc --arg r "$1" '{
      hookSpecificOutput: {
        hookEventName: "PreToolUse",
        permissionDecision: "deny",
        permissionDecisionReason: $r
      }
    }'
    exit 0
}

has() { printf '%s' "$norm" | grep -qE "$1"; }

# Current branch, so "push while main is checked out" is caught even when the
# command never spells `main` out. Empty outside a work tree.
branch=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || true)

# `main` named as a push destination: `origin main`, `HEAD:main`, `dev:main`.
# The word boundaries keep `maintenance` or `main-old` from matching.
names_main='(^| )("?main"?|[^ ]+:"?main"?)( |$)'

# 1. Merging a pull request is the owner's decision, never a terminal's.
if has '(^|[;&|] *)gh +pr +merge( |$)'; then
    deny "Blocked by .claude/hooks/guard-main-branch.sh: merging a pull request is the repository owner's call. Open or update the PR and hand over the link — yousif07Sei reviews and merges it on GitHub."
fi

# 2. Any push that targets main, from any branch.
if has '(^|[;&|] *)git +push' && has "$names_main"; then
    deny "Blocked by .claude/hooks/guard-main-branch.sh: this pushes to main, which is the production branch. Code reaches main only through a reviewed PR from development. Push to development instead, then open a development -> main PR."
fi

# 3. A push that names no refspec while main is checked out: `git push` and
#    `git push origin` both publish the current branch, so on main they reach
#    production without ever spelling it out. An explicit refspec for some other
#    branch (`git push origin development`) is left alone — rule 2 already
#    covers the case where that refspec is main.
if [ "$branch" = "main" ] && has '(^|[;&|] *)git +push'; then
    push_args=$(printf '%s' "$norm" \
        | sed -E 's/.*(^|[;&|] *)git +push//; s/[;&|].*//' \
        | tr ' ' '\n' | grep -vE '^(-.*)?$' | wc -l)
    # 0 args = `git push`; 1 = `git push origin` (remote only, no refspec).
    if [ "$push_args" -lt 2 ]; then
        deny "Blocked by .claude/hooks/guard-main-branch.sh: main is checked out and this push names no branch, so it publishes main — the production branch. Switch to development and push there, then open a development -> main PR for review."
    fi
fi

# 4. Merging or rebasing into main locally, which is how a push to main gets
#    something to push in the first place.
if [ "$branch" = "main" ] && has '(^|[;&|] *)git +(merge|rebase|cherry-pick)( |$)'; then
    deny "Blocked by .claude/hooks/guard-main-branch.sh: main is checked out, so this writes commits onto the production branch. main is updated only by merging a reviewed PR on GitHub. Back-merge into development instead if you need the branches in sync."
fi

exit 0
