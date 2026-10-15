// app.js - Port forwarder for hosting (node.js)
// Usage: node app.js [localPort] [remotePort] [remoteHost]
// Env vars: PORT=80 REMOTE_HOST=x.x.x.x REMOTE_PORT=8080
const net = require('net');

// Chosen port: 8080 (unprivileged, avoids conflicts with Apache/Node defaults)
const config = {
  localPort: process.env.PORT ? parseInt(process.env.PORT, 10) : 8080,
  remoteHost: process.env.REMOTE_HOST || 'localhost',
  remotePort: process.env.REMOTE_PORT ? parseInt(process.env.REMOTE_PORT, 10) : 80,
};

if (process.argv[2]) config.localPort = parseInt(process.argv[2], 10);
if (process.argv[3]) config.remotePort = parseInt(process.argv[3], 10);
if (process.argv[4]) config.remoteHost = process.argv[4];

const server = net.createServer((localSocket) => {
  const remoteSocket = net.connect(config.remotePort, config.remoteHost, () => {
    localSocket.pipe(remoteSocket);
    remoteSocket.pipe(localSocket);
  });
  localSocket.on('error', (err) => { console.error('Local socket error:', err.message); remoteSocket.destroy(); });
  remoteSocket.on('error', (err) => { console.error('Remote socket error:', err.message); localSocket.destroy(); });
});

server.listen(config.localPort, () => {
  console.log(`Port forward: http://localhost:${config.localPort}  ->  ${config.remoteHost}:${config.remotePort}`);
});

process.on('SIGINT', () => {
  server.close(() => { console.log('Port forwarder stopped.'); process.exit(0); });
});

module.exports = server;
