import { spawn } from 'node:child_process';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(dirname(fileURLToPath(import.meta.url)));
if (process.platform !== 'win32') {
    console.error('This local launcher uses the project’s Windows runtimes.');
    process.exit(1);
}
const executable = join(
    process.env.SystemRoot || 'C:\\Windows',
    'System32', 'WindowsPowerShell', 'v1.0', 'powershell.exe',
);
const args = ['-NoProfile', '-File', join(root, 'scripts', 'start-local.ps1')];
if (process.argv.includes('--check')) args.push('-Check');
const child = spawn(executable, args, { cwd: root, stdio: 'inherit', shell: false });
child.on('error', () => {
    console.error('Could not start Windows PowerShell. Check that it is enabled on this PC.');
    process.exitCode = 1;
});
child.on('exit', (code) => { process.exitCode = code ?? 1; });
