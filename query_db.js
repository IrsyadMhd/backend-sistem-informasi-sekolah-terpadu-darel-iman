const net = require('net');

const client = net.createConnection({ path: '/tmp/.s.PGSQL.5432' }, () => {
    // Startup Message
    const user = 'postgres';
    const db = 'school_management';
    
    let payload = Buffer.from([0x00, 0x03, 0x00, 0x00]); // Protocol 3.0
    payload = Buffer.concat([payload, Buffer.from(`user\0${user}\0database\0${db}\0\0`)]);
    
    const length = payload.length + 4;
    const lenBuf = Buffer.alloc(4);
    lenBuf.writeUInt32BE(length, 0);
    
    client.write(Buffer.concat([lenBuf, payload]));
});

let authenticated = false;

client.on('data', (data) => {
    let offset = 0;
    while (offset < data.length) {
        const type = String.fromCharCode(data[offset]);
        const len = data.readUInt32BE(offset + 1);
        const packet = data.slice(offset + 5, offset + 1 + len);
        offset += 1 + len;
        
        if (type === 'R') {
            const authType = packet.readUInt32BE(0);
            if (authType === 0) {
                authenticated = true;
                sendQuery("SELECT u.id, u.name, u.email, u.is_active, r.name as role_name FROM users u LEFT JOIN model_has_roles mhr ON u.id = mhr.model_id LEFT JOIN roles r ON mhr.role_id = r.id;");
            } else if (authType === 3 || authType === 5) {
                const passBuf = Buffer.from('password\0');
                const passLenBuf = Buffer.alloc(5);
                passLenBuf.write('p', 0);
                passLenBuf.writeUInt32BE(passBuf.length + 4, 1);
                client.write(Buffer.concat([passLenBuf, passBuf]));
            }
        } else if (type === 'D') {
            const numCols = packet.readUInt16BE(0);
            let pOffset = 2;
            const row = [];
            for (let i = 0; i < numCols; i++) {
                const colLen = packet.readInt32BE(pOffset);
                pOffset += 4;
                if (colLen === -1) {
                    row.push(null);
                } else {
                    row.push(packet.slice(pOffset, pOffset + colLen).toString('utf8'));
                    pOffset += colLen;
                }
            }
            console.log('RESULT_ROW:', JSON.stringify(row));
        } else if (type === 'Z') {
            if (authenticated) {
                client.end();
            }
        } else if (type === 'E') {
            console.error('Error:', packet.toString('utf8'));
        }
    }
});

function sendQuery(sql) {
    const sqlBuf = Buffer.from(sql + '\0');
    const qLenBuf = Buffer.alloc(5);
    qLenBuf.write('Q', 0);
    qLenBuf.writeUInt32BE(sqlBuf.length + 4, 1);
    client.write(Buffer.concat([qLenBuf, sqlBuf]));
}

client.on('error', (err) => {
    console.error('Socket error:', err);
});
