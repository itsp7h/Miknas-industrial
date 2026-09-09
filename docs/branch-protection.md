# Branch protection

Two gates guard production, deliberately placed at different moments:

| Moment | Gate | Who satisfies it |
|---|---|---|
| Landing a commit on `main` | ruleset **Protect main** — PR required, CI green | anyone with write, once CI passes |
| Releasing `main` to production | `production` environment reviewers | **`yousif07Sei` only** |

`development` is deliberately **not** protected: it auto-deploys to staging, and
the team should be able to push there without ceremony.

## Why the merge gate does not require a review

The obvious design — require an approving review, pinned to `yousif07Sei` via
CODEOWNERS — cannot work while that account also authors the pull requests.
GitHub forbids approving your own PR, and with no bypass actors there is no way
out: every PR opened by `yousif07Sei` would be permanently unmergeable.

It works in `itsp7h/realstate` only because PRs there are authored by `itsp7h`
and approved by `yousif07Sei`. If release PRs here ever move to that model, add
`.github/CODEOWNERS` containing `* @yousif07Sei` and flip
`required_approving_review_count` to 1 with `require_code_owner_review: true`.

Until then the personal gate lives on the **deployment**, where it has no such
problem: approving a deployment is not approving a PR, so `yousif07Sei` can
approve releases of their own commits. `can_admins_bypass` is `false` on that
environment, so holding admin does not help — only a listed reviewer can
release.

`require_extra_approval_for_unattributed_changes` is also left off, unlike
`realstate`. With zero required approvals it would silently demand one whenever
a commit's author email is not linked to a GitHub account — the same
self-approval dead end, reached by accident.

## What it blocks

- direct pushes to `main`, by everyone including repository admins
  (`bypass_actors` is empty)
- merging anything whose CI is red, or whose branch is behind `main`
  (`strict_required_status_checks_policy`)
- deleting `main`, and force-pushing over it

Only the three CI jobs are required. `Verify CI is green` and
`Deploy to production` are check runs on `main` too, but requiring them would be
circular — the deploy cannot finish before the merge that triggers it.

## Applying it

Needs **admin**, which belongs to `itsp7h`; `yousif07Sei` has `push` only. Run
this signed in as the account holding admin:

```bash
gh api -X POST repos/itsp7h/Miknas-industrial/rulesets \
  --input .github/rulesets/protect-main.json
```

Verify:

```bash
gh api repos/itsp7h/Miknas-industrial/rulesets --jq '.[] | "\(.name) \(.enforcement)"'
```

To change it later, `PUT` the same payload to
`repos/itsp7h/Miknas-industrial/rulesets/<id>`.

## The ceiling

Admin belongs to the shared `itsp7h` account, so anyone with access to it can
edit or delete this ruleset and the environment's reviewer list. No
configuration closes that gap. The two real fixes are transferring the
repository to `yousif07Sei` (adding `itsp7h` as a collaborator), or moving it
into an organisation where `yousif07Sei` holds the Owner role — which also
unlocks "restrict who can push to matching branches", the only native way to
name a single account as the one that may merge.

## Local guard

`.claude/hooks/guard-main-branch.sh` refuses pushes and merges to `main` from a
Claude Code session. That is a convenience, not a control: it guards one
terminal, while the ruleset binds anything holding a token.
