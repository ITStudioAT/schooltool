const fs = require('fs');
const data = JSON.parse(fs.readFileSync('ward-report.json', 'utf8'));
const high = data.findings.filter(f => f.severity === 'High');

const seen = new Set();
const unique = [];
for (const f of high) {
    const key = f.id + '|' + (f.file || '') + '|' + (f.line || '');
    if (!seen.has(key)) {
        seen.add(key);
        unique.push(f);
    }
}

console.log('Unique High severity findings:', unique.length);
unique.slice(0, 5).forEach((f, i) => {
    console.log('\n#' + (i + 1) + ' [' + f.id + '] ' + f.title);
    console.log('  Category: ' + f.category);
    console.log('  File: ' + (f.file || 'N/A') + (f.line ? ':' + f.line : ''));
    console.log('  Description: ' + f.description.substring(0, 300));
    console.log('  Fix: ' + (f.remediation || 'N/A').substring(0, 300));
    if (f.references && f.references.length) {
        console.log('  Ref: ' + f.references[0]);
    }
});
