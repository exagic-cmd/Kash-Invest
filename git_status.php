<?php
// Run git diff to see what files are modified
echo "=== Git Modified Files ===\n";
echo shell_exec('git status -s') ?? "git command failed\n";
