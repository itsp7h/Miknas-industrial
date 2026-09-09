# Editor settings

A snapshot of the VS Code configuration used on this project, kept here so it
can be restored on another machine. These files are a copy, not a live link —
VS Code does not read them. Editing them changes nothing until you copy them
back.

## Files

| File | Source on macOS |
|------|-----------------|
| `vscode-settings.json` | `~/Library/Application Support/Code/User/settings.json` |
| `vscode-keybindings.json` | `~/Library/Application Support/Code/User/keybindings.json` |
| `vscode-extensions.txt` | `code --list-extensions` |

## Restore

```bash
DEST="$HOME/Library/Application Support/Code/User"
cp docs/editor/vscode-settings.json    "$DEST/settings.json"
cp docs/editor/vscode-keybindings.json "$DEST/keybindings.json"
xargs -n1 code --install-extension < docs/editor/vscode-extensions.txt
```

## Machine-local values

`dart.flutterSdkPath` points at `/Users/yousif/develop/flutter`. It is only
read by the Dart/Flutter extensions and is unrelated to this project, so on a
different machine either repoint it or drop the key.

## Why here and not `.vscode/`

`.gitignore` excludes `/.vscode`: the project deliberately keeps per-developer
editor config out of the tree, so committing these as `.vscode/settings.json`
would both fight that rule and overwrite each developer's own setup on
checkout. They live under `docs/` as reference material instead.
