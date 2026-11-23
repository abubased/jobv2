
const http = require('http');

const server = http.createServer((req, res) => {
    res.writeHead(200, {
        'Content-Type': 'text/plain; charset=utf-8'
    });

    res.end('Reverb Node.js Passenger Test: OK');
});

// Passenger handles the actual port binding.
server.listen(3000, () => {
    console.log('Passenger Node.js application started successfully.');
});

const { spawn } = require('child_process');
const http = require('http');

const php = '/opt/cpanel/ea-php83/root/usr/bin/php';
const project = '/home/submitno/application.submit-now.online';

const server = http.createServer((req, res) => {
    res.writeHead(200, {
        'Content-Type': 'text/plain; charset=utf-8'
    });

    res.end('Laravel Reverb Launcher is running.');
});

const reverb = spawn(
    php,
    [
        'artisan',
        'reverb:start',
        '--host=127.0.0.1',
        '--port=8080',
        '--no-interaction'
    ],
    {
        cwd: project,
        env: {
            ...process.env,
            APP_ENV: 'production'
        },
        stdio: ['ignore', 'inherit', 'inherit']
    }
);

reverb.on('error', (error) => {
    console.error('Reverb process error:', error.message);
});

reverb.on('exit', (code, signal) => {
    console.error(
        `Reverb stopped. Exit code: ${code}, Signal: ${signal}`
    );
});

server.listen(3000, () => {
    console.log('Reverb launcher HTTP process started.');
});

function shutdown() {
    reverb.kill('SIGTERM');
    server.close();
}

process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);
