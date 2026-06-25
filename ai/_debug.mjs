import { readExcel } from './src/reader.js';

const data = readExcel('./test-real.xlsx', { headerRow: true });
console.log('Total rows:', data.totalRows);
console.log('Headers:', JSON.stringify(data.headers));
for (let i = 0; i < data.totalRows; i++) {
  const vals = Object.values(data.rows[i]).filter(v => v !== '');
  if (vals.length > 0) {
    console.log(`Row ${i}:`, JSON.stringify(data.rows[i]));
  }
}