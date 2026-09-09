#!/usr/bin/env bash
# Exercises .claude/hooks/guard-main-branch.sh. Lives in a file because the
# cases themselves contain the very command text the hook matches on.
cd /home/yousif/p7hProjects/Miknas-industrial || exit 1
H=.claude/hooks/guard-main-branch.sh
SHIM=/tmp/claude-1000/-home-yousif-p7hProjects-Miknas-industrial/d2468e2e-dbfb-424a-bf3d-296cfd43aec4/scratchpad/shim

pass=0; fail=0

check() { # check <expected: deny|allow> <label> <command> [pathprefix]
    local want="$1" label="$2" cmd="$3" pfx="${4:-/usr/bin}"
    local out got
    out=$(printf '{"tool_name":"Bash","tool_input":{"command":%s}}' \
          "$(jq -Rn --arg c "$cmd" '$c')" | PATH="$pfx:$PATH" bash "$H")
    got=$(printf '%s' "$out" | jq -r '.hookSpecificOutput.permissionDecision' 2>/dev/null || true)
    got="${got:-allow}"
    if [ "$got" = "$want" ]; then
        pass=$((pass+1)); printf '  ok    %-6s %s\n' "$got" "$label"
    else
        fail=$((fail+1)); printf '  FAIL  want=%s got=%s  %s\n' "$want" "$got" "$label"
    fi
}

PUSH_MAIN='git push origin main'
HEREDOC=$(printf 'gh pr create --base main --body-file - <<%sMD%s\nVerified with %s --dry-run which the hook refused.\nMD\n' "'" "'" "$PUSH_MAIN")
COMMITDOC=$(printf 'git commit -F - <<%sMSG%s\nfix: stop %s from working\nMSG\n' "'" "'" "$PUSH_MAIN")

echo "-- prose that merely mentions pushing to main (must be allowed) --"
check allow "heredoc PR body mentioning it"      "$HEREDOC"
check allow "heredoc commit message mentioning"  "$COMMITDOC"
check allow "-m message mentioning it"           "git commit -m \"note: never $PUSH_MAIN again\""
check allow "gh pr create --base main"           'gh pr create --base main --head development --title "deploy on merge"'

echo "-- real writes to main (must be denied) --"
check deny  "push origin main"                   "$PUSH_MAIN"
check deny  "push quoted main"                   'git push origin "main"'
check deny  "push dev:main forced"               'git push -f origin development:main'
check deny  "push HEAD:main"                     'git push origin HEAD:main'
check deny  "compound cd && push main"           "cd /tmp && $PUSH_MAIN"
check deny  "gh pr merge"                        'gh pr merge 4'
check deny  "gh pr merge with flags"             'gh pr merge 4 --squash --delete-branch'

echo "-- allowed on development --"
check allow "push development"                   'git push origin development'
check allow "bare push (on development)"         'git push'
check allow "merge main into development"        'git merge main'
check allow "plain status"                       'git status'
check allow "unrelated command"                  'npm test'
check allow "delete branch named maintenance"    'git push origin --delete maintenance'

echo "-- with main checked out (shim) --"
check deny  "bare push on main"                  'git push'                      "$SHIM"
check deny  "push origin (no refspec) on main"   'git push origin'               "$SHIM"
check deny  "merge on main"                      'git merge development'         "$SHIM"
check deny  "rebase on main"                     'git rebase development'        "$SHIM"
check deny  "cherry-pick on main"                'git cherry-pick abc123'        "$SHIM"
check allow "explicit push of dev while on main" 'git push origin development'   "$SHIM"
check allow "fetch on main"                      'git fetch origin'              "$SHIM"
check allow "log on main"                        'git log -1'                    "$SHIM"

echo
printf 'passed %d, failed %d\n' "$pass" "$fail"
[ "$fail" -eq 0 ]
